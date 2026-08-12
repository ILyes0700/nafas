"""PART 17.5 - Real-time WebSocket server (Flask-SocketIO, port 5001).

Diffuse les DERNIERES MESURES REELLES (table api_readings) vers le dashboard
toutes les 5 minutes et immediatement a la connexion.

ZERO DEMO / ZERO RANDOM : si la base n'a aucune mesure pour une zone, rien n'est
emis pour cette zone. Aucune valeur n'est inventee.
Le client navigateur est frontend/scripts/websocket_client.js.

Reference: WebSocket RFC 6455.
"""
from __future__ import annotations
import os, sys, time, threading

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

try:
    from flask import Flask
    from flask_socketio import SocketIO, emit
except Exception as e:  # pragma: no cover
    raise SystemExit("Install flask-socketio: pip install flask flask-socketio") from e

try:
    from db_config import try_connection
except Exception:  # pragma: no cover
    try_connection = None

app = Flask(__name__)
socketio = SocketIO(app, cors_allowed_origins="*", async_mode="threading")


def _latest_real_rows():
    """Derniere mesure REELLE par zone depuis api_readings, ou [] si la base est
    indisponible/vide. Ne fabrique jamais de donnee."""
    if try_connection is None:
        print("[ws] db_config indisponible")
        return []
    conn = try_connection()
    if conn is None:
        return []
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute(
            "SELECT r.city_id, r.city_name, r.final_aqi, r.final_category, "
            "r.final_pm25, r.final_so2, r.timestamp "
            "FROM api_readings r "
            "JOIN (SELECT city_id, MAX(timestamp) mt FROM api_readings GROUP BY city_id) m "
            "ON m.city_id = r.city_id AND m.mt = r.timestamp "
            "WHERE r.final_aqi IS NOT NULL"
        )
        rows = cur.fetchall()
        cur.close(); conn.close()
        return rows
    except Exception as e:  # pragma: no cover
        print("[ws] lecture api_readings impossible:", e)
        try:
            conn.close()
        except Exception:
            pass
        return []


def build_payload(row):
    """Construit le message a partir d'une VRAIE ligne api_readings."""
    aqi = float(row["final_aqi"])
    ts = row.get("timestamp")
    return {
        "city_id": str(row["city_id"]),
        "city_name": row.get("city_name") or str(row["city_id"]),
        "aqi": round(aqi, 1),
        "category": row.get("final_category") or "",
        "so2": round(float(row["final_so2"]), 1) if row.get("final_so2") is not None else None,
        "pm25": round(float(row["final_pm25"]), 1) if row.get("final_pm25") is not None else None,
        "anomaly": False,
        "timestamp": ts.isoformat() if hasattr(ts, "isoformat") else str(ts),
    }


def emit_all():
    rows = _latest_real_rows()
    if not rows:
        print("[ws] aucune donnee reelle a diffuser (base vide ou hors ligne)")
        return
    for row in rows:
        try:
            socketio.emit("aqi_update", build_payload(row))
        except Exception as e:  # pragma: no cover
            print("[ws] emit skipped:", e)


def push_updates():
    while True:
        emit_all()
        time.sleep(300)  # toutes les 5 minutes


@socketio.on("connect")
def on_connect():
    emit_all()


if __name__ == "__main__":
    threading.Thread(target=push_updates, daemon=True).start()
    socketio.run(app, port=5001, debug=False)
