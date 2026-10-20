# RioLab 4.0 — Instalación y uso

Sitio público + panel administrativo en **PHP 8.1+** y **MySQL 8 / MariaDB 10.5+**, sin dependencias externas (FPDF viene incluida en `lib/fpdf`).

## 1. Preparar MySQL

Crea una base de datos y un usuario propio para el proyecto (no uses `root` en producción):

```sql
CREATE DATABASE riolab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'riolab'@'localhost' IDENTIFIED BY 'una-contraseña-segura';
GRANT ALL PRIVILEGES ON riolab.* TO 'riolab'@'localhost';
```

## 2. Configurar

```bash
cp config/config.local.example.php config/config.local.php
```

Edita `config/config.local.php` con el usuario, la contraseña, la URL del sitio y una frase secreta. Ese archivo está en `.gitignore`: tus credenciales no se suben a GitHub.

## 3. Instalar

```bash
php database/install.php
```

Crea las tablas, carga las metodologías y los 6 retos iniciales y te pide el correo y la contraseña del primer administrador.

Opcional: `php database/install.php --demo` agrega 140 inscripciones y 75 días de visitas **de ejemplo** para ver el dashboard con datos. Se borran con `php database/demo.php --limpiar`.

## 4. Ejecutar en local

```bash
php -S localhost:8080 router.php
```

- Sitio: http://localhost:8080
- Panel: http://localhost:8080/admin/
- Mis entregas (participantes): http://localhost:8080/participantes/

Usa siempre `router.php`: bloquea el acceso web a `app/`, `config/`, `database/`, `lib/` y `storage/`. En Apache esa protección la hacen los archivos `.htaccess` incluidos.

## 5. Publicar en un servidor

- Sube todo el proyecto y crea `config/config.local.php` en el servidor.
- Apache: necesita `AllowOverride All` para que funcionen los `.htaccess`. Nginx: bloquea esas mismas carpetas en la configuración del sitio.
- Tamaño de archivos: el límite de carga es 20 MB (`config.php → uploads.max_mb`). PHP debe permitirlo: `upload_max_filesize = 25M` y `post_max_size = 26M` (incluidos en `.htaccess` y `.user.ini`).
- La carpeta `storage/uploads/` debe tener permisos de escritura para PHP y entrar en las copias de seguridad.
- Usa HTTPS: la cookie de sesión se marca como segura automáticamente.

## Cómo funciona

| Parte | Archivos |
|---|---|
| Sitio público (lee los retos de MySQL) | `index.php`, `reto.php` |
| Inscripción "Quiero participar" | `inscripcion.php`, `js/inscripcion.js` |
| Ficha PDF de cada reto | `reto-pdf.php` |
| Portal de participantes (correo + código) | `participantes/` |
| Panel administrativo | `admin/` |
| Lógica compartida | `app/` |
| Esquema de la base de datos | `database/schema.sql` |

**Código de acceso:** al inscribirse por primera vez, cada persona recibe un código `RL-XXXX-XXXX`. Solo se guarda su hash, así que no se puede consultar. Si alguien lo pierde, un administrador o mentor genera uno nuevo desde la ficha del inscrito.

**Roles del panel:**
- **Administrador:** acceso total; crea retos, metodologías y usuarios.
- **Mentor:** ve el dashboard, los inscritos y las entregas solo de los retos que tiene asignados, y deja retroalimentación.

**Estadísticas:** las visitas se registran sin guardar la IP. Se usa un identificador anónimo que cambia cada día, y se ignoran los bots evidentes.
