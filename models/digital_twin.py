"""PART 48 - Digital Twin Simulation (v4.0, donnees reelles).

Simule l'impact de scenarios (fermeture d'usine, pic de vent) sur l'AQI futur
en combinant un modele physique de panache gaussien (PINN, Part 38) avec des
conditions initiales et des scenarios tires des DONNEES REELLES d'open_data.

Permet aux autorites de tester des scenarios "et si" (ex: arret temporaire du
complexe chimique de Ghannouche).

CHANGEMENT v4.0 :
  - Toute reference au CGAN a ete retiree. Elle n'existait que dans la
    documentation : le code n'a jamais appele cgan_trainer.py ni gan.php.
  - `base_aqi` n'est plus une constante arbitraire (90). Par defaut, la valeur
    de depart est lue dans open_data pour la zone concernee.
  - Nouveau : sample_real_window() tire une fenetre HISTORIQUE REELLE au lieu
    de generer une trajectoire synthetique. Un scenario "pic industriel"
    devient un episode reellement observe a Ghannouche, pas une hallucination
    d'un generateur entraine sur du bruit tuile. C'est scientifiquement plus
    defendable devant un jury.

Degradation gracieuse : si pinn_dispersion n'est pas disponible, un modele de
dispersion simplifie en numpy pur prend le relais.
Ecrit dans digital_twin_scenarios.
"""
from __future__ import annotations
import json
import math
import random
from datetime import datetime

try:
    from pinn_dispersion import gaussian_plume_equation
except Exception:  # pragma: no cover
    def gaussian_plume_equation(wind_speed, wind_dir, distance_to_source, **kw):
        u = max(0.5, float(wind_speed))
        x = max(1.0, float(distance_to_source))
        return 1.0 / (u * math.sqrt(x))


# ---------------------------------------------------------------------------
# Lecture des conditions REELLES depuis open_data
# ---------------------------------------------------------------------------
def load_real_baseline(db, zone_id: int, lookback_hours: int = 720):
    """Conditions initiales REELLES pour une zone, moyennees sur les dernieres
    `lookback_hours` heures d'open_data (30 jours par defaut).

    Remplace l'ancien base_aqi=90 code en dur. Retourne None si la DB n'est
    pas joignable, auquel cas l'appelant garde ses parametres explicites.
    """
    if db is None:
        return None
    try:
        cur = db.cursor(dictionary=True)
        cur.execute(
            """SELECT AVG(o.us_aqi)          AS base_aqi,
                      AVG(o.wind_speed_10m)   AS wind_speed,
                      AVG(o.wind_direction_10m) AS wind_dir,
                      MAX(o.us_aqi)           AS peak_aqi,
                      MIN(o.us_aqi)           AS min_aqi,
                      COUNT(*)                AS n
               FROM open_data o
               JOIN zones z ON z.city_key = o.city
               WHERE z.id = %s
                 AND o.time >= DATE_SUB((SELECT MAX(time) FROM open_data),
                                        INTERVAL %s HOUR)""",
            (zone_id, lookback_hours))
        row = cur.fetchone()
        cur.close()
        if not row or not row.get("n"):
            return None
        return {
            "base_aqi": float(row["base_aqi"] or 0.0),
            "wind_speed": float(row["wind_speed"] or 3.0),
            "wind_dir": float(row["wind_dir"] or 90.0),
            "peak_aqi": float(row["peak_aqi"] or 0.0),
            "min_aqi": float(row["min_aqi"] or 0.0),
            "n_hours": int(row["n"]),
        }
    except Exception as e:  # pragma: no cover
        print(f"[twin] baseline reel indisponible: {e}")
        return None


