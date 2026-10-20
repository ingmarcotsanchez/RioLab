<?php
/** Footer público compartido. Variables: $isHome (bool), $scripts[] */
$home = ($isHome ?? false) ? '' : url('index.php');
?>
  <footer class="footer">
    <svg class="footer-wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0 40c120-30 240-30 360 0s240 30 360 0 240-30 360 0 240 30 360 0v40H0Z"/>
    </svg>
    <div class="container footer-grid">
      <div class="footer-brand">
        <img src="<?= url('image/logo-para-web-2024.png') ?>" alt="UNIMINUTO Corporación Universitaria Minuto de Dios" width="263" height="71" loading="lazy">
        <p class="footer-center">Centro Universitario Girardot de UNIMINUTO</p>
        <p class="footer-desc">RioLab 4.0 — Ecosistema de Aprendizaje para la Innovación y el Desarrollo Sostenible del Sector Pesquero del Alto Magdalena.</p>
      </div>
      <nav class="footer-nav" aria-label="Pie de página">
        <h3>Navega</h3>
        <ul>
          <li><a href="<?= ($isHome ?? false) ? '#inicio' : url('index.php') ?>">Inicio</a></li>
          <li><a href="<?= $home ?>#ecosistema">Ecosistema</a></li>
          <li><a href="<?= $home ?>#retos">Retos</a></li>
          <li><a href="<?= $home ?>#unete">Participa</a></li>
          <li><a href="<?= url('participantes/') ?>">Mis entregas</a></li>
        </ul>
      </nav>
      <div class="footer-nav">
        <h3>Territorio</h3>
        <ul class="plain">
          <li>Girardot, Cundinamarca</li>
          <li>Región del Alto Magdalena</li>
          <li>Colombia</li>
        </ul>
      </div>
    </div>
    <div class="container footer-bottom">
      <p>© <span id="year"><?= date('Y') ?></span> RioLab 4.0 · Centro Universitario Girardot de UNIMINUTO</p>
      <a href="#main" class="to-top">Volver arriba
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M6 11l6-6 6 6"/></svg>
      </a>
    </div>
  </footer>

<?php foreach ($scripts ?? [] as $js): ?>
  <script src="<?= url($js) ?>" defer></script>
<?php endforeach; ?>
  <script src="<?= url('js/common.js') ?>" defer></script>
</body>
</html>
