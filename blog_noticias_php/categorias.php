<?php
require_once __DIR__ . '/config.php';
require_admin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) $error = 'Escribe una categoría de hasta 80 caracteres.';
        else {
            try {
                $stmt = db()->prepare('INSERT INTO categories (name) VALUES (?)');
                $stmt->execute([$name]);
                flash('Categoría creada.'); header('Location: categorias.php'); exit;
            } catch (PDOException $ex) { $error = 'La categoría ya existe o no se pudo guardar.'; }
        }
    } elseif ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = db()->prepare('DELETE FROM categories WHERE id=?');
            $stmt->execute([$id]);
            flash('Categoría eliminada. Las noticias relacionadas quedarán sin categoría.');
        }
        header('Location: categorias.php'); exit;
    }
}
$categories = db()->query('SELECT c.id,c.name,COUNT(n.id) AS news_count FROM categories c LEFT JOIN news n ON n.category_id=c.id GROUP BY c.id,c.name ORDER BY c.name')->fetchAll();
$pageTitle = 'Categorías';
require __DIR__ . '/partials/header.php';
?>
<main id="contenido" class="container admin-wrap">
  <a class="back-link" href="admin.php">← Volver al panel</a>
  <div class="admin-heading"><div><p class="eyebrow">ORGANIZACIÓN</p><h1>Categorías</h1><p class="muted">Clasifica las noticias para facilitar su consulta.</p></div></div>
  <section class="panel">
    <h2>Nueva categoría</h2>
    <?php if ($error): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="inline-add"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add"><div class="field grow"><label for="name">Nombre de categoría</label><input id="name" name="name" maxlength="80" required></div><button class="button" type="submit">Agregar</button></form>
  </section>
  <section class="panel"><h2>Categorías registradas</h2><?php if (!$categories): ?><p>No hay categorías registradas.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th scope="col">Nombre</th><th scope="col">Noticias asociadas</th><th scope="col">Acción</th></tr></thead><tbody><?php foreach ($categories as $cat): ?><tr><td data-label="Nombre"><?= e($cat['name']) ?></td><td data-label="Noticias"><?= (int)$cat['news_count'] ?></td><td data-label="Acción"><form method="post" class="confirm-form" data-confirm="¿Eliminar esta categoría? Las noticias no se eliminarán."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$cat['id'] ?>"><button class="link-button danger-text" type="submit">Eliminar</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
