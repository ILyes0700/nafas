"""PART 20 - Federated learning (FedAvg). Each city trains locally and shares
only weights; the server aggregates them weighted by sample count.
Reference: McMahan et al. (2017), AISTATS.
"""
from __future__ import annotations
import numpy as np


def fedavg(all_local_weights, n_samples_per_city):
    """all_local_weights: list (per city) of list of np.ndarray layers.
    n_samples_per_city: list of ints. Returns aggregated global weights."""
    total = float(sum(n_samples_per_city))
    n_layers = len(all_local_weights[0])
    global_weights = []
    for li in range(n_layers):
        agg = sum(w[li] * (n / total)
                  for w, n in zip(all_local_weights, n_samples_per_city))
        global_weights.append(agg)
    return global_weights


def federated_round(clone_global, train_local, cities_data, global_weights):
    """One FedAvg round. clone_global()-> model; train_local(model, X, y)->weights."""
    local_weights, n_samples = [], []
    for X, y in cities_data:
        model = clone_global()
        model.set_weights(global_weights)
        w = train_local(model, X, y)
        local_weights.append(w); n_samples.append(len(X))
    return fedavg(local_weights, n_samples)


if __name__ == "__main__":
    # numeric demo of the aggregation math
    w1 = [np.array([1.0, 2.0])]; w2 = [np.array([3.0, 4.0])]
    print("FedAvg:", fedavg([w1, w2], [100, 300]))
