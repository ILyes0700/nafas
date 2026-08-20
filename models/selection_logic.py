from __future__ import annotations

import math


def select_deployment_model(validation_scores, test_scores, latest_predictions):
    """Select by Validation only; Test is a report and latest is production output."""
    eligible = {
        str(name): float(score)
        for name, score in validation_scores.items()
        if name in test_scores and name in latest_predictions
        and math.isfinite(float(score))
        and math.isfinite(float(test_scores[name]))
        and math.isfinite(float(latest_predictions[name]))
    }
    if not eligible:
        return None
    name = min(eligible, key=eligible.get)
    return {
        "model": name,
        "validation_rmse": float(eligible[name]),
        "test_rmse": float(test_scores[name]),
        "latest_pred": float(latest_predictions[name]),
        "eligible_models": sorted(eligible),
    }
