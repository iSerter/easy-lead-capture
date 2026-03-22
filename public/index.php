<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = new Iserter\EasyLeadCapture\App([
    'base_path' => '/lead-capture',
    'admin' => [
        'password' => 'change_me',
    ],
]);

$app->run();
