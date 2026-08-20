import numpy as np
import pandas as pd
from test_support import data_csv
from models import feature_engineering

CSV = data_csv()
df = pd.read_csv(CSV, parse_dates=['time'])
df = df[df['city'] == 'Gabes_ville'].sort_values('time').head(500).reset_index(drop=True)
rename = {
    'us_aqi':'aqi','pm2_5':'pm25','nitrogen_dioxide':'no2','sulphur_dioxide':'so2',
    'ozone':'o3','carbon_monoxide':'co','temperature_2m':'temperature',
    'relative_humidity_2m':'humidity','wind_speed_10m':'wind_speed',
    'wind_direction_10m':'wind_direction','surface_pressure':'pressure',
}
records = df.rename(columns=rename).to_dict('records')
for r in records:
    r['ts'] = r['time']
X, y = feature_engineering.build_xy(records, 24)
print('shape', X.shape, 'target_shape', y.shape)
print('feature_count', len(feature_engineering.FEATURE_NAMES))
print('unique_names', len(set(feature_engineering.FEATURE_NAMES)))
print('finite', bool(np.isfinite(X).all()), bool(np.isfinite(y).all()))
# Causality check: changing all values after origin must not change X row at origin.
origin = 300
base = feature_engineering.build_feature_matrix(records)[origin].copy()
mutated = [dict(r) for r in records]
for j in range(origin + 1, len(mutated)):
    mutated[j]['aqi'] = 9999.0
    mutated[j]['pm25'] = 9999.0
    mutated[j]['temperature'] = -9999.0
changed = feature_engineering.build_feature_matrix(mutated)[origin]
print('future_mutation_difference_at_origin', float(np.max(np.abs(base - changed))))
assert X.shape[1] == 54
assert len(set(feature_engineering.FEATURE_NAMES)) == 54
assert np.isfinite(X).all() and np.isfinite(y).all()
assert np.allclose(base, changed)
print('PASS causal feature test')
