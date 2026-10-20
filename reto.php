<?php
require __DIR__ . '/app/bootstrap.php';

$reto = reto_por_slug((string) ($_GET['r'] ?? ''));
if (!$reto) {
    http_response_code(404);
}
if ($reto) track('visita', 'reto', (int) $reto['id']);

$met = $reto['metodologia'] ?? null;
$title = $reto ? $reto['titulo'] . ' · RioLab 4.0' : 'Reto no encontrado · RioLab 4.0';
$description = $reto['resumen'] ?? null;
$bodyClass = 'page-reto';
$extraCss = ['css/reto.css'];
$navActive = 'retos';
$navCtaHref = $reto && $reto['abierto'] ? '#inscripcion' : null;
$scripts = ['js/inscripcion.js', 'js/reto.js'];
$arrow = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$pdfIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M12 11v6m-3-3 3 3 3-3"/></svg>';
$pdfUrl = $reto ? 'reto-pdf.php?r=' . urlencode($reto['slug']) : '';
$otros = array_values(array_filter(retos_publicos(), fn($r) => !$reto || $r['id'] !== $reto['id']));

require ROOT . '/app/views/public_header.php';
?>

  <main id="main">
<?php if (!$reto): ?>
    <section class="rh" aria-labelledby="reto-title">
      <div class="container rh-inner">
        <h1 id="reto-title" class="rh-title">No encontramos este reto</h1>
        <p class="rh-lead">Es posible que el enlace esté incompleto o que el reto ya no esté disponible.</p>
        <div class="rh-actions"><a href="index.php#retos" class="btn btn-sun btn-lg">Ver todos los retos <?= $arrow ?></a></div>
      </div>
    </section>
