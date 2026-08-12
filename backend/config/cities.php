<?php
/**
 * Référentiel des VILLES (zones) du gouvernorat de Gabès suivies par Nafass.
 *
 * IMPORTANT : on réutilise EXACTEMENT les villes déjà présentes dans le système
 * Nafass (table `zones`) — aucune ville supplémentaire n'est ajoutée, et les
 * positions GPS restent identiques à celles de la carte.
 *
 * Chaque entrée est indexée par l'`id` de la zone en base et fournit les
 * métadonnées dont le moteur de fusion multi-API a besoin :
 *   - identifiants par fournisseur (waqi_id / iqair_city / accuw_key)
 *   - type de zone, usines avoisinantes
 *   - pollution_factor : multiplicateur appliqué à l'AQI fusionné
 *
 * Les pollution_factor sont calibrés sur la réalité locale de Gabès :
 *   nord-est industriel (complexe chimique) = élevé,
 *   ouest résidentiel / oasis semi-rural    = bas.
 */
declare(strict_types=1);
 
/**
 * @return array<int,array<string,mixed>> indexé par zone_id
 */
function gabes_cities(): array
{
    return [
        // 1 — Centre Ville (وسط المدينة) — commerce & trafic urbain
        1 => [
            'zone_id'          => 1,
            'name_fr'          => 'Centre Ville',
            'accuw_name'       => 'Gabes',
            'name_ar'          => 'وسط المدينة',
            'lat'              => 33.885889,
            'lng'              => 10.107319,
            'waqi_id'          => 'gabes',
            'iqair_city'       => 'Gabes',
            'accuw_key'        => '212020',
            'type'             => 'urban_traffic',
            'factories'        => [],
            'pollution_factor' => 1.10,
            'population'       => 75000,
        ],
        // 2 — Chatt Salem (شط السلام) — sous le vent du complexe chimique
        2 => [
            'zone_id'          => 2,
            'name_fr'          => 'Chatt Salem',
            'accuw_name'       => 'An Nahl',
            'name_ar'          => 'شط السلام',
            'lat'              => 33.901649,
            'lng'              => 10.100321,
            'waqi_id'          => 'gabes',
            'iqair_city'       => 'Gabes',
            'accuw_key'        => 'FIND_VIA_API',
            'type'             => 'industrial_downwind',
            'factories'        => ['GCT', 'SIAPE'],
            'pollution_factor' => 1.50,
            'population'       => 45000,
        ],
        // 3 — Ghannouche (غنوش) — complexe phosphatier, hotspot d'émissions
        3 => [
            'zone_id'          => 3,
            'name_fr'          => 'Ghannouche',
            'accuw_name'       => 'Ghannush',
            'name_ar'          => 'غنوش',
            'lat'              => 33.943053,
            'lng'              => 10.066739,
            'waqi_id'          => 'gabes',
            'iqair_city'       => 'Gabes',
            'accuw_key'        => 'FIND_VIA_API',
            'type'             => 'heavy_industrial',
            'factories'        => ['SIAPE_Ghannouch', 'GCT', 'CPG'],
            'pollution_factor' => 1.90,
            'population'       => 32000,
        ],
        // 4 — Chenini (شنني) — oasis semi-rural à l'ouest
        4 => [
            'zone_id'          => 4,
            'name_fr'          => 'Chenini',
            'accuw_name'       => 'Shanini',
            'name_ar'          => 'شنني',
            'lat'              => 33.879796,
            'lng'              => 10.063941,
            'waqi_id'          => 'gabes',
            'iqair_city'       => 'Gabes',
            'accuw_key'        => 'FIND_VIA_API',
            'type'             => 'semi_rural_oasis',
            'factories'        => [],
            'pollution_factor' => 0.60,
            'population'       => 18000,
        ],
        // 5 — El Bled (البلد) — vieille ville, cœur résidentiel dense
        5 => [
            'zone_id'          => 5,
            'name_fr'          => 'El Bled',
            'accuw_name'       => 'Gabes',
            'name_ar'          => 'البلد',
            'lat'              => 33.891530,
            'lng'              => 10.089126,
            'waqi_id'          => 'gabes',
            'iqair_city'       => 'Gabes',
            'accuw_key'        => 'FIND_VIA_API',
            'type'             => 'urban_old_town',
            'factories'        => [],
            'pollution_factor' => 1.00,
            'population'       => 28000,
        ],
        // 6 — Bouchamma (بوشمة) — district résidentiel ouest
        6 => [
            'zone_id'          => 6,
            'name_fr'          => 'Bouchamma',
            'accuw_name'       => 'Bu Shammah',
            'name_ar'          => 'بوشمة',
            'lat'              => 33.902802,
            'lng'              => 10.052750,
            'waqi_id'          => 'gabes',
            'iqair_city'       => 'Gabes',
            'accuw_key'        => 'FIND_VIA_API',
            'type'             => 'residential',
            'factories'        => [],
            'pollution_factor' => 0.80,
            'population'       => 22000,
        ],
    ];
}
 
/** Renvoie la config d'une ville par zone_id, ou null. */
function gabes_city(int $zoneId): ?array
{
    $all = gabes_cities();
    return $all[$zoneId] ?? null;
}
 
/**
 * Emplacements des principales usines (overlay carte).
 * @return array<int,array<string,mixed>>
 */
function gabes_factories(): array
{
    return [
        ['name' => 'SIAPE Ghannouch', 'lat' => 33.9402, 'lng' => 10.0689],
        ['name' => 'GCT Gabès',       'lat' => 33.9230, 'lng' => 10.0930],
        ['name' => 'CPG Zone',        'lat' => 33.9180, 'lng' => 10.0810],
    ];
}