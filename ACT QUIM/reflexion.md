# Reflexión: qué accesos fue necesario permitir y por qué

Nginx funciona con un **proceso maestro** que arranca como `root` y varios **workers** que corren como `www-data`. Los dos usan el mismo binario, así que comparten el perfil `/usr/sbin/nginx`. Casi todos los permisos "delicados" los necesita el maestro, que es el que prepara el entorno antes de bajar privilegios.

## Accesos permitidos

| Acceso | Regla | Quién lo usa | Por qué es necesario |
|---|---|---|---|
| Configuración | `/etc/nginx/** r` | Maestro | Leer `nginx.conf`, `mime.types`, `sites-enabled/*`. Solo **lectura**: si alguien compromete Nginx no puede cambiar su propia configuración |
| Contenido web | `/var/www/apparmor/** r`, `/var/www/html/** r` | Workers | Servir las páginas. Solo lectura: no se puede subir ni modificar contenido a través de Nginx |
| Logs | `/var/log/nginx/*.log w` | Maestro y workers | Escribir `access.log` y `error.log`. Sin esto Nginx ni arranca |
| Temporales | `/var/lib/nginx/** rw` | Maestro y workers | Guardar cuerpos de peticiones grandes y respuestas de proxy/FastCGI |
| PID | `/run/nginx.pid rw` | Maestro y `nginx -s` | systemd y `nginx -s reload` localizan el proceso maestro con este fichero |
| Usuarios y grupos | `include <abstractions/nameservice>` | Maestro | Resolver el usuario `www-data` (`/etc/passwd`, `/etc/group`, `nsswitch.conf`) |
| Librerías y sistema | `include <abstractions/base>`, `openssl` | Todos | Cargar `libc`, `libssl`, `libpcre2`, zona horaria, etc. |
| `capability net_bind_service` | | Maestro | Los puertos < 1024 (el 80) solo los puede abrir root |
| `capability setuid`, `setgid` | | Maestro → workers | Cambiar los workers a `www-data` (principio de mínimo privilegio) |
| `capability chown` | | Maestro | Hacer `chown` a `www-data` de los logs nuevos y de `/var/lib/nginx/*` |
| `capability dac_override` | | Maestro | Los logs son de `www-data:adm` con permisos `640`; root necesita esta capacidad para escribir en ficheros que no son suyos |
| `capability kill` | | Maestro | Enviar señales a los workers, que tienen otro UID |
| `network inet/inet6 stream` | | Maestro y workers | Socket TCP de escucha en el puerto 80 (IPv4 e IPv6) |
| `network unix stream` | | Maestro y workers | `socketpair` para comunicarse maestro ↔ workers |
| Señales | `signal (send, receive) peer=/usr/sbin/nginx`, `signal (receive) peer=unconfined` | Todos | `reload` (HUP), `reopen` (USR1, lo usa logrotate), `stop` (QUIT/TERM) |

## Qué **no** se permitió (y por qué es importante)

- **Ejecutar otros programas** (`ix`, `px`, `/bin/sh`…): Nginx no los necesita. Si un atacante explota una vulnerabilidad, no puede abrir una shell.
- **Escribir en `/etc/nginx` o en `/var/www`**: no se pueden modificar la configuración ni las webs desde el proceso.
- **Leer fuera de las rutas listadas** (`/etc/shadow`, `/home`, `/root`, `/srv/fuera`…): demostrado con el 403 + `DENIED` de `/fuera/`.
- **Sockets UDP o raw**: Nginx solo sirve HTTP por TCP.
- **Capacidades como `sys_admin`, `sys_module` o `sys_ptrace`**: un Nginx comprometido no puede cargar módulos del kernel ni espiar otros procesos.

## Conclusiones

- `aa-genprof` en modo complain es la forma práctica de descubrir **todo** lo que hace un servicio, incluso cosas poco evidentes como `dac_override` (por los permisos de los logs) o las señales entre procesos.
- Hay que **ejercitar todas las funciones** durante el aprendizaje (reload, reopen, restart, errores 404…). Lo que no se use en esa fase falta en el perfil y falla después en enforce. Por ejemplo, si no se hace `nginx -s reopen`, la rotación de logs de la noche dejaría Nginx sin poder recibir la señal.
- Usar **Glob** (`/etc/nginx/**`) en vez de rutas exactas evita que el perfil se rompa al añadir un sitio nuevo, pero hay que hacerlo con cuidado: `/var/www/** r` permitiría leer todas las webs del servidor, no solo la nuestra.
- AppArmor no sustituye a los permisos Unix: los **complementa**. En la prueba de `/fuera/` los permisos Unix dejaban leer el fichero y fue AppArmor quien lo bloqueó.
