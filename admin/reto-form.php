<?php
/** Crear / editar un reto. Incluye todos los campos de la página pública y los exclusivos del PDF. */
require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
$u = require_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$existente = $id ? q('SELECT * FROM retos WHERE id = ?', [$id])->fetch() : null;
if ($id && !$existente) not_found('Reto no encontrado.');
$metodologias = metodologias_todas();
$mentores = q("SELECT id, nombre, correo FROM usuarios WHERE rol = 'mentor' AND activo = 1 ORDER BY nombre")->fetchAll();

$lineas = fn($txt) => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $txt)), 'strlen'));

/* ---------- Valores del formulario ---------- */
if ($existente) {
    $v = $existente;
    foreach (RETO_JSON as $k) $v[$k] = jdecode($v[$k]);
    $v['entregables'] = q('SELECT id, titulo, descripcion, semana FROM reto_entregables WHERE reto_id = ? ORDER BY orden, id', [$id])->fetchAll();
    $v['mentores'] = array_map('intval', q('SELECT usuario_id FROM mentor_retos WHERE reto_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN));
} else {
    $sig = (int) q('SELECT COUNT(*) FROM retos')->fetchColumn() + 1;
    $v = ['titulo' => '', 'slug' => '', 'codigo' => sprintf('R-%02d', $sig), 'tag' => '', 'icono' => 'ola', 'estado' => 'borrador', 'orden' => $sig,
        'resumen' => '', 'contexto' => '', 'pregunta' => '', 'metodologia_id' => $metodologias[0]['id'] ?? 0, 'duracion' => '', 'dedicacion' => '',
        'modalidad' => 'Híbrida', 'modalidad_detalle' => '', 'cupos' => 30, 'equipos' => '', 'nivel' => '', 'fecha_inicio' => '', 'fecha_cierre' => '',
        'ods' => [], 'dirigido' => [], 'requisitos' => [], 'resultados' => [], 'competencias' => [], 'aliados' => [],
        'cronograma' => [['semanas' => '', 'titulo' => '', 'desc' => '']], 'evaluacion' => [['criterio' => '', 'peso' => '']],
        'entregables' => [['id' => '', 'titulo' => '', 'descripcion' => '', 'semana' => '']],
        'recursos' => [], 'compromisos' => [], 'lugar' => '', 'contacto_nombre' => '', 'contacto_correo' => '', 'info_adicional' => '', 'mentores' => []];
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $s = fn($k, $max = 255) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
    $v = array_merge($v, [
        'titulo' => $s('titulo', 160), 'slug' => slugify($s('slug', 80) ?: $s('titulo', 160)), 'codigo' => $s('codigo', 20), 'tag' => $s('tag', 60),
        'icono' => array_key_exists($_POST['icono'] ?? '', RETO_ICONOS) ? $_POST['icono'] : 'ola',
        'estado' => in_array($_POST['estado'] ?? '', ['borrador', 'publicado', 'cerrado'], true) ? $_POST['estado'] : 'borrador',
        'orden' => (int) ($_POST['orden'] ?? 0), 'resumen' => $s('resumen', 400), 'contexto' => $s('contexto', 8000), 'pregunta' => $s('pregunta', 500),
        'metodologia_id' => (int) ($_POST['metodologia_id'] ?? 0), 'duracion' => $s('duracion', 60), 'dedicacion' => $s('dedicacion', 60),
        'modalidad' => $s('modalidad', 60), 'modalidad_detalle' => $s('modalidad_detalle', 400), 'cupos' => (int) ($_POST['cupos'] ?? 0),
        'equipos' => $s('equipos', 120), 'nivel' => $s('nivel', 60), 'fecha_inicio' => $s('fecha_inicio', 10), 'fecha_cierre' => $s('fecha_cierre', 10),
        'ods' => array_values(array_filter(array_map('intval', (array) ($_POST['ods'] ?? [])), fn($n) => isset(ODS[$n]))),
        'dirigido' => $lineas($_POST['dirigido'] ?? ''), 'requisitos' => $lineas($_POST['requisitos'] ?? ''), 'resultados' => $lineas($_POST['resultados'] ?? ''),
        'competencias' => array_values(array_filter(array_map('trim', preg_split('/[,\n]/', (string) ($_POST['competencias'] ?? ''))), 'strlen')),
        'aliados' => $lineas($_POST['aliados'] ?? ''), 'recursos' => $lineas($_POST['recursos'] ?? ''), 'compromisos' => $lineas($_POST['compromisos'] ?? ''),
        'lugar' => $s('lugar'), 'contacto_nombre' => $s('contacto_nombre', 120), 'contacto_correo' => mb_strtolower($s('contacto_correo', 160)),
        'info_adicional' => $s('info_adicional', 8000),
        'mentores' => array_map('intval', (array) ($_POST['mentores'] ?? [])),
    ]);
    $rows = fn($k, $keys) => array_values(array_filter(array_map(
        fn($r) => array_combine($keys, array_map(fn($kk) => mb_substr(trim((string) ($r[$kk] ?? '')), 0, 500), $keys)),
        (array) ($_POST[$k] ?? [])), fn($r) => implode('', array_diff_key($r, ['id' => 1])) !== ''));
    $v['cronograma'] = $rows('cronograma', ['semanas', 'titulo', 'desc']);
    $v['entregables'] = $rows('entregables', ['id', 'titulo', 'descripcion', 'semana']);
    $v['evaluacion'] = array_map(fn($r) => ['criterio' => $r['criterio'], 'peso' => (int) $r['peso']], $rows('evaluacion', ['criterio', 'peso']));

    /* Validación */
    foreach (['titulo' => 'el título', 'codigo' => 'el código', 'tag' => 'la categoría', 'resumen' => 'el resumen', 'contexto' => 'el contexto',
              'pregunta' => 'la pregunta reto', 'duracion' => 'la duración', 'dedicacion' => 'la dedicación', 'modalidad' => 'la modalidad'] as $k => $t) {
        if ($v[$k] === '') $errors[$k] = "Escribe $t.";
    }
    if (q('SELECT 1 FROM retos WHERE slug = ? AND id <> ?', [$v['slug'], $id])->fetch()) $errors['slug'] = 'Ya existe otro reto con esta dirección.';
    if (!in_array($v['metodologia_id'], array_map('intval', array_column($metodologias, 'id')), true)) $errors['metodologia_id'] = 'Selecciona una metodología.';
    if ($v['cupos'] < 1 || $v['cupos'] > 2000) $errors['cupos'] = 'Indica un número de cupos entre 1 y 2000.';
    foreach (['fecha_inicio', 'fecha_cierre'] as $k) {
        if ($v[$k] !== '' && !DateTime::createFromFormat('Y-m-d', $v[$k])) $errors[$k] = 'Fecha inválida.';
    }
    if ($v['fecha_inicio'] && $v['fecha_cierre'] && $v['fecha_cierre'] > $v['fecha_inicio']) $errors['fecha_cierre'] = 'El cierre de inscripciones debe ser antes del inicio del reto.';
    if (!$v['entregables']) $errors['entregables'] = 'Agrega al menos un entregable.';
    foreach ($v['entregables'] as $e) if ($e['titulo'] === '') { $errors['entregables'] = 'Cada entregable necesita un título.'; break; }
    if ($v['evaluacion'] && array_sum(array_column($v['evaluacion'], 'peso')) !== 100) $errors['evaluacion'] = 'Los pesos de la evaluación deben sumar 100 %.';
    if ($v['contacto_correo'] !== '' && !filter_var($v['contacto_correo'], FILTER_VALIDATE_EMAIL)) $errors['contacto_correo'] = 'Correo inválido.';
    if ($v['estado'] === 'publicado') {
        if (!$v['resultados']) $errors['resultados'] = 'Para publicar, agrega al menos un resultado de aprendizaje.';
        if (!$v['cronograma']) $errors['cronograma'] = 'Para publicar, agrega el cronograma.';
    }

    if (!$errors) {
        $j = fn($x) => json_encode($x, JSON_UNESCAPED_UNICODE);
        $data = [
            'slug' => $v['slug'], 'codigo' => $v['codigo'], 'titulo' => $v['titulo'], 'tag' => $v['tag'], 'icono' => $v['icono'], 'resumen' => $v['resumen'],
            'contexto' => $v['contexto'], 'pregunta' => $v['pregunta'], 'metodologia_id' => $v['metodologia_id'], 'duracion' => $v['duracion'],
            'dedicacion' => $v['dedicacion'], 'modalidad' => $v['modalidad'], 'modalidad_detalle' => $v['modalidad_detalle'], 'cupos' => $v['cupos'],
            'equipos' => $v['equipos'], 'nivel' => $v['nivel'], 'fecha_inicio' => $v['fecha_inicio'] ?: null, 'fecha_cierre' => $v['fecha_cierre'] ?: null,
            'ods' => $j($v['ods']), 'dirigido' => $j($v['dirigido']), 'requisitos' => $j($v['requisitos']), 'resultados' => $j($v['resultados']),
            'competencias' => $j($v['competencias']), 'aliados' => $j($v['aliados']), 'cronograma' => $j($v['cronograma']), 'evaluacion' => $j($v['evaluacion']),
            'recursos' => $j($v['recursos']), 'compromisos' => $j($v['compromisos']), 'lugar' => $v['lugar'], 'contacto_nombre' => $v['contacto_nombre'],
            'contacto_correo' => $v['contacto_correo'], 'info_adicional' => $v['info_adicional'] ?: null, 'estado' => $v['estado'], 'orden' => $v['orden'],
        ];
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($id) {
                q('UPDATE retos SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($data))) . ' WHERE id = ?', array_merge(array_values($data), [$id]));
            } else {
                q('INSERT INTO retos (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')', array_values($data));
                $id = (int) $pdo->lastInsertId();
            }
            // Entregables: actualizar los existentes, crear los nuevos y borrar los retirados
            $actuales = array_map('intval', q('SELECT id FROM reto_entregables WHERE reto_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN));
            $vistos = [];
            foreach ($v['entregables'] as $i => $e) {
                $eid = (int) $e['id'];
                if ($eid && in_array($eid, $actuales, true)) {
                    q('UPDATE reto_entregables SET orden = ?, titulo = ?, descripcion = ?, semana = ? WHERE id = ?', [$i + 1, $e['titulo'], $e['descripcion'], $e['semana'], $eid]);
                    $vistos[] = $eid;
                } else {
                    q('INSERT INTO reto_entregables (reto_id, orden, titulo, descripcion, semana) VALUES (?,?,?,?,?)', [$id, $i + 1, $e['titulo'], $e['descripcion'], $e['semana']]);
                }
            }
            foreach (array_diff($actuales, $vistos) as $borrar) q('DELETE FROM reto_entregables WHERE id = ?', [$borrar]);
            // Mentores asignados
            q('DELETE FROM mentor_retos WHERE reto_id = ?', [$id]);
            $validos = array_map('intval', array_column($mentores, 'id'));
            foreach (array_intersect($v['mentores'], $validos) as $m) q('INSERT INTO mentor_retos (usuario_id, reto_id) VALUES (?, ?)', [$m, $id]);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        flash('ok', $existente ? 'Cambios guardados.' : 'Reto creado' . ($v['estado'] === 'publicado' ? ' y publicado.' : ' como borrador.'));
        redirect('reto-form.php?id=' . $id);
    }
    flash('error', 'Revisa los campos marcados: ' . count($errors) . ' por corregir.');
}

/* ---------- Ayudantes de render ---------- */
function err(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<p class="error" id="' . $k . '-err">' . e($errors[$k]) . '</p>' : '';
}
function aria(array $errors, string $k): string
{
    return isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="' . $k . '-err"' : '';
}
function fcls(array $errors, string $k, string $extra = ''): string
{
    return 'field' . ($extra ? " $extra" : '') . (isset($errors[$k]) ? ' has-error' : '');
}
$txt = fn(array $list) => e(implode("\n", $list));

$titulo = $existente ? 'Editar reto' : 'Crear reto';
$acciones = $existente && $existente['estado'] !== 'borrador'
    ? '<a class="btn btn-outline" href="../reto.php?r=' . e(urlencode($existente['slug'])) . '" target="_blank" rel="noopener">' . ui_icon('external') . 'Ver en el sitio</a>'
      . '<a class="btn btn-outline" href="../reto-pdf.php?r=' . e(urlencode($existente['slug'])) . '">' . ui_icon('pdf') . 'PDF</a>'
    : '';
admin_header($titulo, 'retos', ['back' => 'retos.php', 'actions' => $acciones]);
?>
<form method="post" class="form-layout" novalidate data-dirty-guard>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <nav class="form-toc" aria-label="Secciones del formulario">
    <a href="#s-basico">1. Información básica</a>
    <a href="#s-reto">2. El reto</a>
    <a href="#s-metodologia">3. Metodología y logística</a>
    <a href="#s-cronograma">4. Cronograma</a>
    <a href="#s-entregables">5. Entregables</a>
    <a href="#s-aprendizaje">6. Aprendizaje y evaluación</a>
    <a href="#s-participantes">7. Participantes</a>
    <a href="#s-pdf">8. Solo en el PDF</a>
    <a href="#s-mentores">9. Mentores</a>
  </nav>

  <div class="form-sections">
    <!-- 1 -->
    <section class="card fs" id="s-basico">
      <div class="fs-head"><span class="fs-num">1</span><div><h2 class="card-title">Información básica</h2><p>Lo que se ve en la tarjeta del reto en la página de inicio.</p></div></div>
      <div class="form-grid">
        <div class="<?= fcls($errors, 'titulo', 'span-all') ?>">
          <label for="titulo">Título del reto</label>
          <input id="titulo" name="titulo" type="text" maxlength="160" required value="<?= e($v['titulo']) ?>" data-slug-source<?= aria($errors, 'titulo') ?>>
          <?= err($errors, 'titulo') ?>
        </div>
        <div class="<?= fcls($errors, 'codigo') ?>">
          <label for="codigo">Código</label>
          <input id="codigo" name="codigo" type="text" maxlength="20" value="<?= e($v['codigo']) ?>"<?= aria($errors, 'codigo') ?>>
          <?= err($errors, 'codigo') ?>
        </div>
        <div class="<?= fcls($errors, 'tag') ?>">
          <label for="tag">Categoría</label>
          <input id="tag" name="tag" type="text" maxlength="60" placeholder="Ej. Sostenibilidad" value="<?= e($v['tag']) ?>"<?= aria($errors, 'tag') ?>>
          <?= err($errors, 'tag') ?>
        </div>
        <div class="<?= fcls($errors, 'slug') ?>">
          <label for="slug">Dirección web <span class="opt">(se genera sola)</span></label>
          <input id="slug" name="slug" type="text" maxlength="80" value="<?= e($v['slug']) ?>" data-slug-target<?= aria($errors, 'slug') ?>>
          <p class="hint">reto.php?r=<strong>esta-direccion</strong></p>
          <?= err($errors, 'slug') ?>
        </div>
        <div class="field">
          <label for="estado">Estado</label>
          <select id="estado" name="estado">
            <?php foreach (['borrador' => 'Borrador (no visible)', 'publicado' => 'Publicado (recibe inscripciones)', 'cerrado' => 'Cerrado (visible, sin inscripciones)'] as $k => $t): ?>
              <option value="<?= $k ?>"<?= $v['estado'] === $k ? ' selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="orden">Orden en el listado</label>
          <input id="orden" name="orden" type="number" min="0" max="999" value="<?= (int) $v['orden'] ?>">
        </div>
        <div class="<?= fcls($errors, 'resumen', 'span-all') ?>">
          <label for="resumen">Resumen <span class="opt">(máx. 400 caracteres)</span></label>
          <textarea id="resumen" name="resumen" rows="2" maxlength="400"<?= aria($errors, 'resumen') ?>><?= e($v['resumen']) ?></textarea>
          <?= err($errors, 'resumen') ?>
        </div>
        <fieldset class="field span-all" style="border:0;padding:0;margin:0">
          <legend class="label" style="margin-bottom:6px">Ícono</legend>
          <div class="icon-picker">
            <?php foreach (RETO_ICONOS as $k => [$label]): ?>
              <label title="<?= e($label) ?>"><input type="radio" name="icono" value="<?= $k ?>"<?= $v['icono'] === $k ? ' checked' : '' ?> aria-label="<?= e($label) ?>"><span><?= icon_svg($k) ?></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      </div>
    </section>

    <!-- 2 -->
    <section class="card fs" id="s-reto">
      <div class="fs-head"><span class="fs-num">2</span><div><h2 class="card-title">El reto</h2><p>Sección «¿Qué vamos a resolver?» de la página del reto.</p></div></div>
      <div class="form-grid">
        <div class="<?= fcls($errors, 'contexto', 'span-all') ?>">
          <label for="contexto">Contexto y problemática</label>
          <textarea id="contexto" name="contexto" rows="7"<?= aria($errors, 'contexto') ?>><?= e($v['contexto']) ?></textarea>
          <p class="hint">Deja una línea en blanco para separar párrafos.</p>
          <?= err($errors, 'contexto') ?>
        </div>
        <div class="<?= fcls($errors, 'pregunta', 'span-all') ?>">
          <label for="pregunta">Pregunta reto</label>
          <textarea id="pregunta" name="pregunta" rows="2" maxlength="500" placeholder="¿Cómo podríamos…?"<?= aria($errors, 'pregunta') ?>><?= e($v['pregunta']) ?></textarea>
          <?= err($errors, 'pregunta') ?>
        </div>
        <fieldset class="field span-all" style="border:0;padding:0;margin:0">
          <legend class="label" style="margin-bottom:6px">Objetivos de Desarrollo Sostenible</legend>
          <div class="ods-picker">
            <?php foreach (ODS as $n => [$nombre, $color]): ?>
              <label><input type="checkbox" name="ods[]" value="<?= $n ?>"<?= in_array($n, $v['ods'], true) ? ' checked' : '' ?>><span class="ods-n" style="--ods: <?= $color ?>"><?= $n ?></span><?= e($nombre) ?></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      </div>
    </section>

    <!-- 3 -->
    <section class="card fs" id="s-metodologia">
      <div class="fs-head"><span class="fs-num">3</span><div><h2 class="card-title">Metodología y logística</h2><p>Ficha del reto: metodología, duración, modalidad, cupos y fechas.</p></div></div>
      <div class="form-grid">
        <div class="<?= fcls($errors, 'metodologia_id', 'span-all') ?>">
          <label for="metodologia_id">Metodología de aprendizaje</label>
          <select id="metodologia_id" name="metodologia_id"<?= aria($errors, 'metodologia_id') ?>>
            <?php foreach ($metodologias as $m): ?><option value="<?= (int) $m['id'] ?>"<?= (int) $v['metodologia_id'] === (int) $m['id'] ? ' selected' : '' ?>><?= e($m['nombre'] . ($m['sigla'] ? ' — ' . $m['sigla'] : '')) ?></option><?php endforeach; ?>
          </select>
          <p class="hint">Las fases de cada metodología se administran en <a href="metodologias.php" target="_blank">Metodologías</a>.</p>
          <?= err($errors, 'metodologia_id') ?>
        </div>
        <div class="<?= fcls($errors, 'duracion') ?>">
          <label for="duracion">Duración</label>
          <input id="duracion" name="duracion" type="text" placeholder="Ej. 12 semanas" value="<?= e($v['duracion']) ?>"<?= aria($errors, 'duracion') ?>>
          <?= err($errors, 'duracion') ?>
        </div>
        <div class="<?= fcls($errors, 'dedicacion') ?>">
          <label for="dedicacion">Dedicación</label>
          <input id="dedicacion" name="dedicacion" type="text" placeholder="Ej. 6 horas por semana" value="<?= e($v['dedicacion']) ?>"<?= aria($errors, 'dedicacion') ?>>
          <?= err($errors, 'dedicacion') ?>
        </div>
        <div class="<?= fcls($errors, 'modalidad') ?>">
          <label for="modalidad">Modalidad</label>
          <select id="modalidad" name="modalidad">
            <?php foreach (MODALIDADES as $m): ?><option<?= $v['modalidad'] === $m ? ' selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="nivel">Nivel</label>
          <input id="nivel" name="nivel" type="text" placeholder="Ej. Intermedio" value="<?= e($v['nivel']) ?>">
        </div>
        <div class="field span-all">
          <label for="modalidad_detalle">Detalle de la modalidad</label>
          <input id="modalidad_detalle" name="modalidad_detalle" type="text" maxlength="400" placeholder="Ej. Sesiones virtuales semanales y 4 salidas de campo…" value="<?= e($v['modalidad_detalle']) ?>">
        </div>
        <div class="<?= fcls($errors, 'cupos') ?>">
          <label for="cupos">Cupos</label>
          <input id="cupos" name="cupos" type="number" min="1" max="2000" value="<?= (int) $v['cupos'] ?>"<?= aria($errors, 'cupos') ?>>
          <?= err($errors, 'cupos') ?>
        </div>
        <div class="field">
          <label for="equipos">Conformación de equipos</label>
          <input id="equipos" name="equipos" type="text" placeholder="Ej. 6 equipos de 5 integrantes" value="<?= e($v['equipos']) ?>">
        </div>
        <div class="<?= fcls($errors, 'fecha_cierre') ?>">
          <label for="fecha_cierre">Cierre de inscripciones</label>
          <input id="fecha_cierre" name="fecha_cierre" type="date" value="<?= e($v['fecha_cierre']) ?>"<?= aria($errors, 'fecha_cierre') ?>>
          <?= err($errors, 'fecha_cierre') ?>
        </div>
        <div class="<?= fcls($errors, 'fecha_inicio') ?>">
          <label for="fecha_inicio">Fecha de inicio</label>
          <input id="fecha_inicio" name="fecha_inicio" type="date" value="<?= e($v['fecha_inicio']) ?>"<?= aria($errors, 'fecha_inicio') ?>>
          <?= err($errors, 'fecha_inicio') ?>
        </div>
      </div>
    </section>

    <!-- 4 -->
    <section class="card fs" id="s-cronograma" data-repeater>
      <div class="fs-head"><span class="fs-num">4</span><div><h2 class="card-title">Cronograma</h2><p>Fases del reto por semanas.</p></div></div>
      <?= err($errors, 'cronograma') ?>
      <div class="repeater">
        <?php foreach ($v['cronograma'] ?: [['semanas' => '', 'titulo' => '', 'desc' => '']] as $i => $c): ?>
          <div class="rep-row">
            <div class="field"><label>Semanas</label><input type="text" name="cronograma[<?= $i ?>][semanas]" placeholder="1–2" value="<?= e($c['semanas']) ?>"></div>
            <div class="field"><label>Fase</label><input type="text" name="cronograma[<?= $i ?>][titulo]" value="<?= e($c['titulo']) ?>"></div>
            <div class="field wide"><label>Actividades</label><input type="text" name="cronograma[<?= $i ?>][desc]" value="<?= e($c['desc']) ?>"></div>
            <button type="button" class="icon-btn rep-del" aria-label="Quitar fase"><?= ui_icon('x') ?></button>
          </div>
        <?php endforeach; ?>
      </div>
      <template><div class="rep-row">
        <div class="field"><label>Semanas</label><input type="text" name="cronograma[0][semanas]" placeholder="1–2"></div>
        <div class="field"><label>Fase</label><input type="text" name="cronograma[0][titulo]"></div>
        <div class="field wide"><label>Actividades</label><input type="text" name="cronograma[0][desc]"></div>
        <button type="button" class="icon-btn rep-del" aria-label="Quitar fase"><?= ui_icon('x') ?></button>
      </div></template>
      <button type="button" class="btn btn-outline btn-sm rep-add" style="margin-top:10px"><?= ui_icon('plus') ?>Agregar fase</button>
    </section>

    <!-- 5 -->
    <section class="card fs" id="s-entregables" data-repeater>
      <div class="fs-head"><span class="fs-num">5</span><div><h2 class="card-title">Entregables</h2><p>Cada entregable tendrá su propio espacio de carga en «Mis entregas».</p></div></div>
      <?= err($errors, 'entregables') ?>
      <div class="repeater">
        <?php foreach ($v['entregables'] ?: [['id' => '', 'titulo' => '', 'descripcion' => '', 'semana' => '']] as $i => $en): ?>
          <div class="rep-row">
            <input type="hidden" name="entregables[<?= $i ?>][id]" value="<?= e($en['id']) ?>">
            <div class="field"><label>Título</label><input type="text" name="entregables[<?= $i ?>][titulo]" value="<?= e($en['titulo']) ?>"></div>
            <div class="field wide"><label>Descripción</label><input type="text" name="entregables[<?= $i ?>][descripcion]" value="<?= e($en['descripcion']) ?>"></div>
            <div class="field"><label>Entrega</label><input type="text" name="entregables[<?= $i ?>][semana]" placeholder="Semana 3" value="<?= e($en['semana']) ?>"></div>
            <button type="button" class="icon-btn rep-del" aria-label="Quitar entregable"><?= ui_icon('x') ?></button>
          </div>
        <?php endforeach; ?>
      </div>
      <template><div class="rep-row">
        <input type="hidden" name="entregables[0][id]" value="">
        <div class="field"><label>Título</label><input type="text" name="entregables[0][titulo]"></div>
        <div class="field wide"><label>Descripción</label><input type="text" name="entregables[0][descripcion]"></div>
        <div class="field"><label>Entrega</label><input type="text" name="entregables[0][semana]" placeholder="Semana 3"></div>
        <button type="button" class="icon-btn rep-del" aria-label="Quitar entregable"><?= ui_icon('x') ?></button>
      </div></template>
      <button type="button" class="btn btn-outline btn-sm rep-add" style="margin-top:10px"><?= ui_icon('plus') ?>Agregar entregable</button>
      <p class="hint" style="margin-top:8px">Si quitas un entregable que ya tiene documentos, estos se conservan como «Otros documentos».</p>
    </section>

    <!-- 6 -->
    <section class="card fs" id="s-aprendizaje">
      <div class="fs-head"><span class="fs-num">6</span><div><h2 class="card-title">Aprendizaje y evaluación</h2><p>Resultados de aprendizaje, competencias y rúbrica.</p></div></div>
      <div class="form-grid">
        <div class="<?= fcls($errors, 'resultados', 'span-all') ?>">
          <label for="resultados">Resultados de aprendizaje <span class="opt">(uno por línea)</span></label>
          <textarea id="resultados" name="resultados" rows="5"<?= aria($errors, 'resultados') ?>><?= $txt($v['resultados']) ?></textarea>
          <?= err($errors, 'resultados') ?>
        </div>
        <div class="field span-all">
          <label for="competencias">Competencias <span class="opt">(separadas por coma)</span></label>
          <input id="competencias" name="competencias" type="text" value="<?= e(implode(', ', $v['competencias'])) ?>">
        </div>
      </div>
      <div data-repeater style="margin-top:18px">
        <p class="label" style="margin-bottom:8px">Criterios de evaluación</p>
        <?= err($errors, 'evaluacion') ?>
        <div class="repeater">
          <?php foreach ($v['evaluacion'] ?: [['criterio' => '', 'peso' => '']] as $i => $ev): ?>
            <div class="rep-row">
              <div class="field wide"><label>Criterio</label><input type="text" name="evaluacion[<?= $i ?>][criterio]" value="<?= e($ev['criterio']) ?>"></div>
              <div class="field"><label>Peso (%)</label><input type="number" min="0" max="100" name="evaluacion[<?= $i ?>][peso]" value="<?= e($ev['peso']) ?>" data-peso></div>
              <button type="button" class="icon-btn rep-del" aria-label="Quitar criterio"><?= ui_icon('x') ?></button>
            </div>
          <?php endforeach; ?>
        </div>
        <template><div class="rep-row">
          <div class="field wide"><label>Criterio</label><input type="text" name="evaluacion[0][criterio]"></div>
          <div class="field"><label>Peso (%)</label><input type="number" min="0" max="100" name="evaluacion[0][peso]" data-peso></div>
          <button type="button" class="icon-btn rep-del" aria-label="Quitar criterio"><?= ui_icon('x') ?></button>
        </div></template>
        <div class="actions" style="margin-top:10px; justify-content: space-between">
          <button type="button" class="btn btn-outline btn-sm rep-add"><?= ui_icon('plus') ?>Agregar criterio</button>
          <span class="rep-total" aria-live="polite"></span>
        </div>
      </div>
    </section>

    <!-- 7 -->
    <section class="card fs" id="s-participantes">
      <div class="fs-head"><span class="fs-num">7</span><div><h2 class="card-title">Participantes</h2><p>A quién va dirigido, requisitos y aliados (uno por línea).</p></div></div>
      <div class="form-grid">
        <div class="field"><label for="dirigido">Dirigido a</label><textarea id="dirigido" name="dirigido" rows="5"><?= $txt($v['dirigido']) ?></textarea></div>
        <div class="field"><label for="requisitos">Requisitos</label><textarea id="requisitos" name="requisitos" rows="5"><?= $txt($v['requisitos']) ?></textarea></div>
        <div class="field span-all"><label for="aliados">Aliados y acompañamiento</label><textarea id="aliados" name="aliados" rows="3"><?= $txt($v['aliados']) ?></textarea></div>
      </div>
    </section>

    <!-- 8 -->
    <section class="card fs fs--pdf" id="s-pdf">
      <div class="fs-head"><span class="fs-num">8</span><div><h2 class="card-title">Información exclusiva del PDF</h2><p>No aparece en la página; se agrega a la ficha descargable del reto.</p></div></div>
      <div class="form-grid">
        <div class="field"><label for="recursos">Recursos disponibles <span class="opt">(uno por línea)</span></label><textarea id="recursos" name="recursos" rows="4"><?= $txt($v['recursos']) ?></textarea></div>
        <div class="field"><label for="compromisos">Compromisos del participante <span class="opt">(uno por línea)</span></label><textarea id="compromisos" name="compromisos" rows="4"><?= $txt($v['compromisos']) ?></textarea></div>
        <div class="field span-all"><label for="lugar">Lugar de encuentro</label><input id="lugar" name="lugar" type="text" value="<?= e($v['lugar']) ?>"></div>
        <div class="field"><label for="contacto_nombre">Contacto del reto</label><input id="contacto_nombre" name="contacto_nombre" type="text" placeholder="Nombre y cargo" value="<?= e($v['contacto_nombre']) ?>"></div>
        <div class="<?= fcls($errors, 'contacto_correo') ?>"><label for="contacto_correo">Correo de contacto</label><input id="contacto_correo" name="contacto_correo" type="email" value="<?= e($v['contacto_correo']) ?>"<?= aria($errors, 'contacto_correo') ?>><?= err($errors, 'contacto_correo') ?></div>
        <div class="field span-all"><label for="info_adicional">Información adicional</label><textarea id="info_adicional" name="info_adicional" rows="4" placeholder="Horarios, materiales que deben traer, condiciones especiales…"><?= e($v['info_adicional']) ?></textarea></div>
      </div>
    </section>

    <!-- 9 -->
    <section class="card fs" id="s-mentores">
      <div class="fs-head"><span class="fs-num">9</span><div><h2 class="card-title">Mentores asignados</h2><p>Podrán ver los inscritos y revisar las entregas de este reto.</p></div></div>
      <?php if ($mentores): ?>
        <div class="ods-picker">
          <?php foreach ($mentores as $m): ?>
            <label><input type="checkbox" name="mentores[]" value="<?= (int) $m['id'] ?>"<?= in_array((int) $m['id'], $v['mentores'], true) ? ' checked' : '' ?>><span><strong><?= e($m['nombre']) ?></strong><span class="cell-sub"><?= e($m['correo']) ?></span></span></label>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted">Aún no hay mentores. Créalos en <a href="usuarios.php">Usuarios del panel</a>.</p>
      <?php endif; ?>
    </section>

    <div class="savebar">
      <span class="muted small"><?= $existente ? 'Última edición: ' . e(fecha_corta($existente['updated_at'] ?? $existente['created_at'])) : 'Los retos nuevos se crean como borrador si no cambias el estado.' ?></span>
      <a class="btn btn-ghost" href="retos.php">Cancelar</a>
      <button class="btn btn-primary" type="submit"><?= $existente ? 'Guardar cambios' : 'Crear reto' ?></button>
    </div>
  </div>
</form>
<?php admin_footer();
