import time
import pandas as pd
import numpy as np
from test_support import data_csv
from models import feature_engineering

path = data_csv()
df = pd.read_csv(path, parse_dates=['time'])
df = df[df.city == 'Gabes_ville'].sort_values('time').reset_index(drop=True)
rename = {
 'us_aqi':'aqi','pm2_5':'pm25','nitrogen_dioxide':'no2','sulphur_dioxide':'so2',
 'ozone':'o3','carbon_monoxide':'co','temperature_2m':'temperature',
 'relative_humidity_2m':'humidity','wind_speed_10m':'wind_speed',
 'wind_direction_10m':'wind_direction','surface_pressure':'pressure'
}
records = df.rename(columns=rename).to_dict('records')
for r in records: r['ts'] = r['time']
t0 = time.perf_counter()
X, y = feature_engineering.build_xy(records, 24)
secs = time.perf_counter() - t0
print('records', len(records), 'X', X.shape, 'y', y.shape, 'seconds', round(secs, 3))
print('finite', bool(np.isfinite(X).all()), bool(np.isfinite(y).all()))
assert X.shape == (len(records) - 168 - 24, 54)
assert np.isfinite(X).all() and np.isfinite(y).all()
print('PASS full feature builder')
