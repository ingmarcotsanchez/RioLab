<?php
/** RioLab 4.0 — Carga y descarga segura de documentos de los participantes. */

const UPLOAD_MIMES = [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'xls'  => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'ppt'  => ['application/vnd.ms-powerpoint', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'zip'  => ['application/zip', 'application/x-zip-compressed'],
    'mp4'  => ['video/mp4'],
];

/**
 * Valida y guarda un archivo subido. Devuelve los datos para la tabla `entregas`.
 * @throws RuntimeException con un mensaje apto para mostrar al usuario.
 */
function upload_guardar(array $file): array
{
    $maxMb = (int) config('uploads.max_mb', 20);
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Selecciona un archivo para cargar.');
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException("El archivo supera el tamaño máximo de {$maxMb} MB.");
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new RuntimeException('No se pudo recibir el archivo. Inténtalo de nuevo.');
    if ($file['size'] > $maxMb * 1048576) throw new RuntimeException("El archivo supera el tamaño máximo de {$maxMb} MB.");
    if ($file['size'] === 0) throw new RuntimeException('El archivo está vacío.');

    $original = mb_substr(basename(str_replace('\\', '/', (string) $file['name'])), 0, 200);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $permitidas = array_intersect(config('uploads.extensions', []), array_keys(UPLOAD_MIMES));
    if (!in_array($ext, $permitidas, true)) {
        throw new RuntimeException('Formato no permitido. Usa: ' . strtoupper(implode(', ', $permitidas)) . '.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: 'application/octet-stream';
    if (!in_array($mime, UPLOAD_MIMES[$ext], true)) {
        throw new RuntimeException('El contenido del archivo no corresponde a su extensión (.' . $ext . ').');
    }

    $sub = date('Y/m');
    $dir = rtrim(config('uploads.dir'), '/') . '/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('No se pudo preparar la carpeta de documentos.');
    $nombre = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], "$dir/$nombre")) throw new RuntimeException('No se pudo guardar el archivo.');
    @chmod("$dir/$nombre", 0640);

    return ['nombre_original' => $original, 'archivo' => "$sub/$nombre", 'mime' => $mime, 'tamano' => (int) $file['size']];
}

function upload_ruta(string $archivo): string
{
    // El nombre guardado nunca contiene "..": lo generamos nosotros
    return rtrim(config('uploads.dir'), '/') . '/' . str_replace('..', '', $archivo);
}

/** Envía el archivo al navegador como descarga. */
function upload_enviar(array $entrega): never
{
    $ruta = upload_ruta($entrega['archivo']);
    if (!is_file($ruta)) not_found('El archivo ya no está disponible.');
    $inline = in_array($entrega['mime'], ['application/pdf', 'image/jpeg', 'image/png'], true) && isset($_GET['ver']);
    header('Content-Type: ' . $entrega['mime']);
    header('Content-Length: ' . filesize($ruta));
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($entrega['nombre_original']));
    readfile($ruta);
    exit;
}

function upload_borrar(string $archivo): void
{
    $ruta = upload_ruta($archivo);
    if (is_file($ruta)) @unlink($ruta);
}

const ESTADOS_ENTREGA = [
    'recibido'         => ['Recibido', 'neutral'],
    'en_revision'      => ['En revisión', 'info'],
    'aprobado'         => ['Aprobado', 'ok'],
    'requiere_ajustes' => ['Requiere ajustes', 'warn'],
];

const ESTADOS_INSCRIPCION = [
    'pendiente'  => ['Pendiente', 'neutral'],
    'aceptado'   => ['Aceptado', 'ok'],
    'en_curso'   => ['En curso', 'info'],
    'finalizado' => ['Finalizado', 'ok'],
    'rechazado'  => ['No admitido', 'danger'],
];
