<?php
declare(strict_types=1);

/*
 * Configuración local del proyecto.
 * Ajusta estos valores si tu instalación de MySQL usa otras credenciales.
 */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'blog_noticias';
const DB_USER = 'root';
const DB_PASS = '';
const APP_TZ  = 'America/Lima';

date_default_timezone_set(APP_TZ);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // Misma zona horaria en PHP y MySQL para que NOW() y date() coincidan.
    $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    return $pdo;
}

// Cabeceras de seguridad y compresión también sin Apache (respaldo de .htaccess).
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) ob_start('ob_gzhandler');
}

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('Solicitud no válida. Regresa al formulario e inténtalo de nuevo.');
    }
}

function is_admin(): bool {
    return isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] > 0;
}

function require_admin(): void {
    if (!is_admin()) {
        header('Location: login.php');
        exit;
    }
}

function flash(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function get_flash(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $value = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $value;
}

function like_escape(string $s): string { return addcslashes($s, '%_\\'); }
function total_pages(int $total, int $per): int { return max(1, (int)ceil($total / max(1, $per))); }
function not_found(string $msg) {
    http_response_code(404); $pageTitle = 'No encontrada';
    require __DIR__ . '/partials/header.php';
    echo '<main id="contenido" class="container narrow"><h1>Noticia no encontrada</h1><p>' . e($msg) . '</p><p><a class="button" href="index.php">Volver a las noticias</a></p></main>';
    require __DIR__ . '/partials/footer.php'; exit;
}

/* ---------- Reglas de negocio (probadas en tests/pruebas_unitarias.php) ---------- */
const TITLE_MAX = 180;
const SUMMARY_MAX = 300;
const ALT_MAX = 200;
const CATEGORY_MAX = 80;
const PASSWORD_MIN = 8;
const LOGIN_MAX_FAILS = 5;
const LOGIN_LOCK_SECONDS = 60;

/** Valida los datos de una noticia. Devuelve [campo => mensaje]; vacío si es válida. */
function validate_news(array $d): array {
    $errors = [];
    $title = $d['title'] ?? ''; $summary = $d['summary'] ?? ''; $content = $d['content'] ?? '';
    $image = $d['image_url'] ?? ''; $alt = $d['image_alt'] ?? '';
    if ($title === '' || mb_strlen($title) > TITLE_MAX) $errors['title'] = 'El título es obligatorio (máximo ' . TITLE_MAX . ' caracteres).';
    if ($summary === '' || mb_strlen($summary) > SUMMARY_MAX) $errors['summary'] = 'El resumen es obligatorio (máximo ' . SUMMARY_MAX . ' caracteres).';
    if ($content === '') $errors['content'] = 'El contenido es obligatorio.';
    if ($image !== '' && (!filter_var($image, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $image))) $errors['image_url'] = 'Escribe una URL que empiece con http:// o https://, o deja el campo vacío.';
    if ($image !== '' && ($alt === '' || mb_strlen($alt) > ALT_MAX)) $errors['image_alt'] = 'Describe la imagen en máximo ' . ALT_MAX . ' caracteres para quienes usan lector de pantalla.';
    return $errors;
}

/** Política de contraseña: mínimo 8 caracteres, con letra y número. */
function password_problem(string $new, string $confirm): ?string {
    if (mb_strlen($new) < PASSWORD_MIN) return 'La nueva contraseña debe tener al menos ' . PASSWORD_MIN . ' caracteres.';
    if (!preg_match('/\pL/u', $new) || !preg_match('/\d/', $new)) return 'La nueva contraseña debe combinar letras y números.';
    if ($new !== $confirm) return 'La confirmación no coincide con la nueva contraseña.';
    return null;
}

/** Bloqueo temporal tras varios intentos fallidos de inicio de sesión. */
function login_locked_seconds(?int $now = null): int {
    $now = $now ?? time();
    $until = (int)($_SESSION['login_lock_until'] ?? 0);
    return max(0, $until - $now);
}
function register_login_failure(?int $now = null): void {
    $now = $now ?? time();
    $_SESSION['login_fails'] = (int)($_SESSION['login_fails'] ?? 0) + 1;
    if ($_SESSION['login_fails'] >= LOGIN_MAX_FAILS) {
        $_SESSION['login_lock_until'] = $now + LOGIN_LOCK_SECONDS;
        $_SESSION['login_fails'] = 0;
    }
}
function reset_login_failures(): void { unset($_SESSION['login_fails'], $_SESSION['login_lock_until']); }
