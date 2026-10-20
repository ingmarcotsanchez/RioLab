<?php
/**
 * Copia este archivo como config/config.local.php y escribe tus datos.
 * config.local.php está en .gitignore: tus contraseñas no se suben a GitHub.
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'riolab',
        'user' => 'riolab',
        'pass' => 'escribe-aqui-la-contraseña',
    ],
    'base_url'   => 'http://localhost:8080',
    'app_secret' => 'escribe-aqui-una-frase-larga-y-aleatoria',
    'debug'      => false,
];
