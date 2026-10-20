<?php
/** El participante puede retirar un documento mientras no haya sido revisado. */
require __DIR__ . '/../app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('./');
csrf_check();
$yo = participante_actual();
if (!$yo) redirect('./');
$d = q("SELECT e.* FROM entregas e JOIN inscripciones i ON i.id = e.inscripcion_id
        WHERE e.id = ? AND i.participante_id = ? AND e.estado = 'recibido'", [(int) ($_POST['id'] ?? 0), $yo['id']])->fetch();
if (!$d) { flash('error', 'Este documento ya está en revisión y no se puede eliminar.'); redirect('./'); }
q('DELETE FROM entregas WHERE id = ?', [$d['id']]);
upload_borrar($d['archivo']);
flash('ok', 'Eliminaste «' . $d['nombre_original'] . '».');
redirect('./#ins-' . $d['inscripcion_id']);
