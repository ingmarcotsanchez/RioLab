<?php
/**
 * RioLab 4.0 — Arranque común: configuración, base de datos, sesión y utilidades.
 * Todas las páginas PHP incluyen este archivo primero.
 */
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

$GLOBALS['config'] = require ROOT . '/config/config.php';

function config(string $key, $default = null)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}

date_default_timezone_set(config('timezone', 'America/Bogota'));
ini_set('display_errors', config('debug') ? '1' : '0');
error_reporting(E_ALL);

/* ---------- Base de datos ---------- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = config('db');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['name'], $c['charset']);
    try {
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    } catch (PDOException $e) {
        http_response_code(503);
        if (config('debug')) die('Error de conexión a MySQL: ' . htmlspecialchars($e->getMessage()));
        die('El servicio no está disponible en este momento. Intenta más tarde.');
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/* ---------- Sesión ---------- */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('riolab_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

/* ---------- Seguridad ---------- */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    start_session();
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        if (is_json_request()) json_out(['ok' => false, 'error' => 'La sesión expiró. Recarga la página e inténtalo de nuevo.'], 419);
        die('La sesión expiró. Vuelve atrás, recarga la página e inténtalo de nuevo.');
    }
}

function is_json_request(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Limita intentos de inicio de sesión: máx. $max en $minutes minutos. */
function too_many_attempts(string $ambito, string $id, int $max = 6, int $minutes = 15): bool
{
    $key = hash('sha256', $ambito . '|' . client_ip() . '|' . strtolower($id));
    $n = (int) q('SELECT COUNT(*) FROM login_intentos WHERE ambito = ? AND clave = ? AND created_at > (NOW() - INTERVAL ? MINUTE)',
        [$ambito, $key, $minutes])->fetchColumn();
    return $n >= $max;
}

function register_attempt(string $ambito, string $id): void
{
    $key = hash('sha256', $ambito . '|' . client_ip() . '|' . strtolower($id));
    q('INSERT INTO login_intentos (ambito, clave) VALUES (?, ?)', [$ambito, $key]);
    if (random_int(1, 50) === 1) q('DELETE FROM login_intentos WHERE created_at < (NOW() - INTERVAL 1 DAY)');
}

function clear_attempts(string $ambito, string $id): void
{
    $key = hash('sha256', $ambito . '|' . client_ip() . '|' . strtolower($id));
    q('DELETE FROM login_intentos WHERE ambito = ? AND clave = ?', [$ambito, $key]);
}

/* ---------- Respuestas ---------- */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function flash(string $type, string $msg): void
{
    start_session();
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    start_session();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function not_found(string $msg = 'Página no encontrada'): never
{
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>404</title><p style="font-family:sans-serif;padding:2rem">' . e($msg) . ' · <a href="/">Volver al inicio</a></p>';
    exit;
}

/* ---------- Formato ---------- */
const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

function fecha_larga(?string $date): string
{
    if (!$date) return 'Por definir';
    $t = strtotime($date);
    return date('j', $t) . ' de ' . MESES[(int) date('n', $t) - 1] . ' de ' . date('Y', $t);
}

function mes_anio(?string $date): string
{
    if (!$date) return 'Por definir';
    $t = strtotime($date);
    return ucfirst(MESES[(int) date('n', $t) - 1]) . ' de ' . date('Y', $t);
}

function fecha_corta(?string $datetime): string
{
    if (!$datetime) return '—';
    $t = strtotime($datetime);
    return date('j', $t) . ' ' . substr(MESES[(int) date('n', $t) - 1], 0, 3) . ' ' . date('Y', $t);
}

function bytes_legibles(int $b): string
{
    if ($b >= 1048576) return number_format($b / 1048576, 1, ',', '.') . ' MB';
    return max(1, (int) round($b / 1024)) . ' KB';
}

function jdecode(?string $json): array
{
    $v = json_decode($json ?? '[]', true);
    return is_array($v) ? $v : [];
}

function slugify(string $text): string
{
    $text = strtr(mb_strtolower($text), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'reto';
}

/* ---------- URLs relativas (funciona en la raíz o en una subcarpeta) ---------- */
function url(string $path = ''): string
{
    static $prefix = null;
    if ($prefix === null) {
        $dir = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? ROOT . '/index.php')) ?: ROOT;
        $rel = trim(substr($dir, strlen(realpath(ROOT))), DIRECTORY_SEPARATOR);
        $prefix = $rel === '' ? '' : str_repeat('../', substr_count($rel, DIRECTORY_SEPARATOR) + 1);
    }
    return $prefix . $path;
}

/* ---------- Código de acceso de participantes ---------- */
function codigo_generar(): string
{
    $alfabeto = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';   // sin 0/O ni 1/I para evitar confusiones
    $c = '';
    for ($i = 0; $i < 8; $i++) $c .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    return 'RL-' . substr($c, 0, 4) . '-' . substr($c, 4);
}

function codigo_normalizar(string $codigo): string
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($codigo));
}

/* ---------- Sesión del participante (portal "Mis entregas") ---------- */
function participante_actual(): ?array
{
    start_session();
    static $p = false;
    if ($p !== false) return $p;
    $id = (int) ($_SESSION['participante_id'] ?? 0);
    $p = $id ? (q('SELECT * FROM participantes WHERE id = ?', [$id])->fetch() ?: null) : null;
    return $p;
}

/* ---------- Analítica anónima ---------- */
function track(string $tipo, string $pagina, ?int $retoId = null): void
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    // Ignorar bots evidentes
    if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|monitor|curl|wget/i', $ua)) return;

    // Identificador anónimo que cambia cada día: no se guarda la IP
    $visitante = substr(hash('sha256', client_ip() . $ua . date('Y-m-d') . config('app_secret')), 0, 16);
    $dispositivo = preg_match('/tablet|ipad/i', $ua) ? 'tablet' : (preg_match('/mobi|android|iphone/i', $ua) ? 'movil' : 'desktop');
    $ref = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST) ?: '';
    if ($ref === ($_SERVER['HTTP_HOST'] ?? '')) $ref = '';

    try {
        q('INSERT INTO eventos (tipo, pagina, reto_id, visitante, dispositivo, referer_host) VALUES (?,?,?,?,?,?)',
            [$tipo, $pagina, $retoId, $visitante, $dispositivo, substr($ref, 0, 120)]);
    } catch (Throwable $e) {
        // Las estadísticas nunca deben romper la página
    }
}

require ROOT . '/app/retos.php';
require ROOT . '/app/icons.php';
require ROOT . '/app/uploads.php';
