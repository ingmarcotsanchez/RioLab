<?php
require __DIR__ . '/app/bootstrap.php';

$retos = retos_publicos();
$retosForm = array_values(array_filter($retos, fn($r) => $r['abierto']));
$retoPre = (string) ($_GET['reto'] ?? '');
track('visita', 'inicio');

$title = 'RioLab 4.0 · Ecosistema de Aprendizaje del Alto Magdalena';
$isHome = true;
$scripts = ['js/inscripcion.js', 'js/main.js'];
require ROOT . '/app/views/public_header.php';
?>

  <main id="main">
    <!-- HERO + VIDEOS -->
    <section class="hero" id="inicio" aria-labelledby="hero-title">
      <div class="hero-media" aria-hidden="true">
        <video class="hero-video is-active" muted playsinline preload="auto"></video>
        <video class="hero-video" muted playsinline preload="none"></video>
        <div class="hero-scrim"></div>
        <div class="hero-grain"></div>
      </div>

      <div class="hero-inner container" data-stagger>
        <p class="eyebrow reveal"><span class="dot"></span>Alto Magdalena · Girardot, Colombia</p>
        <h1 id="hero-title" class="hero-title reveal">
          El río es nuestra <em>aula</em>.<br>
          La pesca, nuestro <em>laboratorio</em>.
        </h1>
        <p class="hero-lead reveal">
          RioLab 4.0 es el Ecosistema de Aprendizaje para la Innovación y el Desarrollo Sostenible del Sector Pesquero del Alto Magdalena. Estudiantes, docentes, pescadores y actores productivos construyendo juntos soluciones reales desde el territorio.
        </p>
        <div class="hero-actions reveal">
          <a href="#unete" class="btn btn-sun btn-lg">
            Súmate al ecosistema
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
          <a href="#retos" class="btn btn-ghost btn-lg">Explorar retos</a>
        </div>
      </div>

      <div class="hero-controls container">
        <ol class="reel" aria-label="Videos del territorio"></ol>
        <button class="reel-toggle" type="button" aria-label="Pausar video" aria-pressed="false">
          <svg class="i-pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14M16 5v14"/></svg>
          <svg class="i-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4.5v15l12-7.5z"/></svg>
        </button>
      </div>

      <a href="#ecosistema" class="scroll-cue" aria-label="Bajar a la sección Ecosistema">
        <span></span>
      </a>
    </section>

    <!-- ECOSISTEMA: Universidad → Territorio → Sector pesquero -->
    <section class="eco section" id="ecosistema" aria-labelledby="eco-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="kicker">El ecosistema</p>
          <h2 id="eco-title" class="section-title">Tres orillas, <span class="hl">una misma corriente</span></h2>
          <p class="section-lead">Conectamos el conocimiento académico con la vida del río para que el aprendizaje ocurra donde están los retos.</p>
        </div>

        <ol class="flow" data-stagger>
          <li class="flow-item reveal">
            <span class="flow-num">01</span>
            <div class="flow-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg>
            </div>
            <h3>Universidad</h3>
            <p>Estudiantes y docentes de UNIMINUTO llevan investigación, tecnología y metodologías al territorio.</p>
          </li>
          <li class="flow-arrow" aria-hidden="true"><svg viewBox="0 0 48 24"><path d="M2 12c8-8 14 8 22 0s14 8 22 0"/></svg></li>
          <li class="flow-item reveal">
            <span class="flow-num">02</span>
            <div class="flow-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M2 6c2.5-2 5-2 7.5 0S14.5 8 17 6s5-2 5 0"/><path d="M2 12c2.5-2 5-2 7.5 0s5 2 7.5 0 5-2 5 0"/><path d="M2 18c2.5-2 5-2 7.5 0s5 2 7.5 0 5-2 5 0"/></svg>
            </div>
            <h3>Territorio</h3>
            <p>El Alto Magdalena se convierte en escenario vivo de aprendizaje, experimentación e innovación.</p>
          </li>
          <li class="flow-arrow" aria-hidden="true"><svg viewBox="0 0 48 24"><path d="M2 12c8-8 14 8 22 0s14 8 22 0"/></svg></li>
          <li class="flow-item reveal">
            <span class="flow-num">03</span>
            <div class="flow-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M3 12c3-4.5 7-6 11-6 3 0 5.5 2 7 6-1.5 4-4 6-7 6-4 0-8-1.5-11-6Z"/><path d="M3 12 1 8m2 4-2 4"/><circle cx="16.5" cy="10.5" r=".9" fill="currentColor"/></svg>
            </div>
            <h3>Sector pesquero</h3>
            <p>Pescadores, asociaciones y actores productivos co-crean soluciones sostenibles con impacto real.</p>
          </li>
        </ol>

        <dl class="stats reveal">
          <div><dt>Actores</dt><dd>Estudiantes · Docentes · Comunidad · Sector productivo</dd></div>
          <div><dt>Enfoque</dt><dd>Aprendizaje basado en retos del territorio</dd></div>
          <div><dt>Horizonte</dt><dd>Innovación 4.0 y desarrollo sostenible</dd></div>
        </dl>
      </div>
    </section>

    <!-- RETOS -->
    <section class="retos section" id="retos" aria-labelledby="retos-title">
      <div class="container">
        <div class="section-head section-head--split reveal">
          <div>
            <p class="kicker">Retos RioLab</p>
            <h2 id="retos-title" class="section-title">Problemas reales,<br><span class="hl">soluciones desde el aula</span></h2>
          </div>
          <p class="section-lead">Cada reto nace de una necesidad del sector pesquero del Alto Magdalena. Elige uno, forma tu equipo y transforma el territorio.</p>
        </div>

        <div class="retos-grid" data-stagger>
