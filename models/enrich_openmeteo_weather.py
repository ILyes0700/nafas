"""Fetch and merge real Open-Meteo historical forecast weather features.

This script is intentionally explicit:
- it uses only the four active Nafass cities;
- it derives the date interval from open_data;
- it validates one-to-one city/time coverage before writing;
- it never fills a missing API value with zero or synthetic data;
- it writes only the new enrichment columns, never AQI or pollutants.

Run from the project root after applying plus24_enrichment.sql:
    python -m models.enrich_openmeteo_weather --apply

Without --apply it performs a dry-run and writes a manifest only.
"""
from __future__ import annotations

import argparse
import json
import sys
from datetime import datetime
from pathlib import Path
from typing import Any

import pandas as pd
import requests

try:
    from . import db_config, data_loader
except ImportError:  # pragma: no cover
    import db_config  # type: ignore
    import data_loader  # type: ignore

API_URL = "https://historical-forecast-api.open-meteo.com/v1/forecast"
HOURLY = (
    "dew_point_2m,cloud_cover_low,vapour_pressure_deficit,"
    "wind_gusts_10m,wind_speed_80m,wind_direction_80m"
)
DB_COLUMNS = {
    "dew_point_2m": "dew_point_2m",
    "cloud_cover_low": "cloud_cover_low",
    "vapour_pressure_deficit": "vapour_pressure_deficit",
    "wind_gusts_10m": "wind_gusts_10m",
    "wind_speed_80m": "wind_speed_80m",
    "wind_direction_80m": "wind_direction_80m",
}
REQUIRED_API_KEYS = tuple(DB_COLUMNS)
MANIFEST = Path(__file__).resolve().parent / "saved" / "weather_enrichment_manifest.json"


def parse_args():
    parser = argparse.ArgumentParser(description="Merge real Open-Meteo historical forecast weather features")
    parser.add_argument("--apply", action="store_true", help="write validated values to MySQL")
    parser.add_argument("--start", default=None, help="optional UTC start date YYYY-MM-DD")
    parser.add_argument("--end", default=None, help="optional UTC end date YYYY-MM-DD")
    parser.add_argument("--timeout", type=int, default=120)
    return parser.parse_args()


def ensure_columns(conn):
    definitions = {
        "dew_point_2m": "FLOAT NULL",
        "cloud_cover_low": "FLOAT NULL",
        "vapour_pressure_deficit": "FLOAT NULL",
        "wind_gusts_10m": "FLOAT NULL",
        "boundary_layer_height": "FLOAT NULL",
        "wind_speed_80m": "FLOAT NULL",
        "wind_direction_80m": "FLOAT NULL",
        "weather_enrichment_source": "VARCHAR(80) NULL",
        "weather_enrichment_updated_at": "DATETIME NULL",
        "weather_enrichment_timezone": "VARCHAR(64) NULL",
    }
    cur = conn.cursor()
    cur.execute("SHOW COLUMNS FROM open_data")
    existing = {str(row[0]) for row in cur.fetchall()}
    for column, definition in definitions.items():
        if column not in existing:
            cur.execute(f"ALTER TABLE `open_data` ADD COLUMN `{column}` {definition}")
            print(f"[schema] added {column}")
    conn.commit()
    cur.close()


def get_scope(conn, start_override: str | None, end_override: str | None):
    marks = ",".join(["%s"] * len(data_loader.ALLOWED_CITY_KEYS))
    cur = conn.cursor(dictionary=True)
    cur.execute(
        "SELECT id, city_key, lat, lng FROM zones "
        f"WHERE city_key IN ({marks}) ORDER BY id ASC",
        data_loader.ALLOWED_CITY_KEYS,
    )
    zones = cur.fetchall()
    if len(zones) != len(data_loader.ALLOWED_CITY_KEYS):
        raise RuntimeError(f"Expected {len(data_loader.ALLOWED_CITY_KEYS)} active zones, got {len(zones)}")
    for zone in zones:
        if zone.get("lat") is None or zone.get("lng") is None:
            raise RuntimeError(f"Missing coordinates for {zone['city_key']}")

    cur.execute(
        "SELECT city, MIN(time) AS start_time, MAX(time) AS end_time, COUNT(*) AS n "
        f"FROM open_data WHERE city IN ({marks}) GROUP BY city",
        data_loader.ALLOWED_CITY_KEYS,
    )
    ranges = {row["city"]: row for row in cur.fetchall()}
    cur.close()
    if set(ranges) != set(data_loader.ALLOWED_CITY_KEYS):
        raise RuntimeError("open_data does not contain all four active cities")

    start = start_override or min(str(row["start_time"])[:10] for row in ranges.values())
    end = end_override or max(str(row["end_time"])[:10] for row in ranges.values())
    return zones, ranges, start, end


