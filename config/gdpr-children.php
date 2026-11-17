<?php

return [

    'tables' => [
        'assurances' => 'gdpr_age_assurances',
        'guardians' => 'gdpr_guardian_links',
    ],

    /*
     * Age of digital consent for information society services offered directly to a child
     * (GDPR Art. 8(1)): 16 by default; member states may lower it to no less than 13.
     * Check the current national law before relying on these values.
     */
    'consent_age' => 16,

    'consent_ages' => [
        'AT' => 14, 'BE' => 13, 'BG' => 14, 'HR' => 16, 'CY' => 14, 'CZ' => 15, 'DK' => 13, 'EE' => 13,
        'FI' => 13, 'FR' => 15, 'DE' => 16, 'GR' => 15, 'HU' => 16, 'IE' => 16, 'IT' => 14, 'LV' => 13,
        'LT' => 14, 'LU' => 16, 'MT' => 13, 'NL' => 16, 'PL' => 16, 'PT' => 13, 'RO' => 16, 'SK' => 16,
        'SI' => 15, 'ES' => 14, 'SE' => 13, 'IS' => 13, 'LI' => 16, 'NO' => 13,
    ],

    /*
     * Methods that count as reliable age checks. Self-declaration never does.
     */
    'reliable_methods' => [
        'os_signal',
        'store_signal',
        'document',
        'facial_estimation',
        'verifiable_credential',
    ],

    /*
     * How long a result stays valid, per method (CarbonInterval string, null = forever).
     */
    'valid_for' => [
        'self_declaration' => '1 year',
        'os_signal' => '30 days',
        'store_signal' => '30 days',
        'document' => '1 year',
        'facial_estimation' => '1 year',
        'verifiable_credential' => '1 year',
        'guardian' => '1 year',
    ],

    /*
     * The service is likely to be used by children: users with no age result are treated
     * as children where it matters (they need parental authorisation, no ad profiling).
     */
    'likely_access' => true,

    /*
     * Where `gdpr.adult` sends users without a reliable adult result. Null = 403.
     */
    'verify_route' => null,

];