<?php foreach ($retos as $r): ?>
          <article class="reto reveal" id="<?= e($r['slug']) ?>">
            <div class="reto-top">
              <span class="reto-tag"><?= e($r['tag']) ?></span>
              <span class="reto-n"><?= e($r['codigo']) ?></span>
            </div>
            <div class="reto-icon" aria-hidden="true"><?= icon_svg($r['icono']) ?></div>
            <h3><?= e($r['titulo']) ?></h3>
            <p><?= e($r['resumen']) ?></p>
            <p class="reto-cupos<?= $r['abierto'] ? '' : ' is-closed' ?>">
              <?php if ($r['abierto']): ?><span class="dot"></span><?= (int) $r['disponibles'] ?> de <?= (int) $r['cupos'] ?> cupos disponibles
              <?php else: ?>Inscripciones cerradas<?php endif; ?>
            </p>
            <a href="reto.php?r=<?= e(urlencode($r['slug'])) ?>" class="btn btn-reto" aria-label="<?= $r['abierto'] ? 'Aceptar el reto' : 'Ver el reto' ?>: <?= e($r['titulo']) ?>"><?= $r['abierto'] ? 'Aceptar el reto' : 'Ver el reto' ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
          </article>
<?php endforeach; ?>
<?php if (!$retos): ?>
          <p class="retos-empty">Muy pronto publicaremos los nuevos retos de RioLab 4.0.</p>
<?php endif; ?>
        </div>
      </div>
    </section>

    <!-- FORMULARIO -->
    <section class="join section" id="unete" aria-labelledby="join-title">
      <div class="container join-grid">
        <div class="join-copy reveal">
          <p class="kicker">Participa</p>
          <h2 id="join-title" class="section-title">Rema con nosotros</h2>
          <p class="section-lead">Déjanos tus datos y te contactaremos para vincularte a los retos, encuentros y experiencias de aprendizaje de RioLab 4.0.</p>
          <ul class="join-list">
            <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7"/></svg>Experiencias de aprendizaje en territorio</li>
            <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7"/></svg>Equipos mixtos: academia y comunidad</li>
            <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7"/></svg>Acompañamiento de docentes e investigadores</li>
          </ul>
        </div>

        <?php $retoFijo = null; require ROOT . '/app/views/form_inscripcion.php'; ?>
      </div>
    </section>
  </main>

<?php require ROOT . '/app/views/public_footer.php'; ?>
