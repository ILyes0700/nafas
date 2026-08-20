import pandas as pd
import numpy as np
from test_support import data_csv
from models import train_all, ml_models, deep_models, bilstm_autoencoder, feature_engineering

assert len(feature_engineering.FEATURE_NAMES) == 54
assert train_all.FEATURE_NAMES == feature_engineering.FEATURE_NAMES
assert ml_models.FEATURE_NAMES == feature_engineering.FEATURE_NAMES
assert deep_models.FEATURE_NAMES == feature_engineering.FEATURE_NAMES
assert bilstm_autoencoder.FEATURES == feature_engineering.FEATURE_NAMES

df = pd.read_csv(data_csv(), parse_dates=['time'])
df = df[df.city == 'Gabes_ville'].head(300).copy()
rename = {
 'us_aqi':'aqi','pm2_5':'pm25','nitrogen_dioxide':'no2','sulphur_dioxide':'so2',
 'ozone':'o3','carbon_monoxide':'co','temperature_2m':'temperature',
 'relative_humidity_2m':'humidity','wind_speed_10m':'wind_speed',
 'wind_direction_10m':'wind_direction','surface_pressure':'pressure'
}
records = df.rename(columns=rename).to_dict('records')
for r in records: r['ts'] = r['time']
X, y = train_all.build_xy(records, 24)
D = deep_models.build_feature_matrix(records)
assert X.shape[1] == D.shape[1] == 54
assert np.isfinite(X).all() and np.isfinite(D).all()
print('FEATURE_NAMES', len(feature_engineering.FEATURE_NAMES))
print('ML_X', X.shape, 'DL_matrix', D.shape, 'y', y.shape)
print('PASS schema alignment')
