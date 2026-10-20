<?php
/**
 * RioLab 4.0 — Configuración
 *
 * No escribas contraseñas en este archivo: copia config.local.example.php
 * como config.local.php y pon allí tus datos (ese archivo no se sube a GitHub).
 * Luego ejecuta el instalador:
 *     php database/install.php
 */

$config = [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'riolab',
        'user'    => 'riolab',
        'pass'    => '',           // se define en config.local.php
        'charset' => 'utf8mb4',
    ],

    // URL pública del sitio (sin barra final). Se usa en el PDF y en enlaces absolutos.
    'base_url' => 'http://localhost:8080',

    // Zona horaria de Colombia
    'timezone' => 'America/Bogota',

    // Carga de documentos
    'uploads' => [
        'dir'       => __DIR__ . '/../storage/uploads',
        'max_mb'    => 20,
        'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip', 'mp4'],
    ],

    // Cadena secreta para anonimizar visitantes en las estadísticas.
    // Defínela en config.local.php con un texto largo y aleatorio.
    'app_secret' => 'cambia-esta-frase-secreta-riolab-4.0',

    // true solo en desarrollo: muestra errores en pantalla
    'debug' => false,
];

if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_replace_recursive($config, require __DIR__ . '/config.local.php');
}

return $config;
