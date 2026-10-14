<?php
// CP-32: inserta 1000 noticias publicadas, mide las consultas del inicio y luego las elimina.
// Uso: php tests/carga_1000.php
require __DIR__ . '/../config.php';
$pdo = db();
$cat = (int)$pdo->query("SELECT id FROM categories ORDER BY id LIMIT 1")->fetchColumn();
$pdo->beginTransaction();
$ins = $pdo->prepare("INSERT INTO news (category_id,title,summary,content,status,published_at) VALUES (?,?,?,?, 'published', ?)");
for ($i = 1; $i <= 1000; $i++) $ins->execute([$cat, "Carga CP-32 número $i", "Resumen $i", str_repeat("Texto de carga. ", 40), date('Y-m-d H:i:s', time() - 86400 - $i * 60)]);
$pdo->commit();
$medir = function (string $sql, array $p = []) use ($pdo): float {
    $t = []; for ($k = 0; $k < 5; $k++) { $a = hrtime(true); $s = $pdo->prepare($sql); $s->execute($p); $s->fetchAll(); $t[] = (hrtime(true) - $a) / 1e6; }
    sort($t); return $t[2]; // mediana de 5 ejecuciones (ms)
};
$total = (int)$pdo->query("SELECT COUNT(*) FROM news WHERE status='published'")->fetchColumn();
$r = [
  'Conteo de publicadas'      => $medir("SELECT COUNT(*) FROM news n WHERE n.status='published'"),
  'Listado página 1 (9)'      => $medir("SELECT n.id,n.title FROM news n LEFT JOIN categories c ON c.id=n.category_id WHERE n.status='published' ORDER BY COALESCE(n.published_at,n.created_at) DESC LIMIT 9 OFFSET 0"),
  'Listado página 100'        => $medir("SELECT n.id,n.title FROM news n LEFT JOIN categories c ON c.id=n.category_id WHERE n.status='published' ORDER BY COALESCE(n.published_at,n.created_at) DESC LIMIT 9 OFFSET 891"),
  'Búsqueda LIKE'             => $medir("SELECT COUNT(*) FROM news n WHERE n.status='published' AND (n.title LIKE ? OR n.summary LIKE ?)", ['%500%', '%500%']),
  'Filtro por categoría'      => $medir("SELECT COUNT(*) FROM news n WHERE n.status='published' AND n.category_id=?", [$cat]),
];
echo "Noticias publicadas: $total\n";
foreach ($r as $k => $v) printf("%-26s %7.2f ms %s\n", $k, $v, $v < 200 ? 'OK' : 'LENTO');
$pdo->exec("DELETE FROM news WHERE title LIKE 'Carga CP-32%'");
echo "Datos de carga eliminados.\n";
try {   // En MySQL/MariaDB (XAMPP) muestra el índice usado (idx_news_status_date / idx_news_category).
    $plan = $pdo->query("EXPLAIN SELECT COUNT(*) FROM news n WHERE n.status='published' AND n.category_id=$cat")->fetchAll();
    echo "EXPLAIN filtro: " . json_encode($plan, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) { echo "EXPLAIN no disponible en este servidor.\n"; }
