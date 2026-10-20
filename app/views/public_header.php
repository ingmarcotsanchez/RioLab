<?php
/**
 * Cabecera pública compartida (head + nav flotante).
 * Variables: $title, $description, $bodyClass, $extraCss[], $isHome (bool), $navActive ('retos'|'portal'|'')
 */
$isHome = $isHome ?? false;
$navActive = $navActive ?? '';
$home = $isHome ? '' : url('index.php');
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'RioLab 4.0') ?></title>
  <meta name="description" content="<?= e($description ?? 'RioLab 4.0: Ecosistema de Aprendizaje para la Innovación y el Desarrollo Sostenible del Sector Pesquero del Alto Magdalena. UNIMINUTO Centro Universitario Girardot.') ?>">
  <meta name="theme-color" content="#04161f">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <link rel="icon" type="image/png" href="<?= url('image/logo_riolab-nav.png') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<?php foreach ($extraCss ?? [] as $css): ?>
  <link rel="stylesheet" href="<?= url($css) ?>">
<?php endforeach; ?>
</head>
<body<?= !empty($bodyClass) ? ' class="' . e($bodyClass) . '"' : '' ?>>
  <a class="skip-link" href="#main">Saltar al contenido</a>

  <header class="nav-wrap">
    <nav class="nav" aria-label="Principal">
      <a href="<?= $isHome ? '#inicio' : url('index.php') ?>" class="brand" aria-label="RioLab 4.0, ir al inicio">
        <img class="brand-logo" src="<?= url('image/logo_riolab-nav.png') ?>" alt="" width="192" height="192">
        <span>RioLab<b>4.0</b></span>
      </a>
      <ul class="nav-links" id="nav-links">
        <li><a href="<?= $home ?>#ecosistema">Ecosistema</a></li>
        <li><a href="<?= $home ?>#retos"<?= $navActive === 'retos' ? ' class="is-active" aria-current="page"' : '' ?>>Retos</a></li>
        <li><a href="<?= $home ?>#unete">Participa</a></li>
        <li><a href="<?= url('participantes/') ?>"<?= $navActive === 'portal' ? ' class="is-active" aria-current="page"' : '' ?>>Mis entregas</a></li>
      </ul>
      <a href="<?= e($navCtaHref ?? $home . '#unete') ?>" class="btn btn-sun btn-sm nav-cta">Únete</a>
      <button class="nav-toggle" aria-expanded="false" aria-controls="nav-links" aria-label="Abrir menú">
        <span></span><span></span>
      </button>
    </nav>
  </header>
