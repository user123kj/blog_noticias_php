<?php
require_once __DIR__ . '/config.php';
require_admin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id=?');
    $stmt->execute([(int)$_SESSION['admin_id']]);
    $hash = (string)$stmt->fetchColumn();
    if (!password_verify($current, $hash)) {
        $error = 'La contraseña actual no es correcta.';
    } elseif ($current === $new) {
        $error = 'La nueva contraseña debe ser distinta de la actual.';
    } elseif ($problem = password_problem($new, $confirm)) {
        $error = $problem;
    } else {
        $upd = db()->prepare('UPDATE admins SET password_hash=? WHERE id=?');
        $upd->execute([password_hash($new, PASSWORD_DEFAULT), (int)$_SESSION['admin_id']]);
        session_regenerate_id(true);
        flash('Contraseña actualizada correctamente.');
        header('Location: admin.php'); exit;
    }
}
$pageTitle = 'Cambiar contraseña';
require __DIR__ . '/partials/header.php';
?>
<main id="contenido" class="container auth-wrap">
  <section class="auth-card">
    <a class="back-link" href="admin.php">← Volver al panel</a>
    <p class="eyebrow">SEGURIDAD DE LA CUENTA</p><h1>Cambiar contraseña</h1>
    <p class="muted">Usa al menos <?= PASSWORD_MIN ?> caracteres combinando letras y números.</p>
    <?php if ($error): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="stack">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="field"><label for="current_password">Contraseña actual</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
      <div class="field"><label for="new_password">Nueva contraseña</label><input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="<?= PASSWORD_MIN ?>" required aria-describedby="pw-help"><small id="pw-help">Mínimo <?= PASSWORD_MIN ?> caracteres, con letras y números.</small></div>
      <div class="field"><label for="confirm_password">Repite la nueva contraseña</label><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required></div>
      <button class="button full" type="submit">Guardar contraseña</button>
    </form>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
