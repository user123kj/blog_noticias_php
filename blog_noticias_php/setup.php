<?php
require_once __DIR__ . '/config.php';

$message = '';
$error = '';
try {
    // La base blog_noticias debe haberse creado ejecutando schema.sql en phpMyAdmin.
    $pdo = db();
    $count = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
        $stmt->execute(['admin', password_hash('Admin123!', PASSWORD_DEFAULT)]);
    }
    $catCount = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ($catCount === 0) {
        $pdo->exec("INSERT INTO categories (name) VALUES ('Tecnología'), ('Educación'), ('Actualidad')");
    }
    $newsCount = (int)$pdo->query('SELECT COUNT(*) FROM news')->fetchColumn();
    if ($newsCount === 0) {
        $catId = (int)$pdo->query("SELECT id FROM categories WHERE name='Tecnología'")->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO news (category_id,title,summary,content,image_url,status,published_at)
            VALUES (?,?,?,?,?,'published',NOW())");
        $stmt->execute([$catId, 'Bienvenidos al blog de noticias',
            'Este es un artículo de ejemplo para comprobar la página pública.',
            'El blog permite publicar noticias, organizarlas por categorías y consultar su contenido completo. Este texto es de demostración y puede editarse o eliminarse desde el panel administrativo.',
            null]);
    }
    $message = 'Instalación inicial completada. Usuario: admin. La contraseña temporal está en el README. Cambia la contraseña después de ingresar y elimina setup.php del servidor cuando termines.';
} catch (Throwable $ex) {
    $error = 'No se pudo completar la instalación. Verifica que ejecutaste schema.sql y que los datos de conexión de config.php son correctos. Detalle: ' . $ex->getMessage();
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Instalación del blog</title><link rel="stylesheet" href="assets/style.css"></head>
<body><main class="narrow panel"><h1>Instalación del blog</h1>
<?php if ($message): ?><p class="notice success"><?= e($message) ?></p><p><a class="button" href="index.php">Ver blog</a> <a class="button secondary" href="login.php">Entrar al panel</a></p><?php else: ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
</main></body></html>
