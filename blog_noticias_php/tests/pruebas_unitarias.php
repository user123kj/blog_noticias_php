<?php
// Uso: php tests/pruebas_unitarias.php   (no requiere base de datos)
// Técnicas: partición de equivalencias y análisis de valores límite (ISTQB).
require __DIR__ . '/../config.php';

$valida = ['title' => 'Título', 'summary' => 'Resumen', 'content' => 'Texto', 'image_url' => '', 'image_alt' => ''];
$con = fn(array $cambios) => validate_news(array_merge($valida, $cambios));

$casos = [
 // Funciones auxiliares
 'UT-01 e() escapa HTML'                   => e('<b>"x"</b>') === '&lt;b&gt;&quot;x&quot;&lt;/b&gt;',
 'UT-02 e() acepta null'                   => e(null) === '',
 'UT-03 like_escape() escapa % y _'        => like_escape('50%_a') === '50\\%\\_a',
 'UT-04 total_pages() redondea arriba'     => total_pages(10, 9) === 2,
 'UT-05 total_pages() mínimo 1'            => total_pages(0, 9) === 1,
 'UT-06 total_pages() exacto (9/9)'        => total_pages(9, 9) === 1,
 'UT-07 csrf_token() estable, 64 hex'      => csrf_token() === csrf_token() && strlen(csrf_token()) === 64,
 // Validación de noticias: clases válidas e inválidas
 'UT-08 noticia válida sin errores'        => $con([]) === [],
 'UT-09 campos vacíos -> 3 errores'        => array_keys($con(['title' => '', 'summary' => '', 'content' => ''])) === ['title', 'summary', 'content'],
 // Valores límite del título (180)
 'UT-10 título 180 caracteres: válido'     => !isset($con(['title' => str_repeat('á', 180)])['title']),
 'UT-11 título 181 caracteres: inválido'   => isset($con(['title' => str_repeat('á', 181)])['title']),
 // Valores límite del resumen (300)
 'UT-12 resumen 300 caracteres: válido'    => !isset($con(['summary' => str_repeat('a', 300)])['summary']),
 'UT-13 resumen 301 caracteres: inválido'  => isset($con(['summary' => str_repeat('a', 301)])['summary']),
 // Imagen y texto alternativo (200)
 'UT-14 imagen sin alt: inválido'          => isset($con(['image_url' => 'https://x.pe/a.jpg'])['image_alt']),
 'UT-15 alt 200 caracteres: válido'        => $con(['image_url' => 'https://x.pe/a.jpg', 'image_alt' => str_repeat('a', 200)]) === [],
 'UT-16 alt 201 caracteres: inválido'      => isset($con(['image_url' => 'https://x.pe/a.jpg', 'image_alt' => str_repeat('a', 201)])['image_alt']),
 'UT-17 URL javascript: rechazada'         => isset($con(['image_url' => 'javascript:alert(1)', 'image_alt' => 'x'])['image_url']),
 'UT-18 URL ftp: rechazada'                => isset($con(['image_url' => 'ftp://x.pe/a.jpg', 'image_alt' => 'x'])['image_url']),
 // Política de contraseña (mínimo 8)
 'UT-19 contraseña 7 caracteres: inválida' => password_problem('Abc1234', 'Abc1234') !== null,
 'UT-20 contraseña 8 caracteres: válida'   => password_problem('Abc12345', 'Abc12345') === null,
 'UT-21 contraseña sin números: inválida'  => password_problem('Abcdefgh', 'Abcdefgh') !== null,
 'UT-22 confirmación distinta: inválida'   => password_problem('Abc12345', 'Abc12346') !== null,
];
// Bloqueo de inicio de sesión: 4 fallos no bloquean, el 5.º sí (60 s)
$_SESSION = []; $t = 1000;
for ($i = 0; $i < 4; $i++) register_login_failure($t);
$casos['UT-23 4 intentos fallidos: sin bloqueo'] = login_locked_seconds($t) === 0;
register_login_failure($t);
$casos['UT-24 5.º intento fallido: bloqueo 60 s'] = login_locked_seconds($t) === 60;
$casos['UT-25 bloqueo expira a los 60 s']        = login_locked_seconds($t + 60) === 0;

$ok = 0; foreach ($casos as $n => $r) { echo ($r ? 'APROBADO ' : 'FALLIDO  ') . $n . PHP_EOL; $ok += (int)$r; }
echo "$ok/" . count($casos) . " aprobados" . PHP_EOL; exit($ok === count($casos) ? 0 : 1);
