"""PART 19 - Data simulator. Generates 8760 rows/city (1 year hourly) as a
fallback when the live APIs are unavailable. Applies each city's pollution
factor and Gabès-specific seasonal / industrial patterns.
"""
from __future__ import annotations
import csv, math, random, datetime as dt

CITIES = {
    "centre_ville": 1.10, "chatt_salem": 1.15, "ghannouche": 1.90,
    "metouia": 0.85, "bouchamma": 1.35, "chenini": 1.20, "el_bled": 1.05,
}


def season_of(month):
    return 1 if month in (6, 7, 8, 9) else 3 if month in (12, 1, 2) else 0


def simulate_city(city_id, factor, start=None, hours=8760, seed=42):
    random.seed(hash(city_id) ^ seed)
    start = start or (dt.datetime.now() - dt.timedelta(hours=hours))
    rows = []
    for i in range(hours):
        ts = start + dt.timedelta(hours=i)
        base = 70 * factor
        # seasonal
        s = season_of(ts.month)
        if s == 1:  # summer
            base *= 1.40
        elif s == 3:  # winter
            base *= 0.80
        # industrial peaks 6-8 & 14-16 on weekdays
        if ts.weekday() < 5 and (6 <= ts.hour <= 8 or 14 <= ts.hour <= 16):
            base += 35
        # night / weekend / friday prayer
        if 23 <= ts.hour or ts.hour <= 5:
            base *= 0.80
        if ts.weekday() >= 5:
            base *= 0.88
        if ts.weekday() == 4 and 12 <= ts.hour <= 14:
            base *= 0.75
        # random phosphate events ~2x/month
        if random.random() < 0.0028:
            base += random.uniform(80, 220)
        aqi = max(15, base + random.gauss(0, 8))
        rows.append({
            "city_id": city_id, "timestamp": ts.strftime("%Y-%m-%d %H:%M:%S"),
            "final_aqi": round(aqi, 1),
            "final_pm25": round(aqi * 0.45 + random.gauss(0, 4), 1),
            "final_pm10": round(aqi * 0.75 + random.gauss(0, 6), 1),
            "final_so2": round(aqi * 0.55 * factor + random.gauss(0, 5), 1),
            "final_no2": round(aqi * 0.30 + random.gauss(0, 3), 1),
            "final_temperature": round(20 + 12 * math.sin(i / 1460.0) + random.gauss(0, 2), 1),
            "final_humidity": round(min(95, max(20, 55 + random.gauss(0, 10))), 1),
            "final_wind_speed": round(max(0, 12 + random.gauss(0, 6)), 1),
            "season": s, "source": "simulated",
        })
    return rows


def simulate_all(out_csv="simulated_readings.csv"):
    all_rows = []
    for cid, f in CITIES.items():
        all_rows.extend(simulate_city(cid, f))
    with open(out_csv, "w", newline="") as fh:
        w = csv.DictWriter(fh, fieldnames=list(all_rows[0].keys()))
        w.writeheader(); w.writerows(all_rows)
    print(f"Wrote {len(all_rows)} rows to {out_csv}")
    return all_rows


if __name__ == "__main__":
    simulate_all()
