<?php
require_once __DIR__ . '/config.php';
if (is_admin()) { header('Location: admin.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($wait = login_locked_seconds()) > 0) {
        $error = "Demasiados intentos fallidos. Espera $wait segundos e inténtalo de nuevo.";
    } else {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT id,username,password_hash FROM admins WHERE username=? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        reset_login_failures();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        header('Location: admin.php'); exit;
    }
    register_login_failure();
    $error = 'Usuario o contraseña incorrectos.';
    }
}
$pageTitle = 'Acceso administrativo';
require __DIR__ . '/partials/header.php';
?>
<main id="contenido" class="container auth-wrap">
  <section class="auth-card">
    <p class="eyebrow">ÁREA PRIVADA</p><h1>Iniciar sesión</h1><p class="muted">Accede para administrar las noticias del blog.</p>
    <?php if ($error): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="stack">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="field"><label for="username">Usuario</label><input id="username" name="username" autocomplete="username" required maxlength="60"></div>
      <div class="field"><label for="password">Contraseña</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
      <button class="button full" type="submit">Entrar al panel</button>
    </form>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
