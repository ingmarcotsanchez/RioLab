<?php
require __DIR__ . '/../app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    unset($_SESSION['participante_id']);
    session_regenerate_id(true);
}
redirect('./');
