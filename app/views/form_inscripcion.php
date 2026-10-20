<?php
/**
 * Formulario "Quiero participar" (se usa en el inicio y en cada reto).
 * Variables: $retosForm (lista de retos abiertos), $retoFijo (reto actual o null), $retoPre (slug preseleccionado)
 */
$retoFijo = $retoFijo ?? null;
$retoPre = $retoPre ?? '';
$fid = $retoFijo ? 'r' : 'i';   // prefijo de ids, por si hay dos formularios en una página
?>
<form class="form reveal js-inscripcion" action="<?= url('inscripcion.php') ?>" method="post" novalidate>
  <?= csrf_field() ?>
  <!-- Campo trampa para bots: las personas no lo ven -->
  <div class="hp" aria-hidden="true"><label>Sitio web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

  <?php if ($retoFijo): ?>
    <input type="hidden" name="reto" value="<?= e($retoFijo['slug']) ?>">
    <input type="hidden" name="origen" value="reto">
    <p class="form-reto"><span>Te inscribes en</span><strong><?= e($retoFijo['codigo'] . ' · ' . $retoFijo['titulo']) ?></strong></p>
  <?php else: ?>
    <input type="hidden" name="origen" value="inicio">
  <?php endif; ?>

  <div class="form-grid">
    <div class="field">
      <label for="<?= $fid ?>-nombre">Nombre completo</label>
      <input id="<?= $fid ?>-nombre" name="nombre" type="text" autocomplete="name" required maxlength="160">
      <p class="error" data-err="nombre" aria-live="polite"></p>
    </div>
    <div class="field">
      <label for="<?= $fid ?>-correo">Correo electrónico</label>
      <input id="<?= $fid ?>-correo" name="correo" type="email" autocomplete="email" inputmode="email" required maxlength="160">
      <p class="error" data-err="correo" aria-live="polite"></p>
    </div>
    <div class="field">
      <label for="<?= $fid ?>-telefono">Teléfono / WhatsApp</label>
      <input id="<?= $fid ?>-telefono" name="telefono" type="tel" autocomplete="tel" inputmode="tel" required maxlength="40">
      <p class="error" data-err="telefono" aria-live="polite"></p>
    </div>
    <div class="field">
      <label for="<?= $fid ?>-municipio">Municipio</label>
      <input id="<?= $fid ?>-municipio" name="municipio" type="text" autocomplete="address-level2" list="municipios-<?= $fid ?>" required maxlength="80">
      <datalist id="municipios-<?= $fid ?>">
        <?php foreach (MUNICIPIOS as $m): ?><option value="<?= e($m) ?>"></option><?php endforeach; ?>
      </datalist>
      <p class="error" data-err="municipio" aria-live="polite"></p>
    </div>
    <div class="field<?= $retoFijo ? ' field--full' : '' ?>">
      <label for="<?= $fid ?>-perfil">¿Cómo participas?</label>
      <select id="<?= $fid ?>-perfil" name="perfil" required>
        <option value="">Selecciona tu perfil</option>
        <?php foreach (PERFILES as $p): ?><option><?= e($p) ?></option><?php endforeach; ?>
      </select>
      <p class="error" data-err="perfil" aria-live="polite"></p>
    </div>
    <?php if (!$retoFijo): ?>
    <div class="field<?= $retoPre ? ' is-flash' : '' ?>">
      <label for="<?= $fid ?>-reto">Reto de interés <span class="opt">(opcional)</span></label>
      <select id="<?= $fid ?>-reto" name="reto">
        <option value="">Aún no lo sé</option>
        <?php foreach ($retosForm as $r): ?>
          <option value="<?= e($r['slug']) ?>"<?= $retoPre === $r['slug'] ? ' selected' : '' ?>><?= e($r['titulo']) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="error" data-err="reto" aria-live="polite"></p>
    </div>
    <?php endif; ?>
    <div class="field field--full">
      <label for="<?= $fid ?>-mensaje">Cuéntanos tu idea <span class="opt">(opcional)</span></label>
      <textarea id="<?= $fid ?>-mensaje" name="mensaje" rows="3" maxlength="500"></textarea>
    </div>
    <div class="field field--full check">
      <input id="<?= $fid ?>-acepto" name="acepto" type="checkbox" value="1" required>
      <label for="<?= $fid ?>-acepto">Autorizo el tratamiento de mis datos personales para fines del proyecto RioLab 4.0, conforme a la Ley 1581 de 2012.</label>
      <p class="error" data-err="acepto" aria-live="polite"></p>
    </div>
  </div>
  <p class="form-alert" role="alert" hidden></p>
  <button type="submit" class="btn btn-sun btn-lg btn-block">
    <span class="btn-label">Quiero participar</span>
    <span class="spinner" aria-hidden="true"></span>
  </button>

  <div class="form-success" tabindex="-1" hidden>
    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m7.5 12 3 3 6-6"/></svg>
    <div>
      <strong class="fs-title">¡Bienvenido a RioLab 4.0!</strong>
      <p class="fs-msg"></p>
      <div class="fs-code" hidden>
        <span>Tu código de acceso</span>
        <code></code>
        <button type="button" class="btn btn-line btn-sm fs-copy">Copiar código</button>
        <small>Guárdalo: lo necesitarás junto con tu correo para subir los documentos del reto en <a href="<?= url('participantes/') ?>">Mis entregas</a>.</small>
      </div>
    </div>
  </div>
</form>