def fetch_zone(zone: dict[str, Any], start: str, end: str, timeout: int) -> pd.DataFrame:
    params = {
        "latitude": float(zone["lat"]),
        "longitude": float(zone["lng"]),
        "start_date": start,
        "end_date": end,
        "hourly": HOURLY,
        "timezone": "UTC",
    }
    response = requests.get(API_URL, params=params, timeout=timeout)
    response.raise_for_status()
    payload = response.json()
    if payload.get("error"):
        raise RuntimeError(f"Open-Meteo error for {zone['city_key']}: {payload.get('reason')}")
    hourly = payload.get("hourly") or {}
    missing = [key for key in ("time", *REQUIRED_API_KEYS) if key not in hourly]
    if missing:
        raise RuntimeError(f"Open-Meteo response missing {missing} for {zone['city_key']}")
    frame = pd.DataFrame({key: hourly[key] for key in ("time", *REQUIRED_API_KEYS)})
    frame["time"] = pd.to_datetime(frame["time"], utc=True).dt.tz_localize(None)
    for key in REQUIRED_API_KEYS:
        frame[key] = pd.to_numeric(frame[key], errors="coerce")
    if frame["time"].duplicated().any():
        raise RuntimeError(f"Duplicate API timestamps for {zone['city_key']}")
    nulls = frame[list(REQUIRED_API_KEYS)].isna().sum()
    if int(nulls.sum()) != 0:
        raise RuntimeError(
            f"Missing real weather values for {zone['city_key']}: {nulls[nulls > 0].to_dict()}"
        )
    frame["city"] = zone["city_key"]
    return frame


def validate_against_db(conn, frame: pd.DataFrame, city: str):
    cur = conn.cursor()
    cur.execute("SELECT time FROM open_data WHERE city=%s ORDER BY time ASC", (city,))
    db_times = pd.to_datetime([row[0] for row in cur.fetchall()])
    cur.close()
    api_times = frame["time"]
    if len(db_times) != len(api_times):
        raise RuntimeError(f"{city}: DB rows={len(db_times)} API rows={len(api_times)}")
    if not db_times.equals(pd.DatetimeIndex(api_times)):
        raise RuntimeError(f"{city}: API timestamps do not exactly match open_data")


def write_frame(conn, frame: pd.DataFrame):
    columns = list(DB_COLUMNS.values())
    sql = (
        "UPDATE open_data SET "
        + ", ".join(f"`{col}`=%s" for col in columns)
        + ", `weather_enrichment_source`=%s, `weather_enrichment_updated_at`=NOW(), "
          "`weather_enrichment_timezone`=%s WHERE city=%s AND time=%s"
    )
    rows = []
    for row in frame.itertuples(index=False):
        values = [getattr(row, key) for key in REQUIRED_API_KEYS]
        rows.append(tuple(values) + ("open-meteo-historical-forecast", "UTC", row.city, row.time.to_pydatetime()))
    cur = conn.cursor()
    cur.executemany(sql, rows)
    print(f"[sql] {frame['city'].iloc[0]} matched rows={cur.rowcount}")
    cur.close()


def main():
    args = parse_args()
    conn = db_config.get_connection()
    try:
        if args.apply:
            ensure_columns(conn)
        zones, ranges, start, end = get_scope(conn, args.start, args.end)
        print(f"[scope] cities={','.join(data_loader.ALLOWED_CITY_KEYS)} start={start} end={end}")
        manifest = {
            "source": API_URL,
            "hourly": HOURLY,
            "timezone": "UTC",
            "active_cities": list(data_loader.ALLOWED_CITY_KEYS),
            "start_date": start,
            "end_date": end,
            "mode": "apply" if args.apply else "dry-run",
            "zones": [],
            "generated_at": datetime.utcnow().isoformat(timespec="seconds") + "Z",
        }
        for zone in zones:
            frame = fetch_zone(zone, start, end, args.timeout)
            validate_against_db(conn, frame, zone["city_key"])
            manifest["zones"].append({
                "city": zone["city_key"],
                "rows": int(len(frame)),
                "nulls": int(frame[list(REQUIRED_API_KEYS)].isna().sum().sum()),
                "min_time": str(frame["time"].iloc[0]),
                "max_time": str(frame["time"].iloc[-1]),
            })
            print(f"[validated] {zone['city_key']}: {len(frame)} exact UTC rows, no nulls")
            if args.apply:
                write_frame(conn, frame)
                conn.commit()
                print(f"[written] {zone['city_key']}: enrichment columns updated")
        MANIFEST.parent.mkdir(parents=True, exist_ok=True)
        MANIFEST.write_text(json.dumps(manifest, indent=2, ensure_ascii=False), encoding="utf-8")
        print(f"[manifest] {MANIFEST}")
        if not args.apply:
            print("[dry-run] no database values were changed; rerun with --apply after reviewing the manifest")
    finally:
        conn.close()


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(f"[enrichment] ERROR: {exc}", file=sys.stderr)
        raise
