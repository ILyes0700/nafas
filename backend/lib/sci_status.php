<?php
/**
 * Shared helper: has the scientific pipeline been trained on the real DB?
 * Returns true once models/train_all.py has written rows into model_performance
 * (which only happens after real training). Used by every scientific API to
 * decide whether to show REAL results (demo=false) or the demo fallback.
 */
require_once __DIR__ . '/helpers.php';

function sci_is_trained(): bool
{
    static $cached = null;
    if ($cached !== null) return $cached;
    try {
        $pdo = db();
        $n = (int)$pdo->query(
            "SELECT COUNT(*) FROM model_performance WHERE horizon IS NOT NULL AND horizon <> ''"
        )->fetchColumn();
        $cached = ($n > 0);
    } catch (Throwable $e) {
        $cached = false;
    }
    return $cached;
}

/** Count rows in a table safely (0 if missing/empty). */
function sci_count(string $table): int
{
    try {
        $pdo = db();
        return (int)$pdo->query("SELECT COUNT(*) FROM `" . $table . "`")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
