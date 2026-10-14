<?php
require_once __DIR__ . '/config.php';
require_admin();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$item = ['title'=>'','summary'=>'','content'=>'','image_url'=>'','image_alt'=>'','category_id'=>'','status'=>'draft'];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM news WHERE id=?'); $stmt->execute([$id]);
    $found = $stmt->fetch(); if (!$found) { not_found('La noticia no existe.'); }
    $item = $found;
}
$categories = db()->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim((string)($_POST['title'] ?? '')); $summary = trim((string)($_POST['summary'] ?? ''));
    $content = trim((string)($_POST['content'] ?? '')); $image = trim((string)($_POST['image_url'] ?? ''));
    $alt = trim((string)($_POST['image_alt'] ?? ''));
    $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?: null;
    $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $item = ['title'=>$title,'summary'=>$summary,'content'=>$content,'image_url'=>$image,'image_alt'=>$alt,'category_id'=>$categoryId,'status'=>$status];
    $errors = validate_news($item);
    if (!$errors) {
        if ($id) {
            $stmt = db()->prepare('UPDATE news SET category_id=?,title=?,summary=?,content=?,image_url=?,image_alt=?,status=?,published_at=IF(?=\'published\',COALESCE(published_at,NOW()),NULL) WHERE id=?');
            $stmt->execute([$categoryId,$title,$summary,$content,$image ?: null,$image ? $alt : null,$status,$status,$id]);
            flash('Noticia actualizada correctamente.');
        } else {
            $stmt = db()->prepare('INSERT INTO news (category_id,title,summary,content,image_url,image_alt,status,published_at) VALUES (?,?,?,?,?,?,?,IF(?=\'published\',NOW(),NULL))');
            $stmt->execute([$categoryId,$title,$summary,$content,$image ?: null,$image ? $alt : null,$status,$status]);
            flash('Noticia creada correctamente.');
        }
        header('Location: admin.php'); exit;
    }
}
function inv(array $er, string $k): string { return isset($er[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : ''; }
function fe(array $er, string $k): string { return isset($er[$k]) ? '<small class="field-error" id="err-' . $k . '">' . e($er[$k]) . '</small>' : ''; }
$pageTitle = $id ? 'Editar noticia' : 'Nueva noticia';
require __DIR__ . '/partials/header.php';
?>
<main id="contenido" class="container form-wrap">
  <a class="back-link" href="admin.php">← Volver al panel</a>
  <section class="panel form-panel"><p class="eyebrow">GESTIÓN DE CONTENIDO</p><h1><?= $id ? 'Editar noticia' : 'Crear noticia' ?></h1>
    <?php if ($errors): ?><p class="notice error" role="alert">Revisa los campos marcados (<?= count($errors) ?>).</p><?php endif; ?>
    <form method="post" class="stack" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="field"><label for="title">Título (obligatorio)</label><input id="title" name="title" maxlength="180" required value="<?= e($item['title']) ?>"<?= inv($errors,'title') ?>><?= fe($errors,'title') ?></div>
      <div class="field"><label for="summary">Resumen (obligatorio)</label><textarea id="summary" name="summary" rows="3" maxlength="300" required<?= inv($errors,'summary') ?>><?= e($item['summary']) ?></textarea><small>Máximo 300 caracteres.</small><?= fe($errors,'summary') ?></div>
      <div class="field"><label for="content">Contenido (obligatorio)</label><textarea id="content" name="content" rows="9" required<?= inv($errors,'content') ?>><?= e($item['content']) ?></textarea><?= fe($errors,'content') ?></div>
      <div class="field"><label for="image_url">URL de imagen (opcional)</label><input id="image_url" name="image_url" type="url" value="<?= e($item['image_url']) ?>" placeholder="https://ejemplo.com/imagen.jpg"<?= inv($errors,'image_url') ?>><small>Usa una imagen ligera y con permiso de publicación.</small><?= fe($errors,'image_url') ?></div>
      <div class="field"><label for="image_alt">Texto alternativo de la imagen</label><input id="image_alt" name="image_alt" maxlength="200" value="<?= e($item['image_alt'] ?? '') ?>"<?= inv($errors,'image_alt') ?>><small>Obligatorio si agregas imagen.</small><?= fe($errors,'image_alt') ?></div>
      <div class="form-grid">
        <div class="field"><label for="category_id">Categoría</label><select id="category_id" name="category_id"><option value="">Sin categoría</option><?php foreach ($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>" <?= (string)$item['category_id'] === (string)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="status">Estado</label><select id="status" name="status"><option value="draft" <?= $item['status']==='draft'?'selected':'' ?>>Guardar como borrador</option><option value="published" <?= $item['status']==='published'?'selected':'' ?>>Publicar</option></select></div>
      </div>
      <div class="form-actions"><button class="button" type="submit">Guardar noticia</button><a class="button secondary" href="admin.php">Cancelar</a></div>
    </form>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
