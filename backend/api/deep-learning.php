<?php
/**
 * Deep Learning module endpoint — RÉEL uniquement (aucune démo).
 *
 * Toutes les données proviennent de la base de données :
 *   - Tableau comparatif           <- model_performance (modèles *LSTM*)
 *   - Prédictions par zone         <- dl_artifacts.predictions
 *   - Série réel vs prédit         <- dl_artifacts.series
 *   - Carte d'attention (SEQ×SEQ)  <- dl_artifacts.attention  (VRAIE attention
 *                                     neuronale extraite du BiLSTM+Attention)
 *
 * dl_artifacts est écrit par models/train_all.py à partir des VRAIS modèles
 * entraînés. Tant que l'entraînement n'a pas tourné, on renvoie des listes
 * vides + demo=true : le frontend affiche un message honnête, JAMAIS de faux
 * chiffres.
 *
 *   GET /backend/api/deep-learning.php
 *   GET /backend/api/deep-learning.php?attention=1
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sci_status.php';

$me = auth_user();
if (!$me || !in_array($me['role'], ['admin'], true)) {
    json_response(['ok' => false, 'error' => 'admin_or_health_only'], 403);
}

/* --- Lire un artefact JSON écrit par train_all.py --- */
function dl_artifact($key) {
    try {
        $pdo = db();
        $st = $pdo->prepare("SELECT payload FROM dl_artifacts WHERE artifact_key = ?");
        $st->execute([$key]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || !isset($row['payload'])) return null;
        $j = json_decode($row['payload'], true);
        return is_array($j) ? $j : null;
    } catch (Throwable $e) {
        return null;
    }
}

/* --- Réponse carte d'attention seule (vraie attention neuronale) --- */
if (!empty($_GET['attention'])) {
    $att = dl_artifact('attention');
    if (!$att || empty($att['weights'])) {
        json_response([
            'ok' => false, 'error' => 'not_trained',
            'message' => "Carte d'attention indisponible : lancez l'entrainement BiLSTM+Attention (TensorFlow requis).",
        ]);
    }
    json_response(['ok' => true] + $att);
}

/* --- Tableau comparatif DL : uniquement les VRAIS modèles LSTM --- */
$demo = !sci_is_trained();
$models = [];
try {
    $pdo = db();
    $rows = $pdo->query(
        "SELECT model_name, AVG(accuracy) acc, AVG(f1_macro) f1, AVG(mae) mae,
                AVG(rmse) rmse, AVG(r_squared) r2, AVG(auc_roc) auc,
                AVG(avg_latency_ms) latency
         FROM model_performance
         WHERE model_name LIKE '%LSTM%' AND horizon='1h'
         GROUP BY model_name"
    )->fetchAll();
    if ($rows) {
        $demo = false;
        foreach ($rows as $r) {
            // v4.0 : libelle enrichi pour distinguer les variantes LSTM.
$famille = [
    'BiLSTM+AE'  => 'BiLSTM + Autoencoder (latent 16)',
    'BiLSTM'     => 'BiLSTM + Attention',
    'LSTM'       => 'LSTM simple',
][$r['model_name']] ?? $r['model_name'];
$models[] = [
    'name' => $famille, 'params' => '—',
                'acc' => round((float)$r['acc'], 1), 'f1' => round((float)$r['f1'], 3),
                'mae' => round((float)$r['mae'], 2), 'rmse' => round((float)$r['rmse'], 2),
                'r2' => round((float)$r['r2'], 3), 'auc' => round((float)$r['auc'], 3),
                'latency' => round((float)$r['latency'], 1),
            ];
        }
    }
} catch (Throwable $e) { /* laisser $models vide -> état vide honnête */ }

/* --- Artefacts réels (prédictions / série / attention) --- */
$predictions = dl_artifact('predictions') ?: [];
$series = dl_artifact('series') ?: ['labels' => [], 'actual' => [], 'predicted' => []];
$attention = dl_artifact('attention');   // null possible -> le JS affiche "Indisponible"

json_response([
    'ok'          => true,
    'demo'        => ($demo || !$models),
    'models'      => $models,
    'predictions' => $predictions,
    'series'      => $series,
    'attention'   => $attention,
]);
