# Servidores web

## ¿Qué es un servidor web?

El término tiene dos sentidos:

- **Software**: programa que escucha peticiones HTTP/HTTPS (normalmente en los puertos 80 y 443), localiza el recurso solicitado y devuelve una respuesta: un fichero, una página generada al momento o un mensaje de error. Ejemplos: NGINX, Apache HTTP Server, Microsoft IIS, LiteSpeed, Caddy.
- **Hardware**: el equipo (físico, virtual o contenedor) donde se ejecuta ese software y se almacenan los ficheros de los sitios.

## ¿Qué función principal cumple dentro de una arquitectura cliente-servidor?

En el modelo cliente-servidor, el **cliente** (navegador, `curl`, una app) inicia la comunicación y el **servidor** permanece a la escucha y responde. La función del servidor web es:

1. **Aceptar la conexión** TCP (o QUIC/UDP en HTTP/3) y, si es HTTPS, negociar TLS.
2. **Interpretar la petición HTTP**: método (`GET`, `POST`…), URL y cabeceras (`Host`, `Cookie`, `Authorization`…).
3. **Decidir qué sitio y qué recurso** corresponden (virtual hosts, `location`, reglas de reescritura, control de acceso).
4. **Generar la respuesta**: leer un fichero del disco o pasar la petición a un intérprete/aplicación (PHP-FPM, Node, Python…) o a otro servidor (proxy inverso).
5. **Devolver la respuesta** con su código de estado (200, 404…) y cabeceras, y **registrar** la petición en los logs.

Es el punto central entre los usuarios y los recursos: también aplica seguridad (autenticación, cifrado, filtrado) y reparte la carga.

## ¿Diferencia entre un servidor web estático y uno dinámico?

| | Estático | Dinámico |
|---|---|---|
| Qué entrega | Ficheros tal cual están en disco (HTML, CSS, JS, imágenes) | Contenido generado en cada petición |
| Procesamiento | Ninguno: lee y envía | Ejecuta código (PHP, Python, Java, Node…) y suele consultar una BD |
| Mismo resultado para todos | Sí | No: depende del usuario, la sesión, la hora, los datos… |
| Rendimiento | Muy alto, fácil de cachear | Menor, consume CPU/RAM y BD |
| Seguridad | Superficie de ataque pequeña | Mayor: inyección SQL, XSS, RCE, etc. |
| En esta práctica | `web1` y `web2` | `php.asir.local` y `app.asir.local` |

En la práctica, un servidor como NGINX sirve directamente lo estático y **delega lo dinámico** en otro proceso (PHP-FPM).

## ¿Qué protocolos suelen utilizar para entregar contenido?

- **HTTP** (*HyperText Transfer Protocol*): protocolo de aplicación base de la web. Versiones:
  - **HTTP/1.1**: texto, conexiones persistentes (*keep-alive*), cabecera `Host` obligatoria (lo que permite los virtual hosts).
  - **HTTP/2**: binario, **multiplexación** de varias peticiones en una misma conexión, compresión de cabeceras (HPACK). En la práctica siempre sobre TLS.
  - **HTTP/3**: funciona sobre **QUIC (UDP)** en lugar de TCP; menos latencia y sin bloqueo *head-of-line*.
- **HTTPS**: HTTP cifrado con **TLS** (versiones actuales 1.2 y 1.3), puerto 443. Aporta confidencialidad, integridad y autenticación del servidor mediante certificado.
- **TCP/IP**: transporte y red por debajo de HTTP/1.1 y HTTP/2.
- **WebSocket**: canal bidireccional persistente que se inicia con una petición HTTP (`Upgrade`), usado en chats o paneles en tiempo real.
- Internamente, entre servidor web y aplicación: **FastCGI** (PHP-FPM), **uWSGI**, **proxy HTTP** hacia otros servidores.

## ¿Qué características debe tener un servidor web para manejar altos volúmenes de tráfico?

