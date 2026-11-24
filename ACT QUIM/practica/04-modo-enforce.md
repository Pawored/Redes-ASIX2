# 4. Perfil final en modo enforce

## 4.1 Activar el modo enforce

```bash
sudo aa-enforce /usr/sbin/nginx
```

```
Setting /usr/sbin/nginx to enforce mode.
```

`aa-enforce` quita `flags=(complain)` de la cabecera del perfil y lo recarga en el kernel. Se reinicia Nginx para que todos los procesos arranquen ya confinados:

```bash
sudo systemctl restart nginx
sudo aa-status | grep -A1 'enforce mode' | head
ps auxZ | grep [n]ginx
```

```
/usr/sbin/nginx (enforce)  root      ... nginx: master process /usr/sbin/nginx -g daemon on; master_process on;
/usr/sbin/nginx (enforce)  www-data  ... nginx: worker process
/usr/sbin/nginx (enforce)  www-data  ... nginx: worker process
```

## 4.2 Perfil final

Fichero `/etc/apparmor.d/usr.sbin.nginx` (copia en [config/apparmor/usr.sbin.nginx](../config/apparmor/usr.sbin.nginx)):

```
# Last Modified: Tue Nov 24 2026
# Perfil AppArmor para Nginx (Ubuntu 24.04, nginx 1.24.0)
# Generado con aa-genprof + aa-logprof y ajustado a mano.
abi <abi/4.0>,

include <tunables/global>

/usr/sbin/nginx {
  include <abstractions/base>
  include <abstractions/nameservice>
  include <abstractions/openssl>

  # --- Capacidades (proceso maestro, que arranca como root) ---
  capability chown,             # chown de logs y /var/lib/nginx/* a www-data
  capability dac_override,      # root escribe en logs que son de www-data:adm (640)
  capability kill,              # el maestro (root) envía señales a los workers (www-data)
  capability net_bind_service,  # escuchar en el puerto 80 (< 1024)
  capability setgid,            # bajar privilegios de los workers a www-data
  capability setuid,

  # --- Red ---
  network inet stream,          # TCP IPv4 (puerto 80)
  network inet6 stream,         # TCP IPv6
  network unix stream,          # socketpair maestro <-> workers

  # --- Señales ---
  signal (send, receive) peer=/usr/sbin/nginx,   # nginx -s reload/stop, maestro -> workers
  signal (receive) peer=unconfined,              # systemd y kill desde la shell

  # --- Binario y módulos dinámicos ---
  /usr/sbin/nginx mr,
  /usr/lib/nginx/modules/ r,
  /usr/lib/nginx/modules/*.so mr,
  /usr/share/nginx/modules-available/ r,
  /usr/share/nginx/modules-available/*.conf r,

  # --- Configuración (solo lectura) ---
  /etc/nginx/ r,
  /etc/nginx/** r,

  # --- Contenido web (solo lectura) ---
  /var/www/html/ r,
  /var/www/html/** r,
  /var/www/apparmor/ r,
  /var/www/apparmor/** r,

  # --- Logs (escritura) ---
  /var/log/nginx/ r,
  /var/log/nginx/*.log w,

  # --- Ficheros temporales (cuerpos de petición, proxy, fastcgi...) ---
  /var/lib/nginx/ r,
  /var/lib/nginx/** rw,

  # --- PID ---
  /run/nginx.pid rw,

  # --- Información del sistema ---
  @{PROC}/sys/crypto/fips_enabled r,
}
```

La sintaxis se ha validado con el compilador de AppArmor:

```bash
sudo apparmor_parser -QT /etc/apparmor.d/usr.sbin.nginx && echo OK
```

## 4.3 Partes del perfil

| Parte | Qué hace |
|---|---|
| `abi <abi/4.0>` | Versión de las reglas que entiende el kernel (AppArmor 4 en Ubuntu 24.04) |
| `include <tunables/global>` | Variables como `@{PROC}` (= `/proc/`) o `@{HOME}` |
| `/usr/sbin/nginx { ... }` | El perfil se aplica a todo proceso que ejecute ese binario |
| `include <abstractions/...>` | Bloques de reglas ya hechos: `base` (librerías, `/dev/null`, locales, zona horaria…), `nameservice` (usuarios y grupos), `openssl` (configuración de OpenSSL) |
| Permisos de fichero | `r` leer · `w` escribir/crear · `m` mapear en memoria como ejecutable (librerías) |
| `**` | Cualquier fichero o subcarpeta a cualquier profundidad; `*` solo un nivel |

Todo lo que no aparece en el perfil está **denegado por defecto**.
