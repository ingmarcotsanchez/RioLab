<?php
/**
 * RioLab 4.0 — Instalador (ejecutar desde la terminal)
 *
 *   php database/install.php
 *
 * 1. Crea la base de datos y las tablas (si no existen).
 * 2. Carga las metodologías y los 6 retos iniciales (solo si la tabla está vacía).
 * 3. Crea el primer usuario administrador.
 *
 * Opciones:
 *   --admin-email=correo  --admin-name="Nombre"  --admin-pass=clave   (modo no interactivo)
 *   --demo   Agrega inscripciones y visitas de ejemplo para probar el dashboard.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo desde la terminal.'); }

$opts = getopt('', ['admin-email:', 'admin-name:', 'admin-pass:', 'demo']);
$config = require __DIR__ . '/../config/config.php';
$c = $config['db'];

function out(string $msg): void { fwrite(STDOUT, $msg . PHP_EOL); }
function ask(string $label, bool $hidden = false): string
{
    fwrite(STDOUT, $label);
    if ($hidden && DIRECTORY_SEPARATOR === '/') shell_exec('stty -echo');
    $v = trim((string) fgets(STDIN));
    if ($hidden && DIRECTORY_SEPARATOR === '/') { shell_exec('stty echo'); fwrite(STDOUT, PHP_EOL); }
    return $v;
}

/* ---------- 1. Base de datos y tablas ---------- */
try {
    $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['user'], $c['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (PDOException $e) {
    out('✗ No se pudo conectar a MySQL: ' . $e->getMessage());
    out('  Revisa los datos de conexión en config/config.local.php (cópialo de config.local.example.php).');
    exit(1);
}
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$c['name']}`");
foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', file_get_contents(__DIR__ . '/schema.sql'))))) as $sql) {
    $pdo->exec($sql);
}
out("✓ Base de datos «{$c['name']}» y tablas listas");

/* ---------- 2. Datos iniciales ---------- */
if ((int) $pdo->query('SELECT COUNT(*) FROM retos')->fetchColumn() === 0) {
    $seed = json_decode(file_get_contents(__DIR__ . '/seed-data.json'), true);

    $metIds = [];
    $st = $pdo->prepare('INSERT INTO metodologias (clave, nombre, sigla, descripcion, fases) VALUES (?,?,?,?,?)');
    foreach ($seed['metodologias'] as $clave => $m) {
        $st->execute([$clave, $m['nombre'], $m['sigla'], $m['descripcion'], json_encode($m['fases'], JSON_UNESCAPED_UNICODE)]);
        $metIds[$clave] = (int) $pdo->lastInsertId();
    }

    $iconos = ['r01' => 'qr', 'r02' => 'gota', 'r03' => 'ciclo', 'r04' => 'movil', 'r05' => 'libro', 'r06' => 'hoja'];
    $meses = ['enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12];
    $toDate = function (string $txt, int $defaultDay) use ($meses): ?string {
        if (!preg_match('/(?:(\d{1,2}) de )?(\p{L}+) de (\d{4})/u', mb_strtolower($txt), $m) || !isset($meses[$m[2]])) return null;
        return sprintf('%04d-%02d-%02d', $m[3], $meses[$m[2]], $m[1] !== '' ? (int) $m[1] : $defaultDay);
    };
    $j = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE);

    $recursos = ['Espacios de laboratorio y aulas del Centro Universitario Girardot', 'Mentoría semanal de un docente de UNIMINUTO', 'Material de apoyo y lecturas en el aula virtual', 'Acompañamiento de líderes de la comunidad pesquera en las salidas de campo'];
    $compromisos = ['Asistir como mínimo al 80 % de las sesiones programadas', 'Respetar los acuerdos, tiempos y saberes de las comunidades', 'Cargar los entregables en «Mis entregas» dentro de las fechas establecidas', 'Usar los elementos de protección durante el trabajo de campo', 'Compartir los resultados con las comunidades participantes'];

    $sql = 'INSERT INTO retos (slug, codigo, titulo, tag, icono, resumen, contexto, pregunta, metodologia_id, duracion, dedicacion,
              modalidad, modalidad_detalle, cupos, equipos, nivel, fecha_inicio, fecha_cierre, ods, dirigido, requisitos, resultados,
              competencias, aliados, cronograma, evaluacion, recursos, compromisos, lugar, estado, orden)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
    $st = $pdo->prepare($sql);
    $stEnt = $pdo->prepare('INSERT INTO reto_entregables (reto_id, orden, titulo, descripcion, semana) VALUES (?,?,?,?,?)');
    $orden = 0;
    foreach ($seed['retos'] as $key => $r) {
        $orden++;
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtr(mb_strtolower($r['titulo']), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n'])), '-');
        $st->execute([
            $slug, $r['codigo'], $r['titulo'], $r['tag'], $iconos[$key] ?? 'ola', $r['resumen'], implode("\n\n", $r['contexto']),
            $r['pregunta'], $metIds[$r['metodologia']], $r['duracion'], $r['dedicacion'], $r['modalidad'], $r['modalidadDetalle'],
            $r['cupos'], $r['equipos'], $r['nivel'], $toDate($r['inicio'], 9), $toDate($r['cierre'], 28),
            $j(array_column($r['ods'], 'n')), $j($r['dirigido']), $j($r['requisitos']), $j($r['resultados']), $j($r['competencias']),
            $j($r['aliados']), $j($r['cronograma']), $j($r['evaluacion']), $j($recursos), $j($compromisos),
            'Centro Universitario Girardot de UNIMINUTO y territorios ribereños del Alto Magdalena', 'publicado', $orden,
        ]);
        $retoId = (int) $pdo->lastInsertId();
        foreach ($r['entregables'] as $i => $e) $stEnt->execute([$retoId, $i + 1, $e['titulo'], $e['desc'], $e['semana']]);
    }
    out('✓ Metodologías y ' . count($seed['retos']) . ' retos iniciales cargados');
} else {
    out('• Ya existen retos: no se cargaron datos iniciales');
}

/* ---------- 3. Administrador ---------- */
if ((int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn() === 0) {
    out('');
    out('Crea el primer usuario administrador del panel:');
    $name  = $opts['admin-name']  ?? ask('  Nombre: ');
    $email = $opts['admin-email'] ?? ask('  Correo: ');
    $pass  = $opts['admin-pass']  ?? ask('  Contraseña (mín. 10 caracteres): ', true);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($pass) < 10 || $name === '') {
        out('✗ Datos inválidos: revisa el correo y usa una contraseña de al menos 10 caracteres.');
        exit(1);
    }
    $pdo->prepare("INSERT INTO usuarios (nombre, correo, password_hash, rol) VALUES (?,?,?, 'admin')")
        ->execute([$name, strtolower($email), password_hash($pass, PASSWORD_DEFAULT)]);
    out("✓ Administrador {$email} creado");
} else {
    out('• Ya existe un administrador');
}

/* ---------- Datos de demostración (opcional) ---------- */
if (isset($opts['demo'])) {
    require __DIR__ . '/demo.php';
    demo_seed($pdo);
    out('✓ Datos de demostración agregados (inscripciones y visitas de ejemplo)');
}

$dir = $config['uploads']['dir'];
if (!is_dir($dir)) mkdir($dir, 0775, true);
out('');
out('Listo. Ingresa al panel en: ' . rtrim($config['base_url'], '/') . '/admin/');
