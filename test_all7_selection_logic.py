from test_support import SOURCE_ROOT
from models.selection_logic import select_deployment_model

validation = {
    'Random Forest': 8.0,
    'XGBoost + Fuzzy': 7.0,
    'LSTM': 6.5,
    'BiLSTM Simple': 6.2,
    'BiLSTM+MultiHead Attn': 5.8,
    'BiLSTM+AE': 5.5,
    'CNN+AE': 5.9,
}
test = {
    'Random Forest': 30.0,
    'XGBoost + Fuzzy': 29.0,
    'LSTM': 28.0,
    'BiLSTM Simple': 27.0,
    'BiLSTM+MultiHead Attn': 26.0,
    'BiLSTM+AE': 40.0,
    'CNN+AE': 10.0,
}
latest = {name: 80.0 for name in validation}
decision = select_deployment_model(validation, test, latest)
print(decision)
assert decision['model'] == 'BiLSTM+AE'
assert decision['test_rmse'] == 40.0
assert 'CNN+AE' in decision['eligible_models']
print('PASS selection uses Validation only, Test is report only')
