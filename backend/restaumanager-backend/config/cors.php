<?php
return [
    'paths'                    => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => [
        'http://localhost',
        'http://localhost:8000',
        'http://localhost:8080',
        'http://localhost:5500',
        'http://localhost:5501',
        'http://localhost:3000',
        'http://localhost:4200',
        'http://127.0.0.1',
        'http://127.0.0.1:8000',
        'http://127.0.0.1:8080',
        'http://127.0.0.1:5500',
        'http://127.0.0.1:5501',
        'http://127.0.0.1:3000',
    ],
    // Accepte les fichiers ouverts directement (file://) et tous les sous-domaines localhost
    'allowed_origins_patterns' => [
        '#^null$#',                          // file:// origin
        '#^https?://localhost(:\d+)?$#',     // n'importe quel port localhost
        '#^https?://127\.0\.0\.1(:\d+)?$#', // n'importe quel port 127.0.0.1
    ],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => ['Last-Event-ID', 'X-Accel-Buffering'],
    'max_age'                  => 0,
    'supports_credentials'     => true,
];