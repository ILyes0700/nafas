"""PART 6 - Health Impact Index specific to Gabès (chronic SO2 exposure)."""
from __future__ import annotations


def health_impact_score(aqi, pm25, so2, vuln_pop_pct, exposure_hours):
    score = (
        (aqi / 500) * 0.25 + (pm25 / 75) * 0.25 + (so2 / 100) * 0.20 +
        (vuln_pop_pct / 100) * 0.15 + (exposure_hours / 24) * 0.15) * 100
    return max(0.0, min(100.0, score))


def risk_level(score):
    if score <= 25:
        return "Négligeable", "Activités normales"
    if score <= 50:
        return "Faible", "Surveiller les symptômes"
    if score <= 75:
        return "Modéré", "Limiter activités extérieures"
    if score <= 90:
        return "Élevé", "Rester à l'intérieur, masque FFP2"
    return "Critique", "URGENCE — évacuation zones industrielles"


def assess(aqi, pm25, so2, vuln_pop_pct=25, exposure_hours=12):
    s = health_impact_score(aqi, pm25, so2, vuln_pop_pct, exposure_hours)
    lvl, reco = risk_level(s)
    return {"health_impact_score": round(s, 1), "health_risk_level": lvl,
            "recommendations": reco}


if __name__ == "__main__":
    print(assess(140, 62, 118, 30, 16))
