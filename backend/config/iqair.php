<?php
/**
 * Configuration IQAir (AirVisual) — qualité de l'air en temps réel.
 *
 * Étapes :
 *   1. Inscris-toi gratuitement : https://www.iqair.com/dashboard/api
 *   2. Plan "Community" gratuit : 10 000 requêtes / mois (largement suffisant
 *      pour 6 zones × 24 actualisations/jour = ~4 400 req/mois).
 *   3. Copie ta clé ci-dessous (commence en général par une UUID).
 *
 * Si la clé est vide → le bouton « Actualiser depuis IQAir » renvoie une
 * erreur claire et l'app continue avec les valeurs statiques actuelles.
 */

// ⚠️  Colle ta clé IQAir ici (laisse vide si tu n'en as pas encore)
const IQAIR_API_KEY = '1157dd82-a951-495c-a133-1828151c32b4';

// Endpoint le plus pratique pour des coordonnées arbitraires.
// Doc : https://api-docs.iqair.com/?version=latest#0a45e76d-3b21-49ab-8b88-fd4d3a7bccaf
const IQAIR_ENDPOINT = 'https://api.airvisual.com/v2/nearest_city';

// Timeout en secondes pour chaque requête HTTP.
const IQAIR_TIMEOUT = 10;

// Cache : on n'interroge IQAir au plus qu'une fois par heure et par zone
// pour préserver le quota gratuit.
const IQAIR_REFRESH_INTERVAL_MIN = 60;

// Bypass SSL — utile en dev local sur WAMP si php_curl n'a pas de cacert.pem.
// À PASSER À false en production.
const IQAIR_INSECURE = true;
