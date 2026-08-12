"""models/data_loader.py — v4.0 (donnees reelles Open-Meteo / CAMS uniquement)

CHANGEMENT MAJEUR v4.0
----------------------
Ce module ne genere PLUS AUCUNE donnee. Avant, il :
  - interpolait ~150 points reels sur une grille horaire (_interp_hourly),
  - dupliquait la serie par tuilage + bruit gaussien jusqu'a 2016 lignes
    (_densify, TARGET_LEN),
  - fabriquait des zones entierement synthetiques (_synthetic_zone_frame),
  - concatenait les lignes CGAN de api_readings_augmented (load_api_augmented).

La table `open_data` contient desormais ~130 000 lignes horaires REELLES
(Open-Meteo / CAMS Europe + ERA5) pour 7 villes, du 2024-01-01 au 2026-07-02.
Toute generation est donc inutile ET nuisible : elle injectait de
l'autocorrelation artificielle qui gonflait les R2.

SOURCE UNIQUE : table `open_data`, jointe a `zones` via `zones.city_key`.

DECISION SUR LES FEATURES (imposee par la section 3.3 du prompt)
----------------------------------------------------------------
`uv_index`, `forecast_3h` et `forecast_6h` n'existent pas dans open_data et ne
sont PAS reconstituables honnetement -> ils sont RETIRES du vecteur.
En compensation, open_data expose 5 variables reelles que l'ancien pipeline
n'avait pas : o3, co, dust, precipitation, cloud_cover -> elles sont AJOUTEES.
Bilan : 27 - 3 + 5 = 29 features. Voir FEATURE_NAMES dans train_all.py.

SPLIT (imposee par la section 3.6)
----------------------------------
Split chronologique strict 80/20 par ville, sur la serie reelle triee par
temps. Train = les 80% les plus ANCIENS. Test = les 20% les plus RECENTS.
Aucun marqueur _synth / _holdout ne subsiste. Cette regle s'applique a tous
les modeles sans exception (AR(7), RF, XGBoost, BiLSTM, BiLSTM+AE, Fuzzy T2).
"""
from __future__ import annotations

from typing import Dict, List, Tuple

import pandas as pd

try:  # import en package (python -m models.train_all)
    from . import db_config
except ImportError:  # import en script direct (python models/data_loader.py)
    import db_config  # type: ignore


# --------------------------------------------------------------------------
# Configuration
# --------------------------------------------------------------------------

TRAIN_RATIO = 0.8

# Nombre max d'heures consecutives manquantes que l'on accepte de combler par
# interpolation lineaire. Au-dela, on laisse le NaN : c'est un vrai trou de
# donnees, pas quelque chose que l'on a le droit d'inventer.
MAX_INTERP_GAP = 3

# open_data (colonnes Open-Meteo) -> cles internes attendues par _feature_row()
COLUMN_MAP = {
    "us_aqi": "aqi",
    "pm2_5": "pm25",
    "pm10": "pm10",
    "sulphur_dioxide": "so2",
    "nitrogen_dioxide": "no2",
    "ozone": "o3",
    "carbon_monoxide": "co",
    "dust": "dust",
    "temperature_2m": "temperature",
    "relative_humidity_2m": "humidity",
    "wind_speed_10m": "wind_speed",
    "wind_direction_10m": "wind_direction",
    "surface_pressure": "pressure",
    "precipitation": "precipitation",
    "cloud_cover": "cloud_cover",
}

# Colonnes numeriques sur lesquelles l'interpolation courte est autorisee.
NUMERIC_KEYS = list(COLUMN_MAP.values())


# --------------------------------------------------------------------------
# Connexion
# --------------------------------------------------------------------------

def connect():
    """Connexion MySQL. db_config est inchange (section 3.1 du prompt)."""
    return db_config.get_connection()


def load_zones(conn) -> List[dict]:
    """Retourne les zones possedant une city_key, donc joignables a open_data.

    Une zone sans city_key est une zone orpheline (reliquat de l'ancien seed) :
    on la saute explicitement plutot que de deviner une correspondance sur le
    nom d'affichage, qui etait justement la source de bugs avant la migration.
    """
    cur = conn.cursor(dictionary=True)
    cur.execute(
        "SELECT id, name, city_key, category "
        "FROM zones "
        "WHERE city_key IS NOT NULL AND city_key <> '' "
        "ORDER BY id ASC"
    )
    rows = cur.fetchall()
    cur.close()
    return rows


