"""Real Open-Meteo/CAMS loader for the Nafass ML pipeline.

The loader never generates rows or values. It reads open_data, keeps the four
active cities, performs only short internal-gap interpolation (<=3 hours), and
returns a strict chronological 70/10/20 split contract.

Optional enrichment columns are read when the SQL migration and the real
Open-Meteo enrichment script have been executed. Missing optional columns are
reported explicitly; they are not silently confused with measurements.
"""
from __future__ import annotations

import os
from typing import Dict, List

import pandas as pd

try:
    from . import db_config
except ImportError:  # pragma: no cover
    import db_config  # type: ignore

TRAIN_RATIO = 0.70
VALIDATION_RATIO = 0.10
TEST_RATIO = 0.20

ALLOWED_CITY_KEYS = (
    "Gabes_ville",
    "Ghannouche",
    "Chott_Salem",
    "Teboulbou",
)

MAX_INTERP_GAP = 3

# open_data columns -> internal feature keys
BASE_COLUMN_MAP = {
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

# These columns are populated by the real Open-Meteo enrichment script.
OPTIONAL_COLUMN_MAP = {
    "dew_point_2m": "dew_point",
    "cloud_cover_low": "cloud_cover_low",
    "vapour_pressure_deficit": "vapour_pressure_deficit",
    "wind_gusts_10m": "wind_gusts_10m",
    "boundary_layer_height": "boundary_layer_height",
    "wind_speed_80m": "wind_speed_80m",
    "wind_direction_80m": "wind_direction_80m",
}
# These are the columns consumed by the v6 feature schema. PBLH is kept as a
# nullable future column because the tested historical source returned a large
# contiguous missing block; it is not silently imputed or used by v6.
REQUIRED_ENRICHMENT_COLUMNS = (
    "dew_point_2m", "cloud_cover_low", "vapour_pressure_deficit",
    "wind_gusts_10m", "wind_speed_80m", "wind_direction_80m",
)

COLUMN_MAP = {**BASE_COLUMN_MAP, **OPTIONAL_COLUMN_MAP}
NUMERIC_KEYS = list(COLUMN_MAP.values())


def connect():
    return db_config.get_connection()


def _open_data_columns(conn) -> set[str]:
    cur = conn.cursor()
    cur.execute("SHOW COLUMNS FROM open_data")
    cols = {str(row[0]) for row in cur.fetchall()}
    cur.close()
    return cols


def load_zones(conn) -> List[dict]:
    cur = conn.cursor(dictionary=True)
    marks = ",".join(["%s"] * len(ALLOWED_CITY_KEYS))
    cur.execute(
        "SELECT id, name, city_key, category, lat, lng FROM zones "
        "WHERE city_key IS NOT NULL AND city_key <> '' "
        f"AND city_key IN ({marks}) ORDER BY id ASC",
        ALLOWED_CITY_KEYS,
    )
    rows = cur.fetchall()
    cur.close()
    return rows


def load_city_series(conn, city_key: str) -> pd.DataFrame:
    available = _open_data_columns(conn)
    required = {"city", "time", *BASE_COLUMN_MAP.keys()}
    missing_required = sorted(required - available)
    if missing_required:
        raise RuntimeError(
            "open_data columns are missing: " + ", ".join(missing_required)
        )
    require_enrichment = os.environ.get("NAFAS_REQUIRE_ENRICHMENT", "1").lower() not in ("0", "false", "no")
    missing_enrichment = sorted(set(REQUIRED_ENRICHMENT_COLUMNS) - available)
    if require_enrichment and missing_enrichment:
        raise RuntimeError(
            "Enriched +24h columns are missing: " + ", ".join(missing_enrichment)
            + ". Run source/backend/sql/plus24_enrichment.sql and "
              "python -m models.enrich_openmeteo_weather --apply, or set "
              "NAFAS_REQUIRE_ENRICHMENT=0 only for the old baseline."
        )

    selected = ["time"] + [name for name in COLUMN_MAP if name in available]
    query = (
        "SELECT " + ", ".join(f"`{name}`" for name in selected) +
        " FROM open_data WHERE city = %s ORDER BY time ASC"
    )
    cur = conn.cursor(dictionary=True)
    cur.execute(query, (city_key,))
    rows = cur.fetchall()
    cur.close()
    if not rows:
        return pd.DataFrame()

    df = pd.DataFrame(rows).rename(columns={k: v for k, v in COLUMN_MAP.items() if k in selected})
    df["ts"] = pd.to_datetime(df["time"], errors="coerce")
    df = df.drop(columns=["time"]).sort_values("ts").reset_index(drop=True)

    present_numeric = [key for key in NUMERIC_KEYS if key in df.columns]
    for key in present_numeric:
        df[key] = pd.to_numeric(df[key], errors="coerce")
    if present_numeric:
        df[present_numeric] = df[present_numeric].interpolate(
            method="linear", limit=MAX_INTERP_GAP, limit_area="inside"
        )
    df = df.dropna(subset=["aqi"]).reset_index(drop=True)
    if require_enrichment:
        required_internal = [OPTIONAL_COLUMN_MAP[k] for k in REQUIRED_ENRICHMENT_COLUMNS]
        residual = {key: int(df[key].isna().sum()) for key in required_internal if key in df.columns and df[key].isna().any()}
        if residual:
            raise RuntimeError(
                f"Real enrichment has missing values for {city_key}: {residual}. "
                "Do not fill them with zero; rerun the real Open-Meteo enrichment."
            )

    optional_present = [k for k in OPTIONAL_COLUMN_MAP.values() if k in df.columns]
    print(
        f"[data_loader] {city_key}: {len(df)} real rows; "
        f"optional enrichment={len(optional_present)}/{len(OPTIONAL_COLUMN_MAP)} "
        f"({', '.join(optional_present) if optional_present else 'none'})"
    )
    return df


def split_frame(df: pd.DataFrame, train_ratio: float = TRAIN_RATIO,
                validation_ratio: float = VALIDATION_RATIO):
    n = len(df)
    train_end = int(n * train_ratio)
    validation_end = train_end + int(n * validation_ratio)
    return (
        df.iloc[:train_end].copy(),
        df.iloc[train_end:validation_end].copy(),
        df.iloc[validation_end:].copy(),
    )


def split_index(df: pd.DataFrame, train_ratio: float = TRAIN_RATIO,
                validation_ratio: float = VALIDATION_RATIO):
    n = len(df)
    train_end = int(n * train_ratio)
    validation_end = train_end + int(n * validation_ratio)
    return train_end, validation_end


def build_frames() -> Dict[int, pd.DataFrame]:
    conn = connect()
    frames: Dict[int, pd.DataFrame] = {}
    try:
        for zone in load_zones(conn):
            df = load_city_series(conn, zone["city_key"])
            if df.empty:
                print(f"[data_loader] zone {zone['id']} ({zone['city_key']}): no rows -> skipped")
                continue
            if len(df) < 200:
                print(f"[data_loader] zone {zone['id']} ({zone['city_key']}): only {len(df)} rows -> skipped")
                continue
            train_end, validation_end = split_index(df)
            print(
                "[data_loader] zone %s %-14s %6d real rows | train %d (%s -> %s) | "
                "validation %d (%s -> %s) | test %d (%s -> %s)"
                % (
                    zone["id"], zone["city_key"], len(df),
                    train_end, df["ts"].iloc[0].date(), df["ts"].iloc[train_end - 1].date(),
                    validation_end - train_end, df["ts"].iloc[train_end].date(), df["ts"].iloc[validation_end - 1].date(),
                    len(df) - validation_end, df["ts"].iloc[validation_end].date(), df["ts"].iloc[-1].date(),
                )
            )
            frames[int(zone["id"])] = df
    finally:
        conn.close()

    if not frames:
        raise RuntimeError(
            "No usable zones. Check WAMP/MySQL, zones.city_key, and the open_data import."
        )
    return frames


def records_for_zone(df: pd.DataFrame) -> List[dict]:
    return df.to_dict("records")


if __name__ == "__main__":
    build_frames()
