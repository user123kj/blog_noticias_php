<?php
require_once __DIR__ . '/config.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { not_found('Noticia no encontrada.'); }
$stmt = db()->prepare("SELECT n.*,c.name AS category_name FROM news n LEFT JOIN categories c ON c.id=n.category_id WHERE n.id=? AND n.status='published'");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { not_found('Noticia no encontrada o no publicada.'); }
$pageTitle = $item['title'];
require __DIR__ . '/partials/header.php';
?>
<main id="contenido" class="container article-wrap">
  <a class="back-link" href="index.php">← Volver a noticias</a>
  <article class="article">
    <?php if ($item['image_url']): ?><img class="article-cover" src="<?= e($item['image_url']) ?>" alt="<?= e($item['image_alt'] ?? '') ?>" width="900" height="440" decoding="async"><?php endif; ?>
    <div class="article-content">
      <p class="eyebrow"><?= e($item['category_name'] ?? 'General') ?> · <?= e(date('d/m/Y', strtotime($item['published_at'] ?? $item['created_at']))) ?></p>
      <h1><?= e($item['title']) ?></h1>
      <p class="article-summary"><?= e($item['summary']) ?></p>
      <p class="tools"><button class="button secondary" type="button" data-speak hidden aria-pressed="false">Escuchar noticia</button> <button class="button secondary" type="button" data-share hidden>Compartir</button></p>
      <div class="prose"><?= nl2br(e($item['content'])) ?></div>
    </div>
  </article>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
