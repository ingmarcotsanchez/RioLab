<?php
/** Descarga de un documento desde el panel (respeta los retos asignados al mentor). */
require __DIR__ . '/_init.php';
require_login();
$d = q('SELECT e.*, i.reto_id FROM entregas e JOIN inscripciones i ON i.id = e.inscripcion_id WHERE e.id = ?', [(int) ($_GET['id'] ?? 0)])->fetch();
if (!$d || !puede_ver_reto($d['reto_id'] !== null ? (int) $d['reto_id'] : null)) not_found('Documento no encontrado.');
// Al abrir un documento recibido, pasa automáticamente a "en revisión"
if ($d['estado'] === 'recibido') q("UPDATE entregas SET estado = 'en_revision' WHERE id = ?", [$d['id']]);
upload_enviar($d);
