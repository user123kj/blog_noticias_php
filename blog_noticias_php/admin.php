<?php
require_once __DIR__ . '/config.php';
require_admin();
$news = db()->query("SELECT n.id,n.title,n.status,n.updated_at,c.name AS category_name FROM news n LEFT JOIN categories c ON c.id=n.category_id ORDER BY n.updated_at DESC")->fetchAll();
$categories = db()->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$pageTitle = 'Administración';
require __DIR__ . '/partials/header.php';
?>
<main id="contenido" class="container admin-wrap">
  <div class="admin-heading"><div><p class="eyebrow">PANEL DE CONTROL</p><h1>Administrar noticias</h1><p class="muted">Hola, <?= e($_SESSION['admin_username'] ?? 'administrador') ?>. Gestiona el contenido publicado en el blog.</p></div><a class="button" href="noticia_form.php">+ Nueva noticia</a></div>
  <div class="admin-stats"><div class="stat"><span>Total de noticias</span><strong><?= count($news) ?></strong></div><div class="stat"><span>Publicadas</span><strong><?= count(array_filter($news, fn($n) => $n['status'] === 'published')) ?></strong></div><div class="stat"><span>Categorías</span><strong><?= count($categories) ?></strong></div></div>
  <section class="panel">
    <div class="section-heading"><h2>Contenido</h2><a class="text-link" href="categorias.php">Administrar categorías →</a></div>
    <?php if (!$news): ?><p class="empty-state">Todavía no hay noticias. Crea la primera para comenzar.</p><?php else: ?>
    <div class="table-scroll"><table><thead><tr><th scope="col">Título</th><th scope="col">Categoría</th><th scope="col">Estado</th><th scope="col">Última actualización</th><th scope="col">Acciones</th></tr></thead><tbody>
    <?php foreach ($news as $item): ?><tr><td data-label="Título"><?= e($item['title']) ?></td><td data-label="Categoría"><?= e($item['category_name'] ?? 'General') ?></td><td data-label="Estado"><span class="status <?= $item['status'] === 'published' ? 'published' : 'draft' ?>"><?= $item['status'] === 'published' ? 'Publicada' : 'Borrador' ?></span></td><td data-label="Actualización"><?= e(date('d/m/Y H:i', strtotime($item['updated_at']))) ?></td><td data-label="Acciones"><div class="actions"><a href="noticia_form.php?id=<?= (int)$item['id'] ?>">Editar<span class="sr-only"> <?= e($item['title']) ?></span></a><form method="post" action="noticia_accion.php" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="link-button" type="submit"><?= $item['status'] === 'published' ? 'Despublicar' : 'Publicar' ?><span class="sr-only"> <?= e($item['title']) ?></span></button></form><form method="post" action="noticia_accion.php" class="inline-form confirm-form" data-confirm="¿Seguro que deseas eliminar esta noticia?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="action" value="delete"><button class="link-button danger-text" type="submit">Eliminar<span class="sr-only"> <?= e($item['title']) ?></span></button></form></div></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </section>
  <div class="admin-bottom"><a href="index.php">Ver página pública</a><a href="cuenta.php">Cambiar contraseña</a><form method="post" action="logout.php" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="link-button" type="submit">Cerrar sesión</button></form></div>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
