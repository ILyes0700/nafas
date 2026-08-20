import numpy as np
import pandas as pd
from test_support import data_csv
from models import train_all

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
step = 24
X, y = train_all.build_xy(records, step)
ntr, nval_end, nall = train_all._split_sample_bounds(records, step, len(X))
start = 168
targets = np.arange(start, len(records) - step, dtype=int) + step
targets = targets[:len(X)]
print('samples', len(X), 'train', ntr, 'validation', nval_end-ntr, 'test', nall-nval_end)
print('target first', int(targets[0]), 'expected', start+step)
print('target boundaries', int(targets[ntr-1]), int(targets[nval_end-1]), int(targets[-1]))
assert int(y[0]) == int(records[start+step]['aqi'])
assert int(targets[ntr-1]) < int(len(records)*0.70)
assert int(targets[ntr]) >= int(len(records)*0.70)
assert int(targets[nval_end-1]) < int(len(records)*0.80)
assert int(targets[nval_end]) >= int(len(records)*0.80)
print('PASS target t+24 and chronological 70/10/20')
