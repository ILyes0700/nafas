<?php
/**
 * AccuWeather — SOURCE PRIMAIRE du système de fusion (poids 75%).
 *
 * AccuWeather expose 4 endpoints utilisés par le moteur de fusion :
 *   A) Location Key  — locations/v1/cities/search?q={city}
 *   B) Air Quality   — airquality/v2/hourly/12h/{key}
 *   C) Current Cond. — currentconditions/v1/{key}
 *   D) Forecast 12h  — forecasts/v1/hourly/12hours/{key}
 *
 * Pour obtenir une clé gratuite :
 *   1. https://developer.accuweather.com/ → créer une app
 *   2. Plan "Limited Trial" : 50 requêtes / jour (suffisant pour 6 villes
 *      avec un cache d'1 heure).
 *   3. Coller la clé ci-dessous.
 *
 * Si la clé est vide OU si l'API est injoignable, le moteur de fusion bascule
 * automatiquement sur un générateur déterministe par ville (fusion_synthetic_*)
 * afin que chaque ville continue d'afficher SA PROPRE valeur d'AQI.
 */
declare(strict_types=1);
 
// ⚠  Colle ta clé AccuWeather ici (laisse vide si tu n'en as pas encore).
const ACCUWEATHER_API_KEY = 'zpka_877821f28ec94540b9f6e4e795c97007_aaf03bd0';
 
// Endpoints REST (le moteur ajoute apikey + paramètres).
const ACCUWEATHER_BASE          = 'http://dataservice.accuweather.com';
const ACCUWEATHER_SEARCH        = ACCUWEATHER_BASE . '/locations/v1/cities/search';
const ACCUWEATHER_AIRQUALITY    = ACCUWEATHER_BASE . '/airquality/v2/hourly/12h/';
const ACCUWEATHER_CURRENT       = ACCUWEATHER_BASE . '/currentconditions/v1/';
const ACCUWEATHER_FORECAST_12H  = ACCUWEATHER_BASE . '/forecasts/v1/hourly/12hours/';
 
// Timeout par requête HTTP (secondes) — la fusion utilise 5s comme demandé.
const ACCUWEATHER_TIMEOUT = 5;
 
// Quota journalier indicatif (utilisé pour api_config.daily_limit).
const ACCUWEATHER_DAILY_LIMIT = 50;
 
// Bypass SSL — pratique en dev local (WAMP sans cacert.pem). false en prod.
const ACCUWEATHER_INSECURE = true;