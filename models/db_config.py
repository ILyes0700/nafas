"""MySQL connection for the Python ML layer.
Matches backend/config/database.php (WAMP defaults: 127.0.0.1 / root / no pass).
Override via environment variables if needed.
"""
from __future__ import annotations
import os

DB = {
    "host": os.environ.get("GT_DB_HOST", "127.0.0.1"),
    "port": int(os.environ.get("GT_DB_PORT", "3306")),
    "database": os.environ.get("GT_DB_NAME", "gabes_tatenafas"),
    "user": os.environ.get("GT_DB_USER", "root"),
    "password": os.environ.get("GT_DB_PASS", ""),
}


def get_connection():
    """Return a live MySQL connection or raise a helpful error."""
    try:
        import mysql.connector
        return mysql.connector.connect(**DB)
    except Exception as e:  # pragma: no cover
        raise RuntimeError(
            "Cannot connect to MySQL. Start WAMP/MySQL and check db_config.DB. "
            "Install driver with: pip install mysql-connector-python. Detail: %s" % e)


def try_connection():
    """Return connection or None (no exception) — used for graceful fallback."""
    try:
        return get_connection()
    except Exception as e:
        print("[db_config] DB unavailable:", e)
        return None
