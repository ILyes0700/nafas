"""Shared causal feature engineering for the Nafass +24h pipeline.

All features are computed from records at or before the forecast origin t.
No target value and no observation after t is read here.

The enriched schema is intentionally compact enough for the 168-hour DL window.
It adds pollutant persistence/trend, rain accumulation, circular wind components,
and real Open-Meteo historical weather variables when the enrichment step has
been completed.
"""
from __future__ import annotations

import math
from typing import Iterable, Sequence

import numpy as np

try:
    from . import fuzzy_type2
except Exception:
    try:
        import fuzzy_type2  # type: ignore
    except Exception:
        fuzzy_type2 = None

# Keep the original 35 features, then add 19 causal features.
FEATURE_NAMES = (
    ["aqi_current"]
    + [f"aqi_lag_{k}" for k in (1, 2, 3, 4, 5, 6, 7, 24, 168)]
    + ["aqi_delta_1h", "aqi_delta_6h", "aqi_mean_6h", "aqi_mean_24h", "aqi_std_24h"]
    + ["fuzzy_score_type2", "uncertainty_lower", "uncertainty_upper"]
    + ["pm25", "pm10", "so2", "no2", "o3", "co", "dust"]
    + ["temperature", "humidity", "wind_speed", "wind_direction",
       "pressure", "precipitation", "cloud_cover"]
    + ["hour_of_day", "is_weekend", "season"]
    + [
        "pm25_lag_24", "pm10_lag_24", "dust_lag_24",
        "pm25_mean_24h", "pm25_max_24h", "pm25_slope_24h",
        "pm25_pm10_ratio",
        "wind_u", "wind_v", "wind_calm",
        "precip_sum_24h", "hours_since_rain",
        "hour_sin", "hour_cos",
        "dew_point", "vapour_pressure_deficit", "wind_gusts_10m",
        "cloud_cover_low", "wind_speed_80m",
    ]
)
assert len(FEATURE_NAMES) == 54, f"Expected 54 features, got {len(FEATURE_NAMES)}"

RAW_KEYS = (
    "aqi", "pm25", "pm10", "so2", "no2", "o3", "co", "dust",
    "temperature", "humidity", "wind_speed", "wind_direction",
    "pressure", "precipitation", "cloud_cover", "dew_point", "cloud_cover_low",
    "vapour_pressure_deficit", "wind_gusts_10m", "wind_speed_80m",
)


def _value(record: dict, key: str) -> float:
    try:
        value = record.get(key)
        return float(value) if value is not None and np.isfinite(float(value)) else 0.0
    except (TypeError, ValueError):
        return 0.0


def _timestamp(record: dict, index: int):
    ts = record.get("ts") or record.get("timestamp") or record.get("time")
    try:
        if not hasattr(ts, "hour"):
            import pandas as pd
            ts = pd.to_datetime(ts)
        return ts
    except Exception:
        return None


