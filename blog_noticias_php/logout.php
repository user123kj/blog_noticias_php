<?php
require_once __DIR__ . '/config.php';
// Solo se cierra sesión por POST con token CSRF (evita cierres forzados desde otros sitios).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . (is_admin() ? 'admin.php' : 'index.php')); exit; }
verify_csrf();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: index.php');
exit;
