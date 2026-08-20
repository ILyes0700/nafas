from test_support import SOURCE_ROOT
from models.enrich_openmeteo_weather import fetch_zone, REQUIRED_API_KEYS

zones = [
    {'city_key': 'Gabes_ville', 'lat': 33.889334848, 'lng': 10.096435713},
    {'city_key': 'Ghannouche', 'lat': 33.943871971, 'lng': 10.067081982},
    {'city_key': 'Chott_Salem', 'lat': 33.901897588, 'lng': 10.100104537},
    {'city_key': 'Teboulbou', 'lat': 33.840965860, 'lng': 10.130874866},
]
for zone in zones:
    frame = fetch_zone(zone, '2024-01-01', '2024-01-03', 60)
    assert len(frame) == 72
    assert int(frame[list(REQUIRED_API_KEYS)].isna().sum().sum()) == 0
    print(zone['city_key'], len(frame), 'rows', 'non-null', list(REQUIRED_API_KEYS))
print('PASS four-zone Open-Meteo enrichment API test')
