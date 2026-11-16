# NGINX

## Estructura de la configuración (Ubuntu/Debian)

```
/etc/nginx/
├── nginx.conf              ← configuración global (contextos main, events, http)
├── sites-available/        ← un fichero por sitio (server blocks)
├── sites-enabled/          ← enlaces simbólicos a los sitios activos
├── snippets/               ← fragmentos reutilizables (fastcgi-php.conf…)
├── conf.d/                 ← configuración adicional del contexto http
└── modules-enabled/        ← módulos dinámicos cargados
```

Arquitectura de procesos: un proceso **master** (root) que lee la configuración y abre los puertos, y varios procesos **worker** (usuario `www-data`) que atienden las conexiones.

```bash
ps -ef | grep nginx
# root      ... nginx: master process /usr/sbin/nginx
# www-data  ... nginx: worker process
# www-data  ... nginx: worker process
```

## ¿Qué significan las siguientes directivas?

### `worker_processes`

Contexto `main`. Número de procesos *worker* que lanza NGINX. Cada worker es un proceso de un solo hilo que gestiona muchas conexiones con su bucle de eventos, así que lo normal es **un worker por núcleo de CPU**.

```nginx
worker_processes auto;     # valor de Ubuntu: tantos workers como núcleos (nproc)
```

Poner más workers que núcleos no aporta rendimiento y aumenta los cambios de contexto.

### `worker_connections`

Contexto `events`. Número **máximo de conexiones simultáneas que puede abrir cada worker** (incluye las conexiones con clientes y las que NGINX abre hacia backends como PHP-FPM o servidores proxificados).

```nginx
events {
    worker_connections 768;    # valor por defecto en Ubuntu (el de nginx compilado es 512)
}
```

Capacidad aproximada: **clientes máximos ≈ `worker_processes` × `worker_connections`**. Como proxy inverso, cada cliente usa dos conexiones (cliente + backend), así que se divide entre 2. Está limitado también por el número de descriptores de fichero del sistema (`ulimit -n`, `worker_rlimit_nofile`).

### `keepalive_timeout`

Contexto `http`, `server` o `location`. Tiempo que una conexión **keep-alive** con el cliente permanece abierta **sin actividad** esperando más peticiones antes de que NGINX la cierre.

```nginx
keepalive_timeout 65;          # 65 s (por defecto en nginx: 75 s)
keepalive_timeout 65 60;       # el segundo valor se envía al cliente en la cabecera "Keep-Alive: timeout=60"
keepalive_timeout 0;           # desactiva keep-alive
```

Mantener la conexión ahorra el coste de abrir TCP+TLS para cada recurso de la página, pero un valor muy alto mantiene conexiones ociosas ocupando `worker_connections` (y facilita ataques tipo *Slowloris*). Valores típicos: 15–65 s.

## ¿Cuáles son los módulos más importantes para ampliar las funcionalidades de NGINX?

Se puede ver con qué módulos está compilado con `nginx -V`. Los módulos dinámicos se cargan con `load_module` (en Ubuntu, desde `/etc/nginx/modules-enabled/`).

| Módulo | Para qué sirve |
|---|---|
| `ngx_http_core_module` | Directivas básicas: `server`, `location`, `root`, `listen`, `server_name`… |
| `ngx_http_ssl_module` | HTTPS / TLS (`ssl_certificate`, `ssl_protocols`…) |
| `ngx_http_v2_module` / `ngx_http_v3_module` | HTTP/2 y HTTP/3 (QUIC) |
| `ngx_http_proxy_module` | **Proxy inverso** hacia otros servidores HTTP (`proxy_pass`) y caché (`proxy_cache`) |
| `ngx_http_upstream_module` | **Balanceo de carga** entre varios backends (round-robin, `least_conn`, `ip_hash`) |
| `ngx_http_fastcgi_module` | Comunicación con **PHP-FPM** (`fastcgi_pass`) y `fastcgi_cache` |
| `ngx_http_rewrite_module` | Redirecciones y reescritura de URL (`rewrite`, `return`, `if`) |
| `ngx_http_gzip_module` | Compresión de respuestas |
| `ngx_http_auth_basic_module` | Autenticación con usuario/contraseña (usado en la zona protegida) |
| `ngx_http_access_module` | Permitir/denegar por IP (`allow`, `deny`) |
| `ngx_http_auth_request_module` | Delegar la autenticación en un servicio externo (SSO) |
| `ngx_http_limit_req_module` / `limit_conn` | Limitar peticiones o conexiones por IP (anti fuerza bruta / DoS) |
| `ngx_http_headers_module` | Añadir cabeceras (`add_header`, `expires`) — cabeceras de seguridad |
| `ngx_http_realip_module` | Obtener la IP real del cliente detrás de un proxy/CDN |
| `ngx_http_stub_status_module` | Estadísticas básicas para monitorización |
| `ngx_http_geoip_module` | Decisiones según el país de la IP |
| `ngx_stream_module` | Proxy/balanceo de TCP y UDP (BD, DNS, etc.) |
| Terceros: **ModSecurity**, Brotli, headers-more | WAF, compresión Brotli, manipulación avanzada de cabeceras |

## ¿Qué debemos tener en cuenta a la hora de configurar PHP en NGINX?

1. **NGINX no interpreta PHP**: hay que instalar **PHP-FPM** y comunicarse con él por **FastCGI**. Si no hay bloque `location ~ \.php$`, NGINX entrega el `.php` como descarga y **expone el código fuente** (contraseñas incluidas). Lo comprobé en la práctica: ver [Incidencias](../incidencias.md).
2. **`fastcgi_pass` debe coincidir con el `listen` del pool** (`/etc/php/8.3/fpm/pool.d/www.conf`): mismo socket y **misma versión de PHP**. Si no coincide → `502 Bad Gateway`.
3. **Permisos del socket**: `listen.owner`/`listen.group` deben permitir que el usuario de NGINX (`www-data`) lo use.
4. **`SCRIPT_FILENAME` correcto**: lo define `snippets/fastcgi-php.conf` (o `fastcgi.conf`) como `$document_root$fastcgi_script_name`. Si falta → página en blanco o "File not found".
5. **No pasar a PHP ficheros que no existen**: `try_files $fastcgi_script_name =404` (incluido en el snippet). Evita la vulnerabilidad clásica de ejecutar como PHP un fichero subido (p. ej. `/uploads/foto.jpg/x.php`). Complementario: `cgi.fix_pathinfo=0` en `php.ini` y **no permitir ejecutar PHP en directorios de subida**.
6. **`index index.php`** para que se sirva al pedir `/`.
7. **Extensiones necesarias**: por ejemplo `php8.3-mysql` para MySQL; tras instalarlas o cambiar `php.ini`, **reiniciar PHP-FPM** (`systemctl restart php8.3-fpm`), no solo NGINX.
8. **Límites coherentes** entre NGINX y PHP: `client_max_body_size` (NGINX) con `upload_max_filesize` y `post_max_size` (PHP) — si no, `413`; `fastcgi_read_timeout` con `max_execution_time` — si no, `504`.
9. **Seguridad de PHP**: `display_errors = Off`, `expose_php = Off`, borrar `phpinfo()`, proteger ficheros de configuración (`location = /config.php { deny all; }`).
10. **Rendimiento**: activar **OPcache** y ajustar el gestor de procesos del pool (`pm = dynamic`, `pm.max_children`…) según la RAM disponible.
11. **Logs**: los errores de PHP aparecen en el `error_log` del sitio como `FastCGI sent in stderr` y en el log de PHP-FPM.
