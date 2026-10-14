<?php
// Carga los datos de prueba del plan (sección 2.5): 25 publicadas, 2 borradores, 3 categorías.
// Uso: php tests/datos_prueba.php [URL base]   (después de importar schema.sql y abrir setup.php)
// Ejemplo XAMPP: php tests/datos_prueba.php http://localhost/blog_noticias_php
require __DIR__ . '/../config.php';
$pdo = db();
$cats = $pdo->query('SELECT name,id FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);
if (count($cats) < 3) exit("Primero ejecuta setup.php\n");
$temas = ['Tecnología' => ['Nueva red de fibra óptica llega a Huancayo', 'Estudiantes crean app para reciclar', 'Inteligencia artificial en las aulas', 'Ciberseguridad para pequeños negocios', 'Drones apoyan la agricultura andina', 'Laboratorio de robótica abre sus puertas', 'Feria de software libre en la región', 'Pagos digitales crecen en mercados'],
          'Educación'  => ['Becas para estudiantes de ingeniería', 'Bibliotecas amplían su horario', 'Docentes se capacitan en accesibilidad', 'Olimpiada de matemáticas 50%_récord', 'Talleres de lectura para escolares', 'Universidad lanza cursos virtuales', 'Programa de tutoría entre pares', 'Clases de quechua en colegios'],
          'Actualidad' => ['Festival del Valle del Mantaro', 'Mejoras en el transporte urbano', 'Campaña de vacunación en distritos', 'Temporada de lluvias: recomendaciones', 'Nuevo parque ecológico en la ciudad', 'Mercado modelo renueva instalaciones', 'Maratón solidaria reúne a cientos', 'Ferias agropecuarias del fin de semana']];
$ins = $pdo->prepare("INSERT INTO news (category_id,title,summary,content,image_url,image_alt,status,published_at) VALUES (?,?,?,?,?,?,?,?)");
$n = 0;
foreach ($temas as $cat => $titulos) foreach ($titulos as $t) {
    $n++;
    $img = $n === 1 ? rtrim($argv[1] ?? "http://localhost/blog_noticias_php", "/") . "/assets/icon.svg" : null;
    $ins->execute([$cats[$cat], $t, "Resumen de la noticia: $t.", "Contenido completo de la noticia «{$t}».\nSegundo párrafo con más detalles para la lectura.", $img, $img ? 'Logotipo verde del blog con la letra N' : null, 'published', date('Y-m-d H:i:s', time() - $n * 3600)]);
}
$ins->execute([$cats['Educación'], 'Borrador: plan de estudios 2027', 'Resumen en revisión.', 'Texto en revisión.', null, null, 'draft', null]);
$ins->execute([$cats['Actualidad'], 'Borrador: elecciones estudiantiles', 'Resumen en revisión.', 'Texto en revisión.', null, null, 'draft', null]);
echo "Datos cargados: $n publicadas + 1 de setup + 2 borradores\n";