- **Alta concurrencia con pocos recursos**: arquitectura orientada a eventos y E/S no bloqueante (como NGINX) en lugar de un proceso/hilo por conexión.
- **Caché**: caché de contenido (`proxy_cache`, `fastcgi_cache`), caché de código PHP (**OPcache**), caché de navegador (`Cache-Control`, `Expires`) y CDN para contenido estático.
- **Compresión** (`gzip`, Brotli) para enviar menos bytes.
- **Balanceo de carga** y **escalado horizontal**: varios servidores de aplicación detrás de un proxy inverso (`upstream`), con comprobación de salud.
- **Conexiones persistentes y HTTP/2–HTTP/3** para reducir el coste de abrir conexiones.
- **Ajuste de límites del sistema**: `worker_processes`, `worker_connections`, número de descriptores de fichero (`worker_rlimit_nofile`, `ulimit`), *timeouts* adecuados.
- **Separación de servicios**: BD en otro servidor, contenido estático en almacenamiento/CDN.
- **Protección frente a abusos**: limitación de peticiones (`limit_req`, `limit_conn`) y mitigación de DDoS.
- **Alta disponibilidad**: redundancia (varios nodos, IP flotante con keepalived) para que la caída de uno no tumbe el servicio.
- **Monitorización** (`stub_status`, Prometheus, logs) para detectar cuellos de botella.

## ¿En qué se diferencian Apache HTTP Server y Nginx en su modelo de funcionamiento?

| | Apache HTTP Server | NGINX |
|---|---|---|
| Modelo | **Basado en procesos/hilos**, mediante MPM: `prefork` (un proceso por conexión), `worker` (hilos), `event` (hilos + gestión asíncrona de keep-alive) | **Orientado a eventos, asíncrono y no bloqueante**: un proceso *master* y unos pocos *workers*; cada worker atiende miles de conexiones en un bucle de eventos (epoll) |
| Consumo con muchas conexiones | Crece con el número de conexiones (cada una ocupa un proceso o hilo) | Casi constante; muy eficiente con miles de conexiones simultáneas |
| Contenido dinámico | Puede ejecutar PHP **dentro** del servidor (`mod_php`) o vía PHP-FPM | **No** ejecuta código: lo delega por FastCGI (PHP-FPM) o proxy |
| Configuración distribuida | Sí, ficheros **`.htaccess`** por directorio (cómodo en hosting compartido, pero penaliza rendimiento) | No existe `.htaccess`; toda la configuración es central y se aplica con `reload` |
| Módulos | Se cargan/descargan dinámicamente con facilidad (`a2enmod`) | Se compilan o se cargan como módulos dinámicos (`load_module`) |
| Uso típico | Hosting compartido, aplicaciones heredadas, mucha flexibilidad por directorio | Contenido estático, **proxy inverso**, balanceador, alto tráfico |

Es habitual combinarlos: NGINX delante (estático, TLS, caché, balanceo) y Apache u otro servidor de aplicación detrás.

## ¿Qué medidas de seguridad son necesarias para proteger un servidor web en producción?

**Sistema y red**
- Mantener actualizados SO, NGINX, PHP y BD (`apt update && apt upgrade`, `unattended-upgrades`).
- **Cortafuegos**: abrir solo 80/443 y SSH (preferiblemente restringido por IP).
- SSH con **claves**, sin login de root ni contraseñas; `fail2ban` contra fuerza bruta.
- **No exponer la BD**: `bind-address = 127.0.0.1` y `mysql_secure_installation`.

**Cifrado**
- **HTTPS obligatorio** (certificado de Let's Encrypt con `certbot`), redirigir 80 → 443, solo **TLS 1.2/1.3** y cifrados robustos.
- Cabecera **HSTS** (`Strict-Transport-Security`).

**Configuración de NGINX**
- `server_tokens off;` para no revelar la versión.
- Cabeceras de seguridad: `X-Content-Type-Options: nosniff`, `X-Frame-Options` / `Content-Security-Policy`, `Referrer-Policy`.
- `autoindex off` (no listar directorios) y denegar ficheros ocultos y sensibles (`.git`, `.env`, `.htpasswd`, ficheros de configuración, copias `.bak`).
- `default_server` que rechace peticiones a hosts desconocidos.
- Limitar tamaño y velocidad: `client_max_body_size`, `limit_req`, `limit_conn`, *timeouts* razonables.
- Zonas de administración con autenticación + restricción por IP.

**Permisos y aplicación**
- Mínimo privilegio: procesos como `www-data`, ficheros 644 y directorios 755, configuraciones con contraseñas 640 y fuera del *document root* si es posible.
- PHP: `expose_php = Off`, `display_errors = Off`, `log_errors = On`, borrar `phpinfo()`, deshabilitar funciones peligrosas si no se usan.
- Usuario de BD propio con permisos mínimos; consultas preparadas; validar y escapar entradas (OWASP Top 10).
- **WAF** (ModSecurity con OWASP CRS, CrowdSec).

**Operación**
- Logs centralizados y revisados, monitorización y alertas.
- Copias de seguridad probadas.
- Auditorías periódicas (escáneres como `nikto`, OWASP ZAP, comprobación TLS con `testssl.sh`).
