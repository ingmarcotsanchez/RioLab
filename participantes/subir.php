<?php
/** Recibe un documento del participante para un entregable de su reto. */
require __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('./');
// Si el archivo supera post_max_size, PHP descarta todo el POST (incluido el token)
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'El archivo es demasiado grande. El tamaño máximo es de ' . (int) config('uploads.max_mb') . ' MB.');
    redirect('./');
}
csrf_check();
$yo = participante_actual();
if (!$yo) redirect('./');

$insId = (int) ($_POST['inscripcion_id'] ?? 0);
$entId = (int) ($_POST['entregable_id'] ?? 0);
$ins = q("SELECT * FROM inscripciones WHERE id = ? AND participante_id = ? AND reto_id IS NOT NULL AND estado <> 'rechazado'", [$insId, $yo['id']])->fetch();
if (!$ins) { flash('error', 'No puedes cargar documentos en esta inscripción.'); redirect('./'); }
if ($entId && !q('SELECT id FROM reto_entregables WHERE id = ? AND reto_id = ?', [$entId, $ins['reto_id']])->fetch()) {
    flash('error', 'El entregable seleccionado no pertenece a tu reto.');
    redirect('./#ins-' . $insId);
}

try {
    $f = upload_guardar($_FILES['archivo'] ?? []);
    q('INSERT INTO entregas (inscripcion_id, entregable_id, nombre_original, archivo, mime, tamano, comentario) VALUES (?,?,?,?,?,?,?)',
        [$insId, $entId ?: null, $f['nombre_original'], $f['archivo'], $f['mime'], $f['tamano'], mb_substr(trim((string) ($_POST['comentario'] ?? '')), 0, 500)]);
    flash('ok', '«' . $f['nombre_original'] . '» se cargó correctamente. Tu mentor lo revisará pronto.');
} catch (RuntimeException $ex) {
    flash('error', $ex->getMessage());
}
redirect('./#ins-' . $insId);
