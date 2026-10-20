<?php
/** Recibe el formulario "Quiero participar" y registra al participante y su inscripción. */
require __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php#unete');
csrf_check();

/** Responde en JSON (fetch) o con una página sencilla si el navegador no tiene JavaScript. */
function responder(array $data, int $status = 200): never
{
    if (is_json_request()) json_out($data, $status);
    $title = 'Inscripción · RioLab 4.0';
    $extraCss = ['css/portal.css'];
    $bodyClass = 'page-portal';
    require ROOT . '/app/views/public_header.php';
    echo '<main id="main" class="portal"><div class="container portal-narrow"><div class="pcard">';
    if ($data['ok']) {
        echo '<h1 class="pcard-title">' . e($data['titulo']) . '</h1><p>' . e($data['mensaje']) . '</p>';
        if (!empty($data['codigo'])) echo '<p class="code-big">' . e($data['codigo']) . '</p>';
    } else {
        echo '<h1 class="pcard-title">Revisa tu inscripción</h1><ul>';
        foreach (($data['errors'] ?? []) + (isset($data['error']) ? [$data['error']] : []) as $m) echo '<li>' . e($m) . '</li>';
        echo '</ul>';
    }
    echo '<p><a class="btn btn-sun" href="' . url('index.php#retos') . '">Volver a los retos</a></p></div></div></main>';
    require ROOT . '/app/views/public_footer.php';
    exit;
}

// Bots: campo trampa lleno → fingimos éxito sin guardar
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    responder(['ok' => true, 'titulo' => '¡Gracias!', 'mensaje' => 'Recibimos tu registro.']);
}

if (too_many_attempts('inscripcion', 'ip', 12, 60)) {
    responder(['ok' => false, 'error' => 'Has enviado demasiados registros seguidos. Intenta de nuevo en una hora.'], 429);
}

$in = fn(string $k, int $max) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
$d = [
    'nombre'    => $in('nombre', 160),
    'correo'    => mb_strtolower($in('correo', 160)),
    'telefono'  => $in('telefono', 40),
    'municipio' => $in('municipio', 80),
    'perfil'    => $in('perfil', 60),
    'reto'      => $in('reto', 80),
    'mensaje'   => $in('mensaje', 500),
    'origen'    => ($_POST['origen'] ?? '') === 'reto' ? 'reto' : 'inicio',
];

$errors = [];
if (mb_strlen($d['nombre']) < 3) $errors['nombre'] = 'Escribe tu nombre completo.';
if (!filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) $errors['correo'] = 'Ingresa un correo válido.';
if (strlen(preg_replace('/\D/', '', $d['telefono'])) < 7) $errors['telefono'] = 'Ingresa un número de contacto válido.';
if (mb_strlen($d['municipio']) < 2) $errors['municipio'] = 'Indica tu municipio.';
if (!in_array($d['perfil'], PERFILES, true)) $errors['perfil'] = 'Selecciona cómo quieres participar.';
if (empty($_POST['acepto'])) $errors['acepto'] = 'Necesitamos tu autorización para contactarte.';

$reto = null;
if ($d['reto'] !== '') {
    $reto = reto_por_slug($d['reto']);
    if (!$reto) $errors['reto'] = 'El reto seleccionado no existe.';
    elseif (!$reto['abierto']) $errors['reto'] = $reto['motivo_cerrado'];
}
if ($errors) {
    // Si el reto está cerrado en la página del reto no hay selector: mostramos el mensaje general
    $error = isset($errors['reto']) && $d['origen'] === 'reto' ? $errors['reto'] : null;
    responder(['ok' => false, 'errors' => $errors, 'error' => $error], 422);
}

register_attempt('inscripcion', 'ip');
$pdo = db();
$pdo->beginTransaction();
try {
    $part = q('SELECT id FROM participantes WHERE correo = ?', [$d['correo']])->fetch();
    $codigo = null;
    if (!$part) {
        $codigo = codigo_generar();
        q('INSERT INTO participantes (nombre, correo, telefono, municipio, perfil, codigo_hash, acepto_datos_at) VALUES (?,?,?,?,?,?, NOW())',
            [$d['nombre'], $d['correo'], $d['telefono'], $d['municipio'], $d['perfil'], password_hash(codigo_normalizar($codigo), PASSWORD_DEFAULT)]);
        $pid = (int) $pdo->lastInsertId();
    } else {
        $pid = (int) $part['id'];
    }

    // ¿Ya estaba inscrito en este reto (o ya tenía un registro de interés general)?
    $dup = $reto
        ? q('SELECT id FROM inscripciones WHERE participante_id = ? AND reto_id = ?', [$pid, $reto['id']])->fetch()
        : q('SELECT id FROM inscripciones WHERE participante_id = ? AND reto_id IS NULL', [$pid])->fetch();

    if (!$dup) {
        q('INSERT INTO inscripciones (participante_id, reto_id, mensaje, origen) VALUES (?,?,?,?)', [$pid, $reto['id'] ?? null, $d['mensaje'], $d['origen']]);
    }
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    error_log('[RioLab] inscripción: ' . $ex->getMessage());
    responder(['ok' => false, 'error' => 'No pudimos guardar tu registro. Intenta de nuevo en unos minutos.'], 500);
}

$nombreCorto = explode(' ', $d['nombre'])[0];
if ($dup) {
    $msg = $reto ? "Ya estabas inscrito en «{$reto['titulo']}». No necesitas registrarte de nuevo." : 'Ya teníamos tu registro. Pronto te contactaremos.';
    responder(['ok' => true, 'titulo' => "¡Hola de nuevo, {$nombreCorto}!", 'mensaje' => $msg]);
}
if ($codigo) {
    $msg = $reto ? "Quedaste inscrito en «{$reto['titulo']}». El equipo RioLab revisará tu inscripción y te contactará." : 'Recibimos tu registro. Pronto te contactaremos con los próximos pasos.';
    responder(['ok' => true, 'titulo' => "¡Bienvenido a RioLab 4.0, {$nombreCorto}!", 'mensaje' => $msg, 'codigo' => $codigo]);
}
// Participante existente que se inscribe a un reto adicional: no se revela su código
responder(['ok' => true, 'titulo' => "¡Listo, {$nombreCorto}!",
    'mensaje' => ($reto ? "Agregamos «{$reto['titulo']}» a tus inscripciones." : 'Actualizamos tu registro.') . ' Usa el mismo código de acceso que recibiste en tu primera inscripción para ingresar a Mis entregas.']);
