<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = new Iserter\EasyLeadCapture\App([
    'base_path' => '/lead-capture',
    'admin' => [
        'password' => 'change_me',
    ],
    'ip_geo' => [
        'provider' => getenv('IP_GEO_PROVIDER') ?: 'ipsage',
        'ipsage_endpoint' => getenv('IPSAGE_ENDPOINT') ?: 'http://127.0.0.1:8040',
        'ipapico_api_key' => getenv('IPAPI_CO_API_KEY') ?: null,
    ],
]);

$app->run();
