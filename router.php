<?php
/**
 * Enrutador para el servidor de desarrollo de PHP:
 *     php -S localhost:8080 router.php
 * Bloquea las carpetas privadas (en Apache lo hacen los archivos .htaccess).
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if (preg_match('#^/(app|config|database|lib|storage)(/|$)|/\.|^/admin/_#', $path)) {
    http_response_code(403);
    exit('Acceso denegado');
}
if (preg_match('#^/(index|reto)\.html$#', $path, $m)) {   // enlaces antiguos
    header('Location: /' . ($m[1] === 'reto' ? 'index.php#retos' : 'index.php'), true, 301);
    exit;
}
return false;   // el servidor entrega el archivo solicitado normalmente