<?php else: ?>
    <!-- HERO DEL RETO -->
    <section class="rh" aria-labelledby="reto-title">
      <div class="rh-bg" aria-hidden="true">
        <svg class="rh-waves" viewBox="0 0 1440 400" preserveAspectRatio="none">
          <path d="M0 260c160-50 320-50 480 0s320 50 480 0 320-50 480 0"/>
          <path d="M0 300c160-50 320-50 480 0s320 50 480 0 320-50 480 0"/>
          <path d="M0 340c160-50 320-50 480 0s320 50 480 0 320-50 480 0"/>
        </svg>
      </div>
      <div class="container rh-inner">
        <nav class="crumbs reveal" aria-label="Ruta de navegación">
          <ol>
            <li><a href="index.php">Inicio</a></li>
            <li><a href="index.php#retos">Retos</a></li>
            <li aria-current="page"><?= e($reto['codigo']) ?></li>
          </ol>
        </nav>

        <div class="rh-grid">
          <div>
            <div class="rh-chips reveal">
              <span class="reto-tag"><?= e($reto['tag']) ?></span>
              <span class="rh-code"><?= e($reto['codigo']) ?></span>
            </div>
            <h1 id="reto-title" class="rh-title reveal"><?= e($reto['titulo']) ?></h1>
            <p class="rh-lead reveal"><?= e($reto['resumen']) ?></p>
            <div class="rh-actions reveal">
              <?php if ($reto['abierto']): ?>
                <a href="#inscripcion" class="btn btn-sun btn-lg">Inscribirme a este reto <?= $arrow ?></a>
              <?php endif; ?>
              <a href="<?= e($pdfUrl) ?>" class="btn btn-ghost btn-lg"><?= $pdfIcon ?> Descargar ficha PDF</a>
            </div>
          </div>
          <div class="rh-icon reveal" aria-hidden="true"><?= icon_svg($reto['icono']) ?></div>
        </div>

        <dl class="rh-facts reveal">
          <div>
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            <dt>Duración</dt><dd><?= e($reto['duracion']) ?></dd>
          </div>
          <div>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
            <dt>Modalidad</dt><dd><?= e($reto['modalidad']) ?></dd>
          </div>
          <div>
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/></svg>
            <dt>Cupos</dt><dd><?= (int) $reto['cupos'] ?> participantes</dd>
          </div>
          <div>
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            <dt>Inicio</dt><dd><?= e(mes_anio($reto['fecha_inicio'])) ?></dd>
          </div>
        </dl>
      </div>
    </section>

    <!-- SUBNAV DE SECCIONES -->
    <nav class="subnav" aria-label="Secciones del reto">
      <div class="container">
        <ul>
          <li><a href="#el-reto">El reto</a></li>
          <li><a href="#metodologia">Metodología</a></li>
          <li><a href="#cronograma">Cronograma</a></li>
          <li><a href="#entregables">Entregables</a></li>
          <li><a href="#aprendizajes">Aprendizajes</a></li>
          <li><a href="#evaluacion">Evaluación</a></li>
          <li><a href="#participantes">Participantes</a></li>
          <li><a href="#inscripcion">Inscripción</a></li>
          <li><a href="#faq">Preguntas</a></li>
        </ul>
      </div>
    </nav>

    <div class="container rb">
      <article class="rb-main">
        <section id="el-reto" class="block reveal" aria-labelledby="h-reto">
          <p class="kicker">El reto</p>
          <h2 id="h-reto" class="block-title">¿Qué vamos a resolver?</h2>
          <div class="prose">
            <?php foreach ($reto['contexto_parrafos'] as $p): ?><p><?= e($p) ?></p><?php endforeach; ?>
          </div>
          <blockquote class="hmw">
            <span class="hmw-label">Pregunta reto</span>
            <p><?= e($reto['pregunta']) ?></p>
          </blockquote>
          <?php if ($reto['ods']): ?>
          <div class="ods">
            <h3 class="mini-title">Objetivos de Desarrollo Sostenible</h3>
            <ul class="ods-list">
              <?php foreach ($reto['ods'] as $n): if (!isset(ODS[$n])) continue; ?>
                <li class="ods-item" style="--ods: <?= ODS[$n][1] ?>"><span class="ods-n">ODS <?= (int) $n ?></span><span><?= e(ODS[$n][0]) ?></span></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>
        </section>

        <section id="metodologia" class="block reveal" aria-labelledby="h-met">
          <p class="kicker">Metodología</p>
          <div class="met-card">
            <div class="met-head">
              <span class="met-badge"><?= icon_svg('bombillo') ?></span>
              <div>
                <h2 id="h-met" class="block-title"><?= e($met['nombre']) ?></h2>
                <p class="met-sigla"><?= e($met['sigla']) ?></p>
              </div>
            </div>
            <p class="met-desc"><?= e($met['descripcion']) ?></p>
            <ol class="phases">
              <?php foreach ($met['fases'] as $i => $f): ?>
                <li class="phase">
                  <span class="phase-n"><?= sprintf('%02d', $i + 1) ?></span>
                  <h3><?= e($f['nombre']) ?></h3>
                  <p><?= e($f['desc']) ?></p>
                </li>
              <?php endforeach; ?>
            </ol>
          </div>
        </section>

        <section id="cronograma" class="block reveal" aria-labelledby="h-cro">
          <p class="kicker">Cronograma</p>
          <h2 id="h-cro" class="block-title">Ruta de <?= e($reto['duracion']) ?></h2>
          <p class="mode-note">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
            <span><strong><?= e($reto['modalidad']) ?></strong><?= $reto['modalidad_detalle'] ? ' · ' . e($reto['modalidad_detalle']) : '' ?> Dedicación estimada: <?= e($reto['dedicacion']) ?>.</span>
          </p>
          <ol class="timeline">
            <?php foreach ($reto['cronograma'] as $c): ?>
              <li class="tl-item">
                <span class="tl-week">Semana<?= str_contains($c['semanas'], '–') || str_contains($c['semanas'], '-') ? 's' : '' ?> <?= e($c['semanas']) ?></span>
                <div class="tl-body"><h3><?= e($c['titulo']) ?></h3><p><?= e($c['desc']) ?></p></div>
              </li>
            <?php endforeach; ?>
          </ol>
        </section>

        <section id="entregables" class="block reveal" aria-labelledby="h-ent">
          <p class="kicker">Entregables</p>
          <h2 id="h-ent" class="block-title">Lo que construirá tu equipo</h2>
          <ol class="deliverables">
            <?php foreach ($reto['entregables'] as $i => $en): ?>
              <li class="deliv">
                <div class="deliv-top"><span class="deliv-n">E<?= $i + 1 ?></span><span class="deliv-when"><?= e($en['semana']) ?></span></div>
                <h3><?= e($en['titulo']) ?></h3>
                <p><?= e($en['descripcion']) ?></p>
              </li>
            <?php endforeach; ?>
          </ol>
          <p class="upload-note">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m-5 5 5-5 5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
            <span>Los entregables se cargan en <a href="<?= url('participantes/') ?>">Mis entregas</a> con tu correo y el código de acceso que recibes al inscribirte.</span>
          </p>
        </section>

        <section id="aprendizajes" class="block reveal" aria-labelledby="h-ra">
          <p class="kicker">Resultados de aprendizaje</p>
          <h2 id="h-ra" class="block-title">Al finalizar el reto, el participante…</h2>
          <ol class="outcomes">
            <?php foreach ($reto['resultados'] as $i => $ra): ?>
              <li class="outcome"><span class="outcome-n">RA<?= $i + 1 ?></span><p><?= e($ra) ?></p></li>
            <?php endforeach; ?>
          </ol>
          <?php if ($reto['competencias']): ?>
          <div class="skills">
            <h3 class="mini-title">Competencias que fortalecerás</h3>
            <ul class="chip-list"><?php foreach ($reto['competencias'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>
        </section>

        <section id="evaluacion" class="block reveal" aria-labelledby="h-eva">
          <p class="kicker">Evaluación</p>
          <h2 id="h-eva" class="block-title">¿Cómo se valora el trabajo?</h2>
          <ul class="rubric">
            <?php foreach ($reto['evaluacion'] as $ev): ?>
              <li class="rubric-item">
                <div class="rubric-row"><span><?= e($ev['criterio']) ?></span><strong><?= (int) $ev['peso'] ?>%</strong></div>
                <span class="rubric-bar" aria-hidden="true"><i style="--w:<?= min(100, (int) $ev['peso'] * 2.5) ?>%"></i></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="cert">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-2.5 5 2.5-1.5-7"/></svg>
            <div>
              <h3>Certificación</h3>
              <p>Quienes cumplan con el 80 % de asistencia y presenten los entregables recibirán un certificado de participación del Centro Universitario Girardot de UNIMINUTO. Los mejores equipos serán reconocidos en el Demo Day RioLab.</p>
            </div>
          </div>
        </section>

        <section id="participantes" class="block reveal" aria-labelledby="h-par">
          <p class="kicker">Participantes</p>
          <h2 id="h-par" class="block-title">¿Quiénes pueden sumarse?</h2>
          <div class="two-col">
            <div class="panel">
              <h3 class="mini-title">Dirigido a</h3>
              <ul class="ticks"><?php foreach ($reto['dirigido'] as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul>
            </div>
            <div class="panel">
              <h3 class="mini-title">Requisitos</h3>
              <ul class="ticks ticks--sun"><?php foreach ($reto['requisitos'] as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul>
            </div>
          </div>
          <div class="panel panel--dark">
            <h3 class="mini-title">Aliados y acompañamiento</h3>
            <ul class="chip-list chip-list--dark"><?php foreach ($reto['aliados'] as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul>
            <p>Cada equipo cuenta con un docente mentor de UNIMINUTO y el acompañamiento de un líder de la comunidad pesquera durante todo el reto.</p>
          </div>
        </section>

        <!-- INSCRIPCIÓN -->
        <section id="inscripcion" class="block reveal" aria-labelledby="h-ins">
          <p class="kicker">Inscripción</p>
          <h2 id="h-ins" class="block-title"><?= $reto['abierto'] ? 'Quiero participar en este reto' : 'Inscripciones cerradas' ?></h2>
          <?php if ($reto['abierto']): ?>
            <p class="block-lead">Quedan <strong><?= (int) $reto['disponibles'] ?> de <?= (int) $reto['cupos'] ?> cupos</strong>. Inscripciones abiertas hasta el <?= e(fecha_larga($reto['fecha_cierre'])) ?>.</p>
            <?php $retoFijo = $reto; require ROOT . '/app/views/form_inscripcion.php'; ?>
          <?php else: ?>
            <div class="closed-box">
              <p><?= e($reto['motivo_cerrado']) ?> Puedes descargar la ficha del reto o explorar otros retos con inscripciones abiertas.</p>
              <a href="index.php#retos" class="btn btn-line">Ver otros retos</a>
            </div>
          <?php endif; ?>
        </section>

        <section id="faq" class="block reveal" aria-labelledby="h-faq">
          <p class="kicker">Preguntas frecuentes</p>
          <h2 id="h-faq" class="block-title">Resolvemos tus dudas</h2>
          <div class="faq">
            <?php foreach (FAQ as [$q, $a]): ?>
              <details class="faq-item"><summary><?= e($q) ?><span class="faq-icon" aria-hidden="true"></span></summary><p><?= e($a) ?></p></details>
            <?php endforeach; ?>
          </div>
        </section>
      </article>

      <!-- FICHA DEL RETO -->
      <aside class="rb-aside" aria-label="Ficha del reto">
        <div class="ficha">
          <div class="ficha-head">
            <span class="ficha-code"><?= e($reto['codigo']) ?></span>
            <?php if ($reto['abierto']): ?>
              <span class="ficha-status"><span class="dot"></span>Inscripciones abiertas</span>
            <?php else: ?>
              <span class="ficha-status is-closed">Inscripciones cerradas</span>
            <?php endif; ?>
          </div>
          <div class="ficha-cupos">
            <div class="ficha-cupos-row"><span>Cupos ocupados</span><strong><?= (int) $reto['ocupados'] ?> / <?= (int) $reto['cupos'] ?></strong></div>
            <span class="ficha-bar" role="img" aria-label="<?= (int) $reto['ocupados'] ?> de <?= (int) $reto['cupos'] ?> cupos ocupados"><i style="width: <?= $reto['cupos'] ? min(100, round($reto['ocupados'] / $reto['cupos'] * 100)) : 0 ?>%"></i></span>
          </div>
          <dl class="ficha-list">
            <div><dt>Metodología</dt><dd><?= e($met['nombre']) ?></dd></div>
            <div><dt>Duración</dt><dd><?= e($reto['duracion']) ?></dd></div>
            <div><dt>Dedicación</dt><dd><?= e($reto['dedicacion']) ?></dd></div>
            <div><dt>Modalidad</dt><dd><?= e($reto['modalidad']) ?></dd></div>
            <div><dt>Equipos</dt><dd><?= e($reto['equipos']) ?></dd></div>
            <div><dt>Nivel</dt><dd><?= e($reto['nivel']) ?></dd></div>
            <div><dt>Inicio</dt><dd><?= e(fecha_larga($reto['fecha_inicio'])) ?></dd></div>
            <div><dt>Cierre de inscripciones</dt><dd><?= e(fecha_larga($reto['fecha_cierre'])) ?></dd></div>
          </dl>
          <?php if ($reto['abierto']): ?>
            <a href="#inscripcion" class="btn btn-sun btn-lg btn-block">Inscribirme <?= $arrow ?></a>
          <?php endif; ?>
          <a href="<?= e($pdfUrl) ?>" class="btn btn-line btn-block"><?= $pdfIcon ?><span>Descargar ficha PDF</span></a>
          <button type="button" class="btn btn-line btn-block" id="share">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1"/></svg>
            <span>Copiar enlace del reto</span>
          </button>
          <p class="share-msg" role="status" aria-live="polite"></p>
        </div>
      </aside>
    </div>
<?php endif; ?>

<?php if ($otros): ?>
    <section class="more" aria-labelledby="h-more">
      <div class="container">
        <div class="more-head">
          <h2 id="h-more" class="block-title"><?= $reto ? 'Explora otros retos' : 'Retos disponibles' ?></h2>
          <a href="index.php#retos" class="link-arrow">Ver todos <?= $arrow ?></a>
        </div>
        <ul class="more-grid">
          <?php foreach (array_slice($otros, 0, $reto ? 3 : 6) as $o): ?>
            <li>
              <a class="more-card" href="reto.php?r=<?= e(urlencode($o['slug'])) ?>">
                <span class="more-icon" aria-hidden="true"><?= icon_svg($o['icono']) ?></span>
                <span class="more-meta"><?= e($o['codigo'] . ' · ' . $o['tag']) ?></span>
                <span class="more-title"><?= e($o['titulo']) ?></span>
                <span class="more-foot"><?= e($o['metodologia_nombre']) ?> <?= $arrow ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>
<?php endif; ?>

<?php if ($reto && $reto['abierto']): ?>
    <section class="final-cta" aria-labelledby="h-cta">
      <div class="container final-inner">
        <h2 id="h-cta">¿Listo para remar con nosotros?</h2>
        <p>Inscríbete en <strong><?= e($reto['titulo']) ?></strong> y transforma el Alto Magdalena desde el aula.</p>
        <a href="#inscripcion" class="btn btn-sun btn-lg">Aceptar el reto <?= $arrow ?></a>
      </div>
    </section>
<?php endif; ?>
  </main>

<?php require ROOT . '/app/views/public_footer.php'; ?>