# --------------------------------------------------------------------------
# Chargement d'une ville
# --------------------------------------------------------------------------

def load_city_series(conn, city_key: str) -> pd.DataFrame:
    """Charge la serie horaire reelle complete d'une ville, triee par temps."""
    cur = conn.cursor(dictionary=True)
    cur.execute(
        "SELECT time, " + ", ".join(COLUMN_MAP.keys()) + " "
        "FROM open_data WHERE city = %s ORDER BY time ASC",
        (city_key,),
    )
    rows = cur.fetchall()
    cur.close()

    if not rows:
        return pd.DataFrame()

    df = pd.DataFrame(rows).rename(columns=COLUMN_MAP)
    df["ts"] = pd.to_datetime(df["time"])
    df = df.drop(columns=["time"]).sort_values("ts").reset_index(drop=True)

    for key in NUMERIC_KEYS:
        if key in df.columns:
            df[key] = pd.to_numeric(df[key], errors="coerce")

    # Section 3.4 : interpolation lineaire STRICTEMENT limitee aux vrais trous
    # internes de 3 heures maximum. limit_area="inside" empeche pandas de
    # prolonger la serie a ses extremites (ce serait de l'extrapolation).
    present = [k for k in NUMERIC_KEYS if k in df.columns]
    df[present] = df[present].interpolate(
        method="linear", limit=MAX_INTERP_GAP, limit_area="inside"
    )

    # L'AQI est la cible : une ligne sans cible est inutilisable.
    df = df.dropna(subset=["aqi"]).reset_index(drop=True)
    return df


# --------------------------------------------------------------------------
# Split chronologique
# --------------------------------------------------------------------------

def split_frame(df: pd.DataFrame, train_ratio: float = TRAIN_RATIO):
    """Split chronologique 80/20 (section 3.6). Aucun shuffle, jamais."""
    cut = int(len(df) * train_ratio)
    return df.iloc[:cut].copy(), df.iloc[cut:].copy()


def split_index(df: pd.DataFrame, train_ratio: float = TRAIN_RATIO) -> int:
    """Indice de la premiere ligne de test. Utilise par train_all.py."""
    return int(len(df) * train_ratio)


# --------------------------------------------------------------------------
# Point d'entree du pipeline
# --------------------------------------------------------------------------

def build_frames() -> Dict[int, pd.DataFrame]:
    """Retourne {zone_id: DataFrame reel trie par temps} (section 3.7).

    Contrat identique a l'ancienne version pour que train_all.py n'ait pas a
    changer d'interface, mais les DataFrames ne contiennent plus que du reel
    (~21 000 lignes par ville au lieu de 2016 lignes majoritairement fausses).
    """
    conn = connect()
    frames: Dict[int, pd.DataFrame] = {}
    try:
        for zone in load_zones(conn):
            df = load_city_series(conn, zone["city_key"])
            if df.empty:
                print(
                    "[data_loader] zone %s (%s) : AUCUNE ligne dans open_data "
                    "-> ignoree" % (zone["id"], zone["city_key"])
                )
                continue
            if len(df) < 200:
                print(
                    "[data_loader] zone %s (%s) : seulement %d lignes -> ignoree"
                    % (zone["id"], zone["city_key"], len(df))
                )
                continue
            cut = split_index(df)
            print(
                "[data_loader] zone %s %-14s %6d lignes reelles | "
                "train %d (%s -> %s) | test %d (%s -> %s)"
                % (
                    zone["id"], zone["city_key"], len(df),
                    cut, df["ts"].iloc[0].date(), df["ts"].iloc[cut - 1].date(),
                    len(df) - cut, df["ts"].iloc[cut].date(), df["ts"].iloc[-1].date(),
                )
            )
            frames[int(zone["id"])] = df
    finally:
        conn.close()

    if not frames:
        raise RuntimeError(
            "Aucune zone exploitable. Verifiez que migration_open_data.sql a ete "
            "execute (colonne zones.city_key remplie) et que le CSV a bien ete "
            "importe dans open_data."
        )
    return frames


def records_for_zone(df: pd.DataFrame) -> List[dict]:
    """DataFrame -> liste de dicts, format attendu par les trainers."""
    return df.to_dict("records")


if __name__ == "__main__":
    build_frames()