def time_parts(record: dict, index: int, offset_hours: int = 0):
    """Return target-calendar parts; target time is known without target AQI."""
    ts = _timestamp(record, index)
    if ts is not None:
        target = ts + __import__("datetime").timedelta(hours=int(offset_hours))
        return float(target.hour), float(target.weekday() >= 5), float((target.month % 12) // 3)
    target_i = index + int(offset_hours)
    return float(target_i % 24), float((target_i // 24) % 7 >= 5), float((target_i // (24 * 90)) % 4)


def _slope(values: np.ndarray) -> float:
    if len(values) < 2:
        return 0.0
    x = np.arange(len(values), dtype=float)
    xc = x - x.mean()
    yc = values - values.mean()
    denom = float(np.dot(xc, xc))
    return float(np.dot(xc, yc) / denom) if denom > 0 else 0.0


def _rolling(values: np.ndarray, i: int, width: int) -> np.ndarray:
    return values[max(0, i - width + 1): i + 1]


def _hours_since_rain(precip: np.ndarray, i: int, cap: int = 168) -> float:
    """Hours since the last positive precipitation, using only <= t."""
    for h in range(0, min(i, cap) + 1):
        if precip[i - h] > 0.0:
            return float(h)
    return float(cap)


def _safe_ratio(a: float, b: float) -> float:
    return float(a / (b + 1e-6))


def feature_row(records: Sequence[dict], i: int, horizon_step: int = 0,
                matrix: np.ndarray | None = None) -> list[float]:
    """Build one row from observations <= t and calendar at target t+h."""
    base = build_feature_matrix(records) if matrix is None else matrix
    row = np.asarray(base[i], dtype=np.float32).copy()
    hour, weekend, season = time_parts(records[i], i, horizon_step)
    # indices 32:35 are the original target-calendar features and remain stable
    row[32:35] = (hour, weekend, season)
    row[47:49] = (
        math.sin(2.0 * math.pi * hour / 24.0),
        math.cos(2.0 * math.pi * hour / 24.0),
    )
    return row.astype(float).tolist()


def build_feature_matrix(records: Sequence[dict], _single_row_index: int | None = None) -> np.ndarray:
    """Return an [n_records, 54] causal feature matrix."""
    n = len(records)
    raw = {key: np.asarray([_value(r, key) for r in records], dtype=np.float64)
           for key in RAW_KEYS}
    aqi = raw["aqi"]
    pm25 = raw["pm25"]
    pm10 = raw["pm10"]
    dust = raw["dust"]
    precipitation = raw["precipitation"]
    out: list[list[float]] = []

    def lag(values: np.ndarray, i: int, hours: int) -> float:
        return float(values[max(0, i - hours)])

    for i, record in enumerate(records):
        aqi_lag = {k: lag(aqi, i, k) for k in (1, 2, 3, 4, 5, 6, 7, 24, 168)}
        hist6 = _rolling(aqi, i, 6)
        hist24 = _rolling(aqi, i, 24)
        if fuzzy_type2 is not None:
            try:
                fz = fuzzy_type2.assess(min(100.0, aqi[i] / 5.0))
                fuzzy_values = [float(fz.get(k, 0.0)) for k in
                                ("fuzzy_score_type2", "uncertainty_lower", "uncertainty_upper")]
            except Exception:
                fuzzy_values = [0.0, 0.0, 0.0]
        else:
            fuzzy_values = [0.0, 0.0, 0.0]

        hour, weekend, season = time_parts(record, i, 0)
        direction_rad = math.radians(raw["wind_direction"][i] % 360.0)
        wind_speed = raw["wind_speed"][i]
        rain24 = _rolling(precipitation, i, 24)
        pm_hist24 = _rolling(pm25, i, 24)

        row = [
            float(aqi[i]),
            *[aqi_lag[k] for k in (1, 2, 3, 4, 5, 6, 7, 24, 168)],
            float(aqi[i] - aqi_lag[1]), float(aqi[i] - aqi_lag[6]),
            float(hist6.mean()), float(hist24.mean()),
            float(hist24.std()) if len(hist24) > 1 else 0.0,
            *fuzzy_values,
            *[float(raw[k][i]) for k in ("pm25", "pm10", "so2", "no2", "o3", "co", "dust")],
            *[float(raw[k][i]) for k in
              ("temperature", "humidity", "wind_speed", "wind_direction",
               "pressure", "precipitation", "cloud_cover")],
            hour, weekend, season,
            lag(pm25, i, 24), lag(pm10, i, 24), lag(dust, i, 24),
            float(pm_hist24.mean()), float(pm_hist24.max()), _slope(pm_hist24),
            _safe_ratio(pm25[i], pm10[i]),
            float(wind_speed * math.cos(direction_rad)),
            float(wind_speed * math.sin(direction_rad)),
            float(wind_speed <= 2.0),
            float(rain24.sum()), _hours_since_rain(precipitation, i),
            float(math.sin(2.0 * math.pi * hour / 24.0)),
            float(math.cos(2.0 * math.pi * hour / 24.0)),
            float(raw["dew_point"][i]),
            float(raw["vapour_pressure_deficit"][i]),
            float(raw["wind_gusts_10m"][i]),
            float(raw["cloud_cover_low"][i]),
            float(raw["wind_speed_80m"][i]),
        ]
        if len(row) != len(FEATURE_NAMES):
            raise RuntimeError(f"Feature row has {len(row)} values, expected {len(FEATURE_NAMES)}")
        out.append(row)
    return np.asarray(out, dtype=np.float32)


def build_xy(records: Sequence[dict], horizon_step: int,
             matrix: np.ndarray | None = None):
    """Build tabular X and y with target exactly at t+horizon."""
    matrix = build_feature_matrix(records) if matrix is None else np.asarray(matrix, dtype=np.float32)
    aqi = np.asarray([_value(r, "aqi") for r in records], dtype=np.float32)
    n = len(records)
    start = 168 if n >= 176 else 8
    X, y = [], []
    for i in range(start, n - int(horizon_step)):
        row = np.asarray(matrix[i], dtype=np.float32).copy()
        hour, weekend, season = time_parts(records[i], i, int(horizon_step))
        row[32:35] = (hour, weekend, season)
        row[47:49] = (
            math.sin(2.0 * math.pi * hour / 24.0),
            math.cos(2.0 * math.pi * hour / 24.0),
        )
        X.append(row)
        y.append(aqi[i + int(horizon_step)])
    return np.asarray(X, dtype=np.float32), np.asarray(y, dtype=np.float32)
