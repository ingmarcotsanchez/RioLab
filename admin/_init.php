<?php
/** RioLab 4.0 — Arranque del panel administrativo: sesión, roles y alcance de mentores. */
require __DIR__ . '/../app/bootstrap.php';
start_session();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

function admin_actual(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $id = (int) ($_SESSION['admin_id'] ?? 0);
    $u = $id ? (q('SELECT id, nombre, correo, rol, activo FROM usuarios WHERE id = ? AND activo = 1', [$id])->fetch() ?: null) : null;
    // Cierre por inactividad (2 horas)
    if ($u && (time() - (int) ($_SESSION['admin_seen'] ?? 0)) > 7200) {
        unset($_SESSION['admin_id']);
        $u = null;
    }
    if ($u) $_SESSION['admin_seen'] = time();
    return $u;
}

function require_login(): array
{
    $u = admin_actual();
    if (!$u) {
        $_SESSION['admin_next'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
    return $u;
}

function es_admin(): bool
{
    return (admin_actual()['rol'] ?? '') === 'admin';
}

function require_admin(): array
{
    $u = require_login();
    if ($u['rol'] !== 'admin') {
        http_response_code(403);
        flash('error', 'Esta sección es solo para administradores.');
        redirect('index.php');
    }
    return $u;
}

/** IDs de retos que el usuario puede ver. null = todos (administrador). */
function retos_permitidos(): ?array
{
    $u = admin_actual();
    if (!$u || $u['rol'] === 'admin') return null;
    static $ids = null;
    if ($ids === null) $ids = array_map('intval', q('SELECT reto_id FROM mentor_retos WHERE usuario_id = ?', [$u['id']])->fetchAll(PDO::FETCH_COLUMN));
    return $ids;
}

/** Fragmento SQL para limitar consultas a los retos del mentor. */
function alcance(string $col): array
{
    $ids = retos_permitidos();
    if ($ids === null) return ['1=1', []];
    if (!$ids) return ['1=0', []];
    return [$col . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids];
}

function puede_ver_reto(?int $retoId): bool
{
    $ids = retos_permitidos();
    return $ids === null || ($retoId !== null && in_array($retoId, $ids, true));
}

function badge(string $texto, string $tono): string
{
    return '<span class="badge badge--' . e($tono) . '">' . e($texto) . '</span>';
}

/** Ícono de interfaz (Lucide simplificado). */
function ui_icon(string $name): string
{
    $p = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
        'files'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
        'flag'      => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
        'book'      => '<path d="M2 5.5C4.5 4 8 4 12 6c4-2 7.5-2 10-.5V19c-2.5-1.5-6-1.5-10 .5-4-2-7.5-2-10-.5Z"/><path d="M12 6v13.5"/>',
        'shield'    => '<path d="M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'logout'    => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3"/>',
        'external'  => '<path d="M14 4h6v6M20 4l-9 9M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'download'  => '<path d="M12 4v12m-5-5 5 5 5-5"/><path d="M4 18v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1"/>',
        'edit'      => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'trash'     => '<path d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>',
        'eye'       => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'key'       => '<circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 8.2-8.2M16 7l3 3M14 9l2 2"/>',
        'pdf'       => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M12 11v6m-3-3 3 3 3-3"/>',
        'arrow-left'=> '<path d="M19 12H5M11 18l-6-6 6-6"/>',
        'x'         => '<path d="M6 6l12 12M18 6 6 18"/>',
    ][$name] ?? '';
    return '<svg class="i" viewBox="0 0 24 24" aria-hidden="true">' . $p . '</svg>';
}
