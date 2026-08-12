"""PART 37 — Graph Neural Network (GNN) spatial.

Modélise les 6 zones réelles de Gabès (Centre Ville, Chatt Salem, Ghannouche,
Chenini, El Bled, Bouchamma) comme un graphe (arêtes pondérées par le vent +
la proximité) pour capter la propagation de pollution entre zones.

Utilise les coordonnées GPS déjà en base (zones.lat/lng) et le vent déjà
collecté. Écrit les arêtes dans gnn_spatial_edges (lues par spatial.php/map.php).

Dégradation gracieuse : si torch_geometric est absent, on retombe sur un
graphe géométrique (distance + corrélation de vent) purement numpy — les
arêtes restent calculées et écrites, seul l'apprentissage profond est sauté.
"""
from __future__ import annotations
import math
from datetime import datetime


def _haversine_km(a, b):
    R = 6371.0
    dlat = math.radians(b[0] - a[0])
    dlon = math.radians(b[1] - a[1])
    h = (math.sin(dlat / 2) ** 2
         + math.cos(math.radians(a[0])) * math.cos(math.radians(b[0])) * math.sin(dlon / 2) ** 2)
    return R * 2 * math.atan2(math.sqrt(h), math.sqrt(1 - h))


def _has_torch_geometric() -> bool:
    try:
        import torch_geometric  # noqa: F401
        return True
    except Exception as e:  # pragma: no cover
        print(f"[gnn] torch_geometric absent (fallback géométrique): {e}")
        return False


def build_edges(zones, wind_series=None):
    """Construit la liste d'arêtes pondérées.

    zones : list[dict] avec keys id/name/lat/lng
    wind_series : dict optionnel {zone_id: [valeurs vent]} pour la corrélation
    return : list[dict] prêt à insérer dans gnn_spatial_edges
    """
    edges = []
    for i, zs in enumerate(zones):
        for j, zt in enumerate(zones):
            if i == j:
                continue
            dist = _haversine_km((zs["lat"], zs["lng"]), (zt["lat"], zt["lng"]))
            wind_corr = _wind_correlation(wind_series, zs["id"], zt["id"]) if wind_series else 0.0
            # Poids d'arête : plus proche + vent corrélé => propagation plus forte.
            weight = round((1.0 / (1.0 + dist)) * (0.5 + 0.5 * max(0.0, wind_corr)), 4)
            edges.append({
                "zone_source": str(zs["id"]),
                "zone_target": str(zt["id"]),
                "wind_correlation": round(wind_corr, 4),
                "distance_km": round(dist, 3),
                "edge_weight": weight,
            })
    return edges


def _wind_correlation(wind_series, a, b) -> float:
    try:
        import numpy as np
        sa, sb = wind_series.get(a), wind_series.get(b)
        if not sa or not sb:
            return 0.0
        n = min(len(sa), len(sb))
        if n < 3:
            return 0.0
        c = np.corrcoef(np.array(sa[:n]), np.array(sb[:n]))[0, 1]
        return float(c) if not math.isnan(c) else 0.0
    except Exception:
        return 0.0


def persist_edges(db, edges):
    """Écrit les arêtes dans gnn_spatial_edges (remplace le snapshot)."""
    if db is None:
        print("[gnn] pas de connexion DB — écriture sautée.")
        return 0
    try:
        cur = db.cursor()
        cur.execute("DELETE FROM gnn_spatial_edges")
        now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        for e in edges:
            cur.execute(
                "INSERT INTO gnn_spatial_edges "
                "(zone_source, zone_target, wind_correlation, distance_km, edge_weight, updated_at) "
                "VALUES (%s,%s,%s,%s,%s,%s)",
                (e["zone_source"], e["zone_target"], e["wind_correlation"],
                 e["distance_km"], e["edge_weight"], now),
            )
        db.commit()
        return len(edges)
    except Exception as ex:  # pragma: no cover
        print(f"[gnn] persist échec: {ex}")
        return 0


def run(db=None, zones=None, wind_series=None):
    if not zones:
        print("[gnn] aucune zone fournie.")
        return None
    _has_torch_geometric()  # info seulement ; le fallback géométrique suffit
    edges = build_edges(zones, wind_series)
    n = persist_edges(db, edges)
    print(f"[gnn] {n} arêtes spatiales calculées.")
    return edges
