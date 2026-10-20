<?php
/**
 * Datos de demostración para probar el dashboard.
 * Todos los registros de ejemplo usan correos @demo.riolab.test y visitantes "demo…",
 * y se eliminan con:  php database/demo.php --limpiar
 */
declare(strict_types=1);

function demo_seed(PDO $pdo): void
{
    mt_srand(4020);
    $nombres = ['Ana', 'Luis', 'María', 'Carlos', 'Laura', 'Andrés', 'Diana', 'Jorge', 'Paola', 'Camilo', 'Sofía', 'Julián', 'Valentina', 'Mateo', 'Daniela', 'Sebastián', 'Natalia', 'Felipe', 'Juliana', 'Óscar'];
    $apellidos = ['Rodríguez', 'Gómez', 'Martínez', 'Díaz', 'Ramírez', 'Moreno', 'Rojas', 'Vargas', 'Castro', 'Ortiz', 'Suárez', 'Pérez', 'Murcia', 'Bermúdez'];
    $perfiles = ['Estudiante' => 50, 'Docente / Investigador' => 12, 'Pescador / Comunidad ribereña' => 20, 'Actor productivo / Empresa' => 8, 'Entidad pública u ONG' => 6, 'Otro' => 4];
    $municipios = ['Girardot' => 45, 'Flandes' => 18, 'Ricaurte' => 12, 'Nariño' => 6, 'Tocaima' => 8, 'Agua de Dios' => 5, 'Guataquí' => 3, 'Coello' => 3];
    $estados = ['pendiente' => 45, 'aceptado' => 30, 'en_curso' => 20, 'rechazado' => 5];
    $pick = function (array $w) { $r = mt_rand(1, array_sum($w)); foreach ($w as $k => $v) { if (($r -= $v) <= 0) return $k; } return array_key_first($w); };

    $retos = $pdo->query("SELECT id, cupos FROM retos WHERE estado = 'publicado'")->fetchAll();
    $hash = password_hash('demo', PASSWORD_DEFAULT);
    $stP = $pdo->prepare('INSERT INTO participantes (nombre, correo, telefono, municipio, perfil, codigo_hash, acepto_datos_at, created_at) VALUES (?,?,?,?,?,?,?,?)');
    $stI = $pdo->prepare('INSERT IGNORE INTO inscripciones (participante_id, reto_id, origen, estado, created_at) VALUES (?,?,?,?,?)');
    $stE = $pdo->prepare('INSERT INTO eventos (tipo, pagina, reto_id, visitante, dispositivo, referer_host, created_at) VALUES (?,?,?,?,?,?,?)');

    for ($n = 1; $n <= 140; $n++) {
        $dias = (int) floor(pow(mt_rand(0, 1000) / 1000, 1.6) * 75);   // más inscripciones recientes
        $fecha = date('Y-m-d H:i:s', strtotime("-{$dias} days " . mt_rand(7, 21) . ':' . mt_rand(10, 59)));
        $stP->execute([$nombres[array_rand($nombres)] . ' ' . $apellidos[array_rand($apellidos)], "persona{$n}@demo.riolab.test",
            '3' . mt_rand(100000000, 209999999), $pick($municipios), $pick($perfiles), $hash, $fecha, $fecha]);
        $pid = (int) $pdo->lastInsertId();
        $reto = mt_rand(1, 10) === 1 ? null : $retos[array_rand($retos)]['id'];
        $stI->execute([$pid, $reto, mt_rand(0, 1) ? 'reto' : 'inicio', $pick($estados), $fecha]);
    }

    $refs = ['' => 50, 'www.facebook.com' => 15, 'l.instagram.com' => 12, 'www.google.com' => 18, 'web.whatsapp.com' => 10, 'uniminuto.edu' => 6];
    $disp = ['movil' => 58, 'desktop' => 36, 'tablet' => 6];
    for ($d = 75; $d >= 0; $d--) {
        $base = 25 + (75 - $d) * 0.9 + (in_array((int) date('N', strtotime("-{$d} days")), [6, 7]) ? -8 : 0);
        $visitas = max(5, (int) ($base + mt_rand(-10, 18)));
        for ($v = 0; $v < $visitas; $v++) {
            $t = date('Y-m-d H:i:s', strtotime("-{$d} days " . mt_rand(6, 22) . ':' . mt_rand(0, 59)));
            $vis = 'demo' . substr(md5("$d-$v"), 0, 12);
            $dev = $pick($disp);
            $stE->execute(['visita', 'inicio', null, $vis, $dev, $pick($refs), $t]);
            if (mt_rand(1, 100) <= 55) {
                $rid = $retos[array_rand($retos)]['id'];
                $stE->execute(['visita', 'reto', $rid, $vis, $dev, '', $t]);
                if (mt_rand(1, 100) <= 18) $stE->execute(['pdf', 'reto', $rid, $vis, $dev, '', $t]);
            }
        }
    }
}

function demo_clear(PDO $pdo): void
{
    $pdo->exec("DELETE FROM participantes WHERE correo LIKE '%@demo.riolab.test'");
    $pdo->exec("DELETE FROM eventos WHERE visitante LIKE 'demo%'");
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $config = require __DIR__ . '/../config/config.php';
    $c = $config['db'];
    $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if (in_array('--limpiar', $argv, true)) { demo_clear($pdo); echo "✓ Datos de demostración eliminados\n"; }
    else { demo_seed($pdo); echo "✓ Datos de demostración agregados\n"; }
}
