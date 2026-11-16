# Códigos de estado HTTP

## ¿Qué son?

> Aunque el enunciado dice "códigos de estado de HTML", son códigos del protocolo **HTTP**: HTML es el lenguaje de las páginas, HTTP es el protocolo que las transporta.

Son números de **tres cifras** que el servidor incluye en la primera línea de cada respuesta para indicar al cliente el resultado de su petición. Van acompañados de una frase descriptiva:

```
HTTP/1.1 401 Unauthorized
WWW-Authenticate: Basic realm="Zona restringida - ASIR"
```

Están definidos en la **RFC 9110** (HTTP Semantics). El navegador, los buscadores, los proxies y las herramientas de monitorización actúan según el código (redirigir, cachear, pedir credenciales, reintentar…).

## ¿Cómo se clasifican?

Por la **primera cifra**, en cinco clases:

| Clase | Significado | Ejemplo |
|---|---|---|
| **1xx** Informativos | La petición se ha recibido y el proceso continúa | 101 Switching Protocols |
| **2xx** Éxito | La petición se ha recibido, entendido y aceptado | 200 OK |
| **3xx** Redirección | El cliente debe hacer algo más (normalmente ir a otra URL) | 301 Moved Permanently |
| **4xx** Error del cliente | La petición es incorrecta o no se puede atender por culpa del cliente | 404 Not Found |
| **5xx** Error del servidor | La petición era válida pero el servidor falló al procesarla | 502 Bad Gateway |

NGINX además usa algunos códigos **no estándar** propios que solo aparecen en sus logs: **444** (cerrar la conexión sin responder), **499** (el cliente cerró la conexión antes de recibir la respuesta), **494–497** (errores de cabeceras y certificados SSL).

## ¿Cuáles me han salido durante la configuración de NGINX y cuál ha sido la solución?

| Código | Dónde | Causa | Solución |
|---|---|---|---|
| **502 Bad Gateway** | `php.asir.local` | PHP-FPM no estaba arrancado. Log: `connect() to unix:/run/php/php8.3-fpm.sock failed (2: No such file or directory)` | `sudo systemctl enable --now php8.3-fpm` y comprobar que el socket de `fastcgi_pass` coincide con el `listen` del pool |
| **200** con descarga del `.php` | `web1` | Un `.php` en un sitio sin bloque `location ~ \.php$`: se sirvió como `application/octet-stream` mostrando el código fuente | Añadir el bloque FastCGI solo en los sitios que deben ejecutar PHP; no dejar `.php` en sitios estáticos |
| **403 Forbidden** | `web2.asir.local/vacio/` | Directorio sin `index.html` y `autoindex` desactivado. Log: `directory index of "/var/www/web2/vacio/" is forbidden` | Crear el `index.html` (o revisar permisos 755/644). **No** activar `autoindex` |
| **401 Unauthorized** | `/privado/` | Comportamiento esperado: sin credenciales o con contraseña incorrecta | Autenticarse con un usuario de `.htpasswd`. Si da 401 con la contraseña buena, revisar ruta y permisos de `.htpasswd` |
| **500 Internal Server Error** | `app.asir.local` | Contraseña de la BD incorrecta en `config.php`. Log: `Access denied for user 'agenda_user'@'localhost'` | Corregir `config.php` y comprobar `SHOW GRANTS FOR 'agenda_user'@'localhost'` |
| **404 Not Found** | `php.asir.local/noexiste.php` | El fichero no existe; el `try_files` del snippet evita pasarlo a PHP | Correcto: comprobar ruta y `root` si sale en una página que sí existe |
| **403** (intencionado) | `/.htpasswd`, `/config.php` | Reglas `deny all` | Comportamiento esperado: protegen ficheros sensibles |
| **444** (intencionado) | Acceso por IP | `default_server` con `return 444` | Comportamiento esperado: acceder por nombre de dominio |

El detalle de cada incidencia está en [Incidencias](../incidencias.md).

## Tabla de códigos más frecuentes

| Código | Nombre | Significado | Situación típica |
|---|---|---|---|
| 100 | Continue | El servidor acepta que el cliente envíe el cuerpo | Subidas grandes con `Expect: 100-continue` |
| 101 | Switching Protocols | Cambio de protocolo | Establecer un **WebSocket** |
| **200** | **OK** | Petición correcta | Cargar cualquier página |
| 201 | Created | Recurso creado | `POST` en una API REST |
| 204 | No Content | Correcto, sin cuerpo | `DELETE` en una API |
| 206 | Partial Content | Se envía solo un rango | Vídeos, descargas reanudables |
| **301** | **Moved Permanently** | Redirección permanente | `http` → `https`, cambio de dominio (los buscadores actualizan la URL) |
| **302** | **Found** | Redirección temporal | Tras hacer login |
| **304** | **Not Modified** | El recurso no ha cambiado; usar la caché | Recargar una página con imágenes ya cacheadas |
| 307 / 308 | Temporary / Permanent Redirect | Como 302/301 pero sin cambiar el método (POST sigue siendo POST) | Redirecciones en APIs |
| **400** | **Bad Request** | Petición mal formada | Cabeceras incorrectas, petición HTTP a puerto HTTPS |
| **401** | **Unauthorized** | Hace falta autenticarse | Zona protegida con `auth_basic` |
| **403** | **Forbidden** | Autenticado o no, no tiene permiso | `deny all`, permisos de ficheros, directorio sin índice |
| **404** | **Not Found** | El recurso no existe | URL mal escrita, `root` incorrecto |
| 405 | Method Not Allowed | Método no permitido en ese recurso | `POST` a un fichero estático |
| 408 | Request Timeout | El cliente tardó demasiado en enviar la petición | Conexiones lentas, *Slowloris* |
| **413** | **Content Too Large** | Cuerpo demasiado grande | Subir un fichero mayor que `client_max_body_size` |
| 414 | URI Too Long | URL demasiado larga | Parámetros GET enormes |
| **429** | **Too Many Requests** | Demasiadas peticiones | Se activa `limit_req` (por defecto NGINX usa 503; se cambia con `limit_req_status 429`) |
| 444 | *(nginx)* No Response | NGINX cierra sin responder | `return 444` en `default_server` |
| 499 | *(nginx)* Client Closed Request | El cliente cerró antes de la respuesta | Usuario cancela, PHP muy lento |
| **500** | **Internal Server Error** | Error genérico en el servidor/aplicación | Error en el código PHP, fallo de conexión a la BD |
| **502** | **Bad Gateway** | El backend dio una respuesta inválida o no respondió | **PHP-FPM caído** o socket incorrecto |
| **503** | **Service Unavailable** | Servicio no disponible temporalmente | Mantenimiento, sobrecarga, `limit_req` |
| **504** | **Gateway Timeout** | El backend tardó demasiado | Script PHP lento (`fastcgi_read_timeout`) |