def sample_real_window(db, zone_id: int, hours: int = 24, scenario: str = "normal"):
    """Tire une fenetre HISTORIQUE REELLE de `hours` heures dans open_data.

    Remplace toute generation synthetique. Les scenarios ne sont plus des
    trajectoires fabriquees mais des episodes reellement mesures :

      normal -> fenetre tiree dans la distribution centrale
      peak   -> fenetre dont l'AQI max tombe dans le decile SUPERIEUR
                (episode de pollution industrielle reellement observe)
      clean  -> fenetre dont l'AQI max tombe dans le decile INFERIEUR
                (periode de bon air reellement observee)

    Retourne (curve, meta) ou curve est la liste horaire d'AQI reels.
    """
    if db is None:
        return None, None
    try:
        cur = db.cursor(dictionary=True)
        cur.execute(
            """SELECT o.time, o.us_aqi, o.wind_speed_10m, o.sulphur_dioxide,
                      o.pm2_5, o.pm10, o.dust
               FROM open_data o
               JOIN zones z ON z.city_key = o.city
               WHERE z.id = %s AND o.us_aqi IS NOT NULL
               ORDER BY o.time ASC""", (zone_id,))
        rows = cur.fetchall()
        cur.close()
    except Exception as e:  # pragma: no cover
        print(f"[twin] lecture open_data: {e}")
        return None, None

    if len(rows) < hours * 2:
        return None, None

    # Fenetres glissantes non chevauchantes tous les 6h : suffisant pour un
    # echantillonnage varie sans faire exploser la memoire sur 21 000 lignes.
    windows = [rows[i:i + hours] for i in range(0, len(rows) - hours, 6)]
    if not windows:
        return None, None

    def wmax(w):
        return max(float(r["us_aqi"] or 0) for r in w)

    if scenario == "peak":
        windows.sort(key=wmax, reverse=True)
        pool = windows[: max(1, len(windows) // 10)]
    elif scenario == "clean":
        windows.sort(key=wmax)
        pool = windows[: max(1, len(windows) // 10)]
    else:
        windows.sort(key=wmax)
        lo, hi = len(windows) // 4, 3 * len(windows) // 4
        pool = windows[lo:hi] or windows

    win = random.choice(pool)
    curve = [round(float(r["us_aqi"] or 0), 1) for r in win]
    meta = {
        "source": "open_data (reel, non simule)",
        "scenario": scenario,
        "start": str(win[0]["time"]),
        "end": str(win[-1]["time"]),
        "peak_aqi": round(wmax(win), 1),
        "mean_wind": round(
            sum(float(r["wind_speed_10m"] or 0) for r in win) / len(win), 2),
        "n_candidate_windows": len(pool),
    }
    return curve, meta


# ---------------------------------------------------------------------------
# Simulation contrefactuelle (modele physique)
# ---------------------------------------------------------------------------
def simulate_scenario(zone_id: int, params: dict, hours: int = 24, db=None):
    """Simule une courbe AQI horaire sous un scenario donne.

    params supportes :
      - source_reduction_pct  : 0..100 (ex: fermeture usine => 80)
      - wind_speed            : m/s (pic de vent)
      - base_aqi              : AQI de depart. Si absent ET si `db` est fourni,
                                la valeur est lue dans open_data pour la zone.
      - distance_to_source_m  : distance zone <-> source

    C'est une simulation CONTREFACTUELLE : elle repond a "que se passerait-il
    si on reduisait la source de X% ?", question a laquelle aucune donnee
    historique ne peut repondre directement. Le point de depart, lui, est bien
    reel.
    """
    real = load_real_baseline(db, zone_id) if db is not None else None

    if "base_aqi" in params:
        base = float(params["base_aqi"])
        base_src = "parametre explicite"
    elif real:
        base = real["base_aqi"]
        base_src = f"open_data, moyenne sur {real['n_hours']}h reelles"
    else:
        base = 90.0
        base_src = "defaut de secours (DB indisponible)"

    reduction = float(params.get("source_reduction_pct", 0)) / 100.0
    wind = float(params.get("wind_speed", real["wind_speed"] if real else 3.0))
    dist = float(params.get("distance_to_source_m", 800))

    # Facteur physique (panache) normalise par rapport a un vent de reference.
    ref = gaussian_plume_equation(3.0, 90.0, dist)
    curve = []
    for h in range(hours):
        # Le vent varie legerement autour de la valeur de scenario.
        w = max(0.5, wind + math.sin(h / 3.0) * 0.5)
        phys = gaussian_plume_equation(w, 90.0, dist)
        phys_ratio = (phys / ref) if ref > 0 else 1.0
        # Emission reduite par le scenario + dispersion physique.
        aqi = base * (1 - reduction) * phys_ratio
        # Legere inertie temporelle.
        if curve:
            aqi = 0.7 * aqi + 0.3 * curve[-1]
        curve.append(round(max(0.0, aqi), 1))

    # La confiance monte quand le point de depart vient de donnees reelles.
    confidence = 0.4
    if reduction or params.get("wind_speed"):
        confidence = 0.6
    if real:
        confidence = min(0.85, confidence + 0.2)

    meta = {"base_aqi": round(base, 1), "base_source": base_src,
            "wind_speed": round(wind, 2), "distance_m": dist}
    return curve, confidence, meta


def run_and_store(db, scenario_name: str, zone_id: int, params: dict,
                  hours: int = 24, mode: str = "physical"):
    """Execute un scenario et le stocke dans digital_twin_scenarios.

    mode:
      "physical"  -> simulation contrefactuelle (panache gaussien)
      "historical"-> echantillonnage d'une fenetre REELLE d'open_data.
                     params["scenario"] vaut alors normal / peak / clean.
    """
    if mode == "historical":
        curve, meta = sample_real_window(
            db, zone_id, hours, params.get("scenario", "normal"))
        if curve is None:
            print("[twin] pas assez de donnees reelles, repli sur le mode physique")
            mode = "physical"
        else:
            conf = 1.0  # ce sont des mesures reelles, pas une prevision

    if mode == "physical":
        curve, conf, meta = simulate_scenario(zone_id, params, hours, db=db)

    if db is not None:
        try:
            cur = db.cursor()
            cur.execute(
                "INSERT INTO digital_twin_scenarios "
                "(scenario_name, created_at, zone_id, parameters_json, "
                "simulated_aqi_curve, confidence) VALUES (%s,%s,%s,%s,%s,%s)",
                (scenario_name, datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                 zone_id, json.dumps({**params, "mode": mode, "meta": meta}),
                 json.dumps(curve), conf))
            db.commit()
        except Exception as e:  # pragma: no cover
            print(f"[twin] insert: {e}")
    return {"curve": curve, "confidence": conf, "mode": mode, "meta": meta}


if __name__ == "__main__":
    try:
        import db_config
        conn = db_config.try_connection()
    except Exception:
        conn = None

    print("--- Scenario contrefactuel : arret 80% du GCT a Ghannouche ---")
    print(run_and_store(conn, "Arret GCT 80%", 2,
                        {"source_reduction_pct": 80, "wind_speed": 5},
                        mode="physical"))

    print("\n--- Episode REEL de pic a Ghannouche (echantillonne, non simule) ---")
    print(run_and_store(conn, "Pic industriel observe", 2,
                        {"scenario": "peak"}, mode="historical"))

    if conn:
        conn.close()