# Blog de noticias autoadministrable en PHP

## Contenido
- Blog público con listado, búsqueda, filtros y detalle de noticias.
- Panel administrativo protegido por sesión.
- CRUD de noticias, publicar/despublicar y gestión de categorías.
- Validación del lado del servidor, consultas preparadas PDO, escape de salida y protección CSRF en formularios de administración.
- Interfaz adaptable con consideraciones iniciales de accesibilidad.
- Informe y plan de pruebas: `Informe_Plan_de_Pruebas.docx` (carpeta superior).
- `schema.sql`: estructura de base de datos.

## Requisitos
- PHP 8.x con extensiones PDO MySQL y mbstring.
- MySQL/MariaDB (XAMPP recomendado para entorno local).

## Instalación en XAMPP (Windows)
1. Copia la carpeta `blog_noticias_php` dentro de `C:\xampp\htdocs\`.
2. Inicia Apache y MySQL desde el panel de XAMPP.
3. Abre `http://localhost/phpmyadmin`.
4. Importa el archivo `schema.sql` desde la pestaña Importar.
5. Revisa los valores de `config.php`. La configuración inicial supone MySQL en `127.0.0.1`, usuario `root`, contraseña vacía y base `blog_noticias`.
6. Abre `http://localhost/blog_noticias_php/setup.php` una sola vez.
7. Entra a `http://localhost/blog_noticias_php/login.php` con:
   - Usuario: `admin`
   - Contraseña temporal: `Admin123!`
8. Para probar, crea una noticia desde “Nueva noticia”, publícala y verifica que aparezca en el inicio.
9. Cuando termines la configuración, elimina `setup.php` del directorio del proyecto. En una implementación real, cambia la contraseña temporal y no publiques credenciales de prueba.

## Seguridad
- Consultas preparadas (PDO), escape de salida, token CSRF en todos los formularios y en el cierre de sesión.
- Bloqueo de 60 s tras 5 intentos fallidos de inicio de sesión; cambio de contraseña en `cuenta.php`.
- Cabeceras `nosniff`, `SAMEORIGIN` y `Referrer-Policy`; compresión gzip desde PHP y `.htaccess`.

## Archivos importantes
- `index.php`: página pública y búsqueda.
- `noticia.php`: detalle de noticia publicada.
- `login.php`, `logout.php`: sesión administrativa.
- `admin.php`: panel de administración.
- `noticia_form.php`: creación y edición.
- `noticia_accion.php`: publicación, despublicación y eliminación.
- `categorias.php`: mantenimiento de categorías.
- `cuenta.php`: cambio de contraseña del administrador.
- `tests/pruebas_unitarias.php`: 25 pruebas unitarias con valores límite (`php tests/pruebas_unitarias.php`).
- `tests/datos_prueba.php`: carga los datos de prueba del plan (25 publicadas, 2 borradores).
- `tests/carga_1000.php`: prueba de carga con 1000 noticias (CP-32).
- `tests/pruebas_sistema.py`: 46 casos de sistema automatizados con Playwright; genera `resultados.json` y capturas en `tests/evidencias/`.
- `.htaccess`: compresión, caché y cabeceras de seguridad.

## Cómo ejecutar las pruebas
1. Unitarias (no necesitan base de datos): `php tests/pruebas_unitarias.php` → 25/25.
2. Datos de prueba: `php tests/datos_prueba.php http://localhost/blog_noticias_php`
3. Carga: `php tests/carga_1000.php`
4. Sistema (opcional, requiere Python): `pip install playwright` y `playwright install chromium`, luego
   `python tests/pruebas_sistema.py http://localhost/blog_noticias_php`
   Usa una base recién instalada (los casos crean y eliminan sus propias noticias).
   Nota: el caso CP-48 bloquea el inicio de sesión 60 s en ese navegador de prueba; es lo esperado.

## Consideraciones académicas
Los resultados del informe provienen de la ejecución real de los scripts de `tests/`. Las capturas están en `tests/evidencias/`. La rúbrica evalúa funcionalidad, usabilidad, accesibilidad, estilo y eficiencia; la trazabilidad está en la sección 7 del informe.

## Accesibilidad, celular y velocidad
- Panel «Accesibilidad y lectura»: texto más grande/pequeño, alto contraste, modo oscuro (respeta la preferencia del sistema) y restablecer. Se recuerda en el navegador.
- «Escuchar noticia» (voz del navegador) y «Compartir» (celular). Se ocultan si el navegador no los soporta.
- Celular: navegación inferior, objetivos táctiles de 44–48 px, instalable como app (manifest + `sw.js`; requiere localhost o HTTPS).
- Velocidad: sin recursos externos, caché con service worker, `content-visibility`, modo ahorro de datos.
