<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Inicio';
$q = trim((string)($_GET['q'] ?? ''));
$category = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: 0;
$perPage = 9; $page = max(1, (int)(filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT) ?: 1));
$where = "WHERE n.status='published'"; $params = [];
if ($q !== '') { $where .= " AND (n.title LIKE ? OR n.summary LIKE ?)"; $like = '%' . like_escape($q) . '%'; array_push($params, $like, $like); }
if ($category > 0) { $where .= " AND n.category_id=?"; $params[] = $category; }
$cnt = db()->prepare("SELECT COUNT(*) FROM news n $where"); $cnt->execute($params);
$total = (int)$cnt->fetchColumn(); $pages = total_pages($total, $perPage); $page = min($page, $pages);
$statement = db()->prepare("SELECT n.id,n.title,n.summary,n.image_url,n.image_alt,n.published_at,c.name AS category_name
        FROM news n LEFT JOIN categories c ON c.id=n.category_id $where
        ORDER BY COALESCE(n.published_at,n.created_at) DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
$statement->execute($params);
$news = $statement->fetchAll();
$categories = db()->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
require __DIR__ . '/partials/header.php';
?>
<main id="contenido">
  <section class="hero">
    <div class="container hero-grid">
      <div>
        <p class="eyebrow">INFORMACIÓN · IDEAS · ACTUALIDAD</p>
        <h1>Historias que te ayudan a <span>entender el mundo.</span></h1>
        <p class="hero-copy">Noticias, novedades y contenidos organizados para que encuentres lo que te interesa.</p>
        <a class="button" href="#noticias">Explorar noticias <span aria-hidden="true">↓</span></a>
      </div>
      <div class="hero-art" aria-hidden="true"><div class="art-card"><span class="art-label">EN FOCO</span><div class="art-lines"></div><div class="art-orb"></div><div class="art-bottom">Información clara.<br>Ideas que avanzan.</div></div></div>
    </div>
  </section>
  <section id="noticias" class="section container">
    <div class="section-heading"><div><p class="eyebrow">LO MÁS RECIENTE</p><h2>Últimas noticias</h2></div><p class="muted">Lee, descubre y comparte conocimiento.</p></div>
    <form class="filters" method="get" action="index.php" role="search">
      <div class="field grow"><label for="q">Buscar noticias</label><input id="q" name="q" type="search" enterkeyhint="search" value="<?= e($q) ?>" placeholder="Escribe una palabra clave"></div>
      <div class="field"><label for="category">Categoría</label><select id="category" name="category"><option value="0">Todas las categorías</option><?php foreach ($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>" <?= $category === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?></select></div>
      <button class="button" type="submit">Buscar</button>
      <?php if ($q !== '' || $category > 0): ?><a class="button secondary" href="index.php">Limpiar</a><?php endif; ?>
    </form>
    <?php if (!$news): ?><div class="empty-state"><h3>No encontramos noticias</h3><p>Prueba con otra búsqueda o selecciona una categoría diferente.</p></div>
    <?php else: ?><div class="news-grid">
      <?php foreach ($news as $item): ?>
      <article class="news-card">
        <?php if (!empty($item['image_url'])): ?><img class="news-image" src="<?= e($item['image_url']) ?>" alt="<?= e($item['image_alt'] ?? '') ?>" width="400" height="190" loading="lazy" decoding="async"><?php else: ?><div class="news-image image-placeholder" aria-hidden="true">N</div><?php endif; ?>
        <div class="news-body">
          <div class="meta"><span><?= e($item['category_name'] ?? 'General') ?></span><time datetime="<?= e($item['published_at'] ?? '') ?>"><?= e($item['published_at'] ? date('d/m/Y', strtotime($item['published_at'])) : '') ?></time></div>
          <h3><a href="noticia.php?id=<?= (int)$item['id'] ?>"><?= e($item['title']) ?></a></h3>
          <p><?= e($item['summary']) ?></p>
          <a class="read-more" href="noticia.php?id=<?= (int)$item['id'] ?>">Leer noticia <span aria-hidden="true">→</span></a>
        </div>
      </article>
      <?php endforeach; ?>
    </div><?php endif; ?>
    <?php if ($pages > 1): ?><nav class="pager" aria-label="Paginación"><?php for ($i = 1; $i <= $pages; $i++): ?><a href="?<?= e(http_build_query(['q' => $q, 'category' => $category, 'p' => $i])) ?>#noticias" <?= $i === $page ? 'aria-current="page"' : '' ?>>Página <?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
