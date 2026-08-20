import json
import numpy as np
import pandas as pd
from test_support import data_csv, enriched_sample_json
from models import feature_engineering

aqi = pd.read_csv(data_csv(), parse_dates=['time'])
aqi = aqi[aqi.city == 'Gabes_ville'].sort_values('time').head(72).reset_index(drop=True)
weather = json.loads(enriched_sample_json().read_text())['hourly']
w = pd.DataFrame(weather)
w['time'] = pd.to_datetime(w['time'])
joined = aqi.merge(w, on='time', how='inner', validate='one_to_one')
assert len(joined) == 72
rename = {
    'us_aqi':'aqi','pm2_5':'pm25','nitrogen_dioxide':'no2','sulphur_dioxide':'so2',
    'ozone':'o3','carbon_monoxide':'co','temperature_2m':'temperature',
    'relative_humidity_2m':'humidity','wind_speed_10m':'wind_speed',
    'wind_direction_10m':'wind_direction','surface_pressure':'pressure',
    'dew_point_2m':'dew_point',
}
records = joined.rename(columns=rename).to_dict('records')
for r in records: r['ts'] = r['time']
mat = feature_engineering.build_feature_matrix(records)
print('shape', mat.shape)
for name in ['dew_point','vapour_pressure_deficit','wind_gusts_10m','cloud_cover_low','wind_speed_80m']:
    idx = feature_engineering.FEATURE_NAMES.index(name)
    vals = mat[:, idx]
    print(name, 'min=', float(vals.min()), 'max=', float(vals.max()))
assert mat.shape == (72, 54)
for name in ['dew_point','vapour_pressure_deficit','wind_gusts_10m','wind_speed_80m']:
    assert np.any(mat[:, feature_engineering.FEATURE_NAMES.index(name)] != 0)
# cloud_cover_low is allowed to be genuinely zero for this short sample.
print('PASS enriched feature join')
