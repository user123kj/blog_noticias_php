<?php
$pageTitle = $pageTitle ?? 'Noticias al Día';
$flashMessage = get_flash();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Blog de noticias autoadministrable para compartir información y actualidad.">
  <title><?= e($pageTitle) ?> | Noticias al Día</title>
  <link rel="stylesheet" href="assets/style.css?v=2">
  <link rel="manifest" href="manifest.webmanifest">
  <meta name="theme-color" content="#176b62">
  <script>try{var p=JSON.parse(localStorage.a11y||'{}'),d=document.documentElement;d.dataset.size=p.s||0;if(p.c)d.dataset.contrast='high';d.dataset.theme=p.t||(matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');if(navigator.connection&&navigator.connection.saveData)d.classList.add('save-data')}catch(e){}</script>
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="site-header">
  <div class="container nav-wrap">
    <a class="brand" href="index.php" aria-label="Noticias al Día, inicio"><span class="brand-mark" aria-hidden="true">N</span><span>Noticias<span class="brand-light"> al Día</span></span></a>
    <nav aria-label="Navegación principal">
      <?php $cur = basename($_SERVER['SCRIPT_NAME']); ?><a href="index.php"<?= $cur === 'index.php' ? ' aria-current="page"' : '' ?>>Inicio</a>
      <a href="index.php#noticias">Noticias</a>
      <a href="login.php"<?= in_array($cur, ['login.php','admin.php','noticia_form.php','categorias.php','cuenta.php']) ? ' aria-current="page"' : '' ?>>Administración</a>
    </nav>
  </div>
</header>
<details class="a11y" hidden>
  <summary>Accesibilidad y lectura</summary>
  <div class="container a11y-inner" role="group" aria-label="Opciones de accesibilidad">
    <button type="button" data-act="smaller">Texto más pequeño</button>
    <button type="button" data-act="larger">Texto más grande</button>
    <button type="button" data-act="contrast" aria-pressed="false">Alto contraste</button>
    <button type="button" data-act="theme" aria-pressed="false">Modo oscuro</button>
    <button type="button" data-act="reset">Restablecer</button>
  </div>
</details>
<?php if ($flashMessage): ?>
  <div class="container"><p class="notice <?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></p></div>
<?php endif; ?>
