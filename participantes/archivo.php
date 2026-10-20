<?php
/** Descarga de un documento propio del participante. */
require __DIR__ . '/../app/bootstrap.php';
$yo = participante_actual();
if (!$yo) redirect('./');
$d = q('SELECT e.* FROM entregas e JOIN inscripciones i ON i.id = e.inscripcion_id WHERE e.id = ? AND i.participante_id = ?',
    [(int) ($_GET['id'] ?? 0), $yo['id']])->fetch();
if (!$d) not_found('Documento no encontrado.');
upload_enviar($d);
