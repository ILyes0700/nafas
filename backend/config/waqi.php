<?php
/**
 * WAQI — World Air Quality Index (free, https://aqicn.org/api/)
 *
 * Used as a SECOND independent source of pollution data so we can:
 *   1. Cross-check IQAir values (outlier detection)
 *   2. Build a "fused" estimate via the median of all available sources
 *
 * To get a free token: https://aqicn.org/data-platform/token/
 * Paste it below — leave empty to disable WAQI verification entirely.
 *
 * Production tip: store the token in a non-committed .env file and read it
 * with getenv('WAQI_API_KEY').
 */
declare(strict_types=1);

// Replace with your own token (free signup, no credit card required)
const WAQI_API_KEY = 'c886a09c-20cb-426b-8f6d-9501cc4147b4';

const WAQI_ENDPOINT  = 'https://api.waqi.info/feed/geo:';
const WAQI_TIMEOUT   = 8;
const WAQI_INSECURE  = true;   // WAMP-friendly (cacert missing). Set FALSE in prod.
const WAQI_CACHE_MIN = 30;     // Re-query interval per zone (minutes)
