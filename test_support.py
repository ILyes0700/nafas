from __future__ import annotations

import os
import sys
from pathlib import Path

TEST_ROOT = Path(__file__).resolve().parent

# In the delivered ZIP, source/models is under TEST_ROOT/source/models.
# If tests are copied to the actual Nafass project, models is directly under
# the project root, so the fallback supports both layouts.
SOURCE_ROOT = TEST_ROOT / "source"
if not (SOURCE_ROOT / "models").is_dir():
    SOURCE_ROOT = TEST_ROOT

if str(SOURCE_ROOT) not in sys.path:
    sys.path.insert(0, str(SOURCE_ROOT))


def data_csv() -> Path:
    """Return the real Nafass CSV path supplied by the user."""
    explicit = os.environ.get("NAFAS_DATA_CSV")
    candidates = []
    if explicit:
        candidates.append(Path(explicit))
    candidates.extend([
        TEST_ROOT / "data-current" / "gabes_air_quality_dataset.csv",
        TEST_ROOT.parent / "data-current" / "gabes_air_quality_dataset.csv",
    ])
    for path in candidates:
        if path.is_file():
            return path
    raise SystemExit(
        "CSV réel introuvable. Définissez NAFAS_DATA_CSV vers "
        "gabes_air_quality_dataset.csv avant de lancer ce test."
    )


def enriched_sample_json() -> Path:
    """Return an optional real Open-Meteo enrichment sample."""
    explicit = os.environ.get("NAFAS_ENRICHED_SAMPLE")
    candidates = []
    if explicit:
        candidates.append(Path(explicit))
    candidates.extend([
        TEST_ROOT / "openmeteo_historical_enriched_sample.json",
        TEST_ROOT.parent / "openmeteo_historical_enriched_sample.json",
    ])
    for path in candidates:
        if path.is_file():
            return path
    raise SystemExit(
        "عينة Open-Meteo غير موجودة. عيّن NAFAS_ENRICHED_SAMPLE أو تخطَّ هذا الاختبار."
    )
