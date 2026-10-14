<?php
require_once __DIR__ . '/config.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método no permitido.'); }
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = (string)($_POST['action'] ?? '');
if (!$id || $id < 1) { flash('Identificador de noticia inválido.', 'error'); header('Location: admin.php'); exit; }
if ($action === 'delete') {
    $stmt = db()->prepare('DELETE FROM news WHERE id=?');
    $stmt->execute([$id]);
    flash($stmt->rowCount() ? 'Noticia eliminada.' : 'La noticia ya no existe.', $stmt->rowCount() ? 'success' : 'error');
} elseif ($action === 'toggle') {
    $stmt = db()->prepare("UPDATE news SET published_at=IF(status='published',NULL,COALESCE(published_at,NOW())), status=IF(status='published','draft','published') WHERE id=?");
    $stmt->execute([$id]);
    flash($stmt->rowCount() ? 'Estado de publicación actualizado.' : 'No se encontró la noticia.', $stmt->rowCount() ? 'success' : 'error');
} else {
    flash('Acción no reconocida.', 'error');
}
header('Location: admin.php');
exit;
