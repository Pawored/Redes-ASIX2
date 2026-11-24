# AppArmor – Confinar Nginx (ACT QUIM)

Alumno: Pau Bonet

Nginx queda confinado con un perfil de AppArmor creado en modo complain y aplicado en modo enforce. El servicio sigue respondiendo (200) y todo lo que no está en el perfil queda bloqueado (403 + DENIED).

## Índice

1. [Objetivo](#objetivo)
2. [Entorno](#entorno)
3. [Paso 1. Configuración inicial de Nginx](#paso-1-configuración-inicial-de-nginx)
4. [Paso 2. Creación y ajuste del perfil con aa-genprof](#paso-2-creación-y-ajuste-del-perfil-con-aa-genprof)
5. [Paso 3. Modo complain: evidencias de los registros](#paso-3-modo-complain-evidencias-de-los-registros)
6. [Paso 4. Perfil final en modo enforce](#paso-4-perfil-final-en-modo-enforce)
7. [Paso 5. Comprobación con el perfil en enforce](#paso-5-comprobación-con-el-perfil-en-enforce)
8. [Diferencias entre los modos complain y enforce](#diferencias-entre-los-modos-complain-y-enforce)
9. [Reflexión](#reflexión-qué-accesos-fue-necesario-permitir-y-por-qué)
10. [Anexos](#anexos)

## Objetivo

1. Instalar Nginx en Ubuntu con un sitio de prueba.
2. Generar un perfil inicial con `aa-genprof` en modo complain (aprendizaje).
3. Usar Nginx con normalidad para que AppArmor registre todos los accesos que necesita.
4. Revisar los registros y ajustar el perfil con `aa-logprof`.
5. Pasar el perfil a modo enforce con `aa-enforce`.
6. Comprobar que Nginx sigue funcionando y que lo no permitido se bloquea.

## Entorno

| Elemento | Valor |
| --- | --- |
| Sistema | Ubuntu 24.04 LTS (máquina virtual en VMware Workstation) |
| Red | Adaptador 1 NAT (`ens33`, internet) + Adaptador 2 Solo anfitrión (`ens37`, `192.168.88.128`) |
| Cliente | Windows (anfitrión, `192.168.88.1`) con navegador, y `curl` en la VM |
| Servidor web | nginx 1.24.0 (paquete de Ubuntu) |
| AppArmor | 4.0.1 (`apparmor`, `apparmor-utils`) |
| Sitio de prueba | `apparmor.asir.local` → `/var/www/apparmor` |
| Ruta de prueba de bloqueo | `/fuera/` → `/srv/fuera` (no está en el perfil) |
| Registros | `journalctl -k` y `/var/log/syslog` |

### Comandos clave

| Comando | Para qué |
| --- | --- |
| `sudo aa-genprof /usr/sbin/nginx` | Crea el perfil y lo pone en complain |
| `sudo aa-logprof` | Revisa los registros nuevos y amplía el perfil |
| `sudo aa-complain /usr/sbin/nginx` | Modo aprendizaje: registra, no bloquea |
| `sudo aa-enforce /usr/sbin/nginx` | Modo aplicación: bloquea y registra |
| `sudo aa-status` | Perfiles cargados y su modo |
| `sudo journalctl -k \| grep apparmor` | Ver los eventos de AppArmor |

## Paso 1. Configuración inicial de Nginx

Al acabar este paso, Nginx sirve la web de prueba y, sin AppArmor, también puede leer `/srv/fuera`.

### 1.1 Instalar los paquetes

```bash
sudo apt update
sudo apt install -y nginx apparmor apparmor-utils curl
```

| Paquete | Para qué |
| --- | --- |
| `nginx` | Servidor web |
| `apparmor` | Módulo de seguridad y `apparmor_parser` (normalmente ya viene en Ubuntu) |
| `apparmor-utils` | `aa-genprof`, `aa-logprof`, `aa-complain`, `aa-enforce`, `aa-status` |
| `curl` | Peticiones HTTP de prueba desde la terminal |

```bash
nginx -v                              # nginx version: nginx/1.24.0 (Ubuntu)
sudo aa-status | head -3              # apparmor module is loaded.
ls /etc/apparmor.d/ | grep -i nginx   # no sale nada: Ubuntu NO trae perfil para Nginx
```

Que no exista ningún perfil es lo que nos interesa: tras instalarlo, Nginx corre sin confinar (`unconfined`) y hay que crearle el perfil desde cero.

### 1.2 Crear la web de prueba

```bash
sudo mkdir -p /var/www/apparmor
sudo nano /var/www/apparmor/index.html
```

Su contenido está en el [Anexo B](#anexo-b-web-de-prueba). Después se ajustan propietario y permisos:

```bash
sudo chown -R root:root /var/www/apparmor
sudo chmod -R 755 /var/www/apparmor
```

Se crea también un directorio que **no** estará en el perfil, para demostrar el bloqueo al final:

```bash
sudo mkdir -p /srv/fuera
echo "<h1>Esto NO deberia verse en modo enforce</h1>" | sudo tee /srv/fuera/index.html
```

### 1.3 Server block

```bash
sudo nano /etc/nginx/sites-available/apparmor.asir.local
```

Su contenido está en el [Anexo A](#anexo-a-server-block).

| Directiva | Valor | Para qué |
| --- | --- | --- |
| `server_name` | `apparmor.asir.local` | Virtual host por nombre |
| `root` | `/var/www/apparmor` | Carpeta de la web (lectura permitida en el perfil) |
| `access_log` / `error_log` | `/var/log/nginx/apparmor.*.log` | Logs del sitio (escritura permitida en el perfil) |
| `location /fuera/` | `alias /srv/fuera/` | Ruta **no** permitida en el perfil: prueba de bloqueo |

```bash
sudo ln -s /etc/nginx/sites-available/apparmor.asir.local /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 1.4 Resolución de nombres

En la VM:

```bash
echo "127.0.0.1 apparmor.asir.local" | sudo tee -a /etc/hosts
```

En Windows (anfitrión), en `C:\Windows\System32\drivers\etc\hosts`:

```
192.168.88.128  apparmor.asir.local
```

### 1.5 Comprobación antes de AppArmor

```bash
ps auxZ | grep [n]ginx
curl -i http://apparmor.asir.local/
curl -s -o /dev/null -w '%{http_code}\n' http://apparmor.asir.local/fuera/
```

Resultado: los procesos salen como `unconfined`, la web devuelve `HTTP/1.1 200 OK` y `/fuera/` también da `200`. Sin AppArmor, Nginx puede leer cualquier fichero que le permitan los permisos Unix.

**Captura 1.** `ps auxZ | grep nginx` con los procesos en `unconfined`.



**Captura 2.** Navegador de Windows en `http://apparmor.asir.local/` mostrando la web, y el `curl` de `/fuera/` con `200`.



## Paso 2. Creación y ajuste del perfil con aa-genprof

`aa-genprof` crea un perfil casi vacío para Nginx, lo carga en modo complain y después lee los registros para proponer reglas. Se usan **dos terminales** en la VM.

### 2.1 Terminal 1: lanzar aa-genprof

```bash
sudo aa-genprof /usr/sbin/nginx
```

Salida (resumida):

```
Writing updated profile for /usr/sbin/nginx.
Setting /usr/sbin/nginx to complain mode.
...
Profiling: /usr/sbin/nginx

Please start the application to be profiled in
another window and exercise its functionality now.
...
[(S)can system log for AppArmor events] / (F)inish
```

Ya existe `/etc/apparmor.d/usr.sbin.nginx` con un perfil mínimo y `flags=(complain)`. En este punto todavía no se pulsa nada.

**Captura 3.** Terminal 1 con `aa-genprof` esperando y el texto “(S)can system log for AppArmor events”.



### 2.2 Terminal 2: usar Nginx (fase de aprendizaje)

Un perfil solo se aplica a los procesos que arrancan después de cargarlo, así que primero se reinicia Nginx:

```bash
sudo systemctl restart nginx
ps auxZ | grep [n]ginx
```

Ahora los procesos salen como `/usr/sbin/nginx (complain)`.

**Captura 4.** Terminal 2 con `ps auxZ` y los procesos en `(complain)`.



Después se ejercitan todas las funciones que Nginx usará en producción, para que queden registradas:

```bash
sudo nginx -t                                   # test de configuración
curl -s http://apparmor.asir.local/ >/dev/null  # servir la web
curl -s http://apparmor.asir.local/noexiste     # 404 (escribe en error.log)
curl -s http://localhost/ >/dev/null            # sitio por defecto (/var/www/html)
sudo systemctl reload nginx                     # recarga (señal HUP)
sudo nginx -s reopen                            # reabrir logs (señal USR1, la usa logrotate)
sudo systemctl restart nginx                    # parar y arrancar
```

Durante el aprendizaje **no** se visita `/fuera/`: lo que no se usa en esta fase no entra en el perfil, y así después se puede comprobar el bloqueo.

### 2.3 Terminal 1: escanear los registros

En la terminal 1 se pulsa **S**. `aa-genprof` muestra cada acceso registrado y pregunta qué hacer. Respuestas dadas:

| Tipo de acceso | Ejemplo | Respuesta |
| --- | --- | --- |
| Capacidad | `capability net_bind_service` (también `setuid`, `setgid`, `chown`, `dac_override`, `kill`) | **A** (Allow) |
| Fichero con abstracción | `/etc/nsswitch.conf` → opción `include <abstractions/nameservice>` | Opción del `include` y **A** |
| Configuración | `/etc/nginx/sites-enabled/apparmor.asir.local r` | **G** (Glob) hasta que quede `/etc/nginx/** r,` y **A** |
| Web | `/var/www/apparmor/index.html r` | **G** hasta `/var/www/apparmor/** r,` y **A** |
| Logs | `/var/log/nginx/apparmor.access.log w` | **G** hasta `/var/log/nginx/*.log w,` y **A** |
| Temporales | `/var/lib/nginx/body` (y proxy, fastcgi…) | **G** hasta `/var/lib/nginx/** rw,` y **A** |
| Red | `network inet stream` | **A** |
| Señales | `signal send set=quit peer=/usr/sbin/nginx` | **A** |

Ejemplo de pregunta:

```
Profile:    /usr/sbin/nginx
Capability: net_bind_service
Severity:   8

 [1 - capability net_bind_service,]
(A)llow / [(D)eny] / (I)gnore / Audi(t) / Abo(r)t / (F)inish
```

Al terminar la ronda aparece “Changed Local Profiles”: se guarda con **S** (Save Changes) y se sale con **F**.

**Captura 5.** Pregunta de `aa-genprof` durante el escaneo de los registros.



### 2.4 Segunda pasada con aa-logprof

Se repiten los comandos del punto 2.2 y se ejecuta:

```bash
sudo aa-logprof
```

`aa-logprof` hace lo mismo que el escaneo de `aa-genprof`, pero solo pregunta por los eventos **nuevos**. Se repite hasta que no hace ninguna pregunta, lo que indica que el perfil ya cubre todo lo que hace Nginx.

`aa-logprof` lee `/var/log/syslog` o `/var/log/audit/audit.log`; si el sistema no tiene ninguno de los dos, hay que instalar `rsyslog` (`sudo apt install rsyslog`).

### 2.5 Ajustes a mano

Por último se edita el perfil para dejarlo limpio y comentado (versión final en el [Paso 4.2](#42-perfil-final)):

```bash
sudo nano /etc/apparmor.d/usr.sbin.nginx
```

Mientras sigue en pruebas, la cabecera conserva `/usr/sbin/nginx flags=(complain) {`. Se recarga y se comprueba:

```bash
sudo apparmor_parser -r /etc/apparmor.d/usr.sbin.nginx
sudo aa-status | grep -A2 complain
```

| Ajuste respecto a lo generado | Motivo |
| --- | --- |
| Unir las señales en `signal (send, receive) peer=/usr/sbin/nginx,` | `aa-logprof` crea una regla por cada señal (`quit`, `hup`, `usr1`, `term`…) |
| Añadir `signal (receive) peer=unconfined,` | systemd (sin confinar) envía las señales de `reload` y `stop` |
| Añadir `network inet6 stream,` | El sitio escucha también en `[::]:80` |
| Añadir `/usr/lib/nginx/modules/*.so mr,` | Si se instala un módulo dinámico no hay que rehacer el perfil |
| Añadir `/var/www/html/** r,` | Sitio por defecto de Ubuntu |
| Comentar cada bloque | Para poder explicar cada regla |
| **No** añadir `/srv/fuera/` | Debe quedar bloqueado |

## Paso 3. Modo complain: evidencias de los registros

En modo complain AppArmor no bloquea nada: deja hacer al proceso y registra cada acceso que el perfil no cubre como `apparmor="ALLOWED"`.

### 3.1 Comprobar que el perfil está en complain

```bash
sudo aa-status
ps auxZ | grep [n]ginx
```

En `aa-status` debe salir `/usr/sbin/nginx` bajo “profiles are in complain mode”, y los procesos de Nginx bajo “processes are in complain mode”.

**Captura 6.** `aa-status` con `/usr/sbin/nginx` en “complain mode”.



### 3.2 Dónde se guardan los registros

| Fuente | Comando | Cuándo |
| --- | --- | --- |
| Journal del kernel | `sudo journalctl -k \| grep 'apparmor='` | Siempre (systemd) |
| syslog | `sudo grep 'apparmor=' /var/log/syslog` | Si está instalado `rsyslog` (Ubuntu Server lo trae) |
| auditd | `sudo grep 'apparmor=' /var/log/audit/audit.log` | Si está instalado `auditd` |

### 3.3 Ver los eventos de Nginx

Solo los de nuestro perfil:

```bash
sudo journalctl -k --no-pager | grep 'profile="/usr/sbin/nginx"' | grep 'apparmor="ALLOWED"'
```

Resumen por tipo de acceso:

```bash
sudo journalctl -k --no-pager | grep 'profile="/usr/sbin/nginx"' \
  | grep -oE 'operation="[^"]+"|capname="[^"]+"' | sort | uniq -c | sort -rn
```

Ejemplos de cada tipo de evento registrado (sin los campos de fecha, `audit(...)` y `pid`):

```
# Lectura de configuración (maestro, fsuid=0 = root)
apparmor="ALLOWED" operation="open" class="file" profile="/usr/sbin/nginx" name="/etc/nginx/nginx.conf" comm="nginx" requested_mask="r" denied_mask="r" fsuid=0 ouid=0

# Lectura de la web (worker, fsuid=33 = www-data)
apparmor="ALLOWED" operation="open" class="file" profile="/usr/sbin/nginx" name="/var/www/apparmor/index.html" comm="nginx" requested_mask="r" denied_mask="r" fsuid=33 ouid=0

# Escritura de logs
apparmor="ALLOWED" operation="open" class="file" profile="/usr/sbin/nginx" name="/var/log/nginx/apparmor.access.log" comm="nginx" requested_mask="wc" denied_mask="wc" fsuid=0 ouid=0

# Capacidades
apparmor="ALLOWED" operation="capable" class="cap" profile="/usr/sbin/nginx" comm="nginx" capability=10 capname="net_bind_service"
apparmor="ALLOWED" operation="capable" class="cap" profile="/usr/sbin/nginx" comm="nginx" capability=1 capname="dac_override"

# Red y señales
apparmor="ALLOWED" operation="create" class="net" profile="/usr/sbin/nginx" comm="nginx" family="inet" sock_type="stream" protocol=6
apparmor="ALLOWED" operation="signal" class="signal" profile="/usr/sbin/nginx" comm="nginx" requested_mask="send" signal=quit peer="/usr/sbin/nginx"
```

| Campo | Significado |
| --- | --- |
| `apparmor="ALLOWED"` | Acceso no cubierto por el perfil, permitido porque está en complain |
| `operation` | Qué intentó: `open`, `mkdir`, `capable`, `create`, `signal`… |
| `profile` | Perfil que lo registró |
| `name` | Fichero afectado |
| `requested_mask` | Permiso pedido: `r` leer, `w` escribir, `c` crear, `a` añadir |
| `fsuid` | UID con el que accede: 0 = root (maestro), 33 = www-data (worker) |
| `capname` | Capacidad de Linux solicitada |

**Captura 7.** Terminal con los eventos `apparmor="ALLOWED"` de Nginx y el resumen por tipo.



## Paso 4. Perfil final en modo enforce

### 4.1 Activar el modo enforce

```bash
sudo aa-enforce /usr/sbin/nginx
sudo systemctl restart nginx
sudo aa-status | grep -i nginx
ps auxZ | grep [n]ginx
```

`aa-enforce` quita `flags=(complain)` de la cabecera del perfil y lo recarga. Tras reiniciar, los procesos salen como `/usr/sbin/nginx (enforce)`.

**Captura 8.** Salida de `aa-enforce` y `ps auxZ` con los procesos en `(enforce)`.



### 4.2 Perfil final

Fichero `/etc/apparmor.d/usr.sbin.nginx`. Su sintaxis se ha validado con `apparmor_parser` 4.0.1.

```
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

Comprobación de la sintaxis:

```bash
sudo apparmor_parser -QT /etc/apparmor.d/usr.sbin.nginx && echo OK
```

### 4.3 Partes del perfil

| Parte | Qué hace |
| --- | --- |
| `abi <abi/4.0>` | Versión de las reglas que entiende el kernel (AppArmor 4 en Ubuntu 24.04) |
| `include <tunables/global>` | Variables como `@{PROC}` (= `/proc/`) |
| `/usr/sbin/nginx { ... }` | El perfil se aplica a todo proceso que ejecute ese binario |
| `include <abstractions/...>` | Reglas ya hechas: `base` (librerías, locales, zona horaria), `nameservice` (usuarios y grupos), `openssl` |
| Permisos de fichero | `r` leer · `w` escribir o crear · `m` mapear en memoria como ejecutable (librerías) |
| `**` y `*` | `**` cualquier fichero o subcarpeta a cualquier profundidad; `*` solo un nivel |

Todo lo que no aparece en el perfil está **denegado por defecto**.

## Paso 5. Comprobación con el perfil en enforce

Con el perfil aplicado, Nginx responde 200 en su web y 403 en `/fuera/`, que antes daba 200.

### 5.1 Nginx sigue funcionando

```bash
systemctl is-active nginx
sudo nginx -t
curl -i http://apparmor.asir.local/
sudo systemctl reload nginx  && echo "reload OK"
sudo nginx -s reopen         && echo "reopen OK"
curl -s -o /dev/null -w '%{http_code}\n' http://apparmor.asir.local/noexiste   # 404
tail -n 3 /var/log/nginx/apparmor.access.log                                    # los logs se siguen escribiendo
sudo journalctl -k --since "10 min ago" | grep 'apparmor="DENIED"' | grep nginx || echo "Sin denegaciones"
```

| Prueba | Resultado |
| --- | --- |
| `systemctl is-active nginx` | `active` |
| `nginx -t` | `syntax is ok` y `test is successful` |
| `curl -i` a `/` | `HTTP/1.1 200 OK` y el HTML de la web |
| `reload` y `reopen` | `reload OK` y `reopen OK` |
| `/noexiste` | `404` |
| `access.log` | Las últimas peticiones aparecen escritas |
| Eventos DENIED en uso normal | `Sin denegaciones` |

**Captura 9.** Terminal con el `curl -i` a `/` (200 OK), `reload OK`, `reopen OK` y “Sin denegaciones”.



**Captura 10.** Navegador de Windows en `http://apparmor.asir.local/` mostrando la web con el perfil en enforce.



### 5.2 El confinamiento bloquea lo no permitido

`/fuera/` apunta a `/srv/fuera/`, que no está en el perfil. Los permisos Unix sí dejan leerlo a `www-data`, pero ahora AppArmor lo impide:

```bash
curl -i http://apparmor.asir.local/fuera/
sudo tail -n 1 /var/log/nginx/apparmor.error.log
sudo journalctl -k --since "5 min ago" | grep 'apparmor="DENIED"' | grep nginx
```

Resultado:

```
HTTP/1.1 403 Forbidden

[error] ... open() "/srv/fuera/index.html" failed (13: Permission denied), client: 127.0.0.1, server: apparmor.asir.local, request: "GET /fuera/ HTTP/1.1"

apparmor="DENIED" operation="open" class="file" profile="/usr/sbin/nginx" name="/srv/fuera/index.html" comm="nginx" requested_mask="r" denied_mask="r" fsuid=33 ouid=0
```

Aunque un atacante consiguiera cambiar la configuración de Nginx o explotar un fallo de *path traversal*, el proceso no podría leer nada fuera de las rutas del perfil, como `/etc/shadow` o `/home`.

**Captura 11.** `curl -i` a `/fuera/` con `403 Forbidden` y la línea `apparmor="DENIED"` de `/srv/fuera/index.html`.



### 5.3 Vuelta atrás si falta alguna regla

```bash
sudo aa-complain /usr/sbin/nginx   # vuelve a modo aprendizaje
sudo systemctl restart nginx
sudo aa-logprof                    # añadir lo que faltaba
sudo aa-enforce /usr/sbin/nginx
```

Para quitar el perfil del todo: `sudo aa-disable /usr/sbin/nginx`.

## Diferencias entre los modos complain y enforce

Complain registra sin bloquear y enforce bloquea y registra. AppArmor es un control de acceso obligatorio (MAC): además de los permisos Unix (DAC: propietario, grupo, `rwx`), cada programa con perfil solo puede hacer lo que dice su perfil, aunque se ejecute como root.

|  | Complain (aprendizaje) | Enforce (aplicación) |
| --- | --- | --- |
| ¿Bloquea lo no permitido? | No | Sí |
| ¿Lo registra? | Sí, como `apparmor="ALLOWED"` | Sí, como `apparmor="DENIED"` |
| Qué ve el programa | Funciona igual que sin perfil | Recibe un error (`EACCES`, “Permission denied”) |
| En `ps auxZ` / `aa-status` | `/usr/sbin/nginx (complain)` | `/usr/sbin/nginx (enforce)` |
| En el fichero del perfil | `/usr/sbin/nginx flags=(complain) {` | `/usr/sbin/nginx {` |
| Comando | `aa-complain /usr/sbin/nginx` | `aa-enforce /usr/sbin/nginx` |
| Para qué sirve | Crear o ampliar un perfil sin romper el servicio | Proteger el servicio en producción |
| Riesgo | No protege nada mientras está activo | Si falta una regla, el servicio falla |

Cómo se usan juntos:

1. **Complain**: el servicio funciona con normalidad y AppArmor apunta lo que haría falta permitir.
2. `aa-genprof` y `aa-logprof` convierten esos registros en reglas.
3. **Enforce**: a partir de aquí se bloquea todo lo que no esté en el perfil.
4. Si al actualizar el servicio falla algo, se vuelve a complain, se pasa `aa-logprof` y se vuelve a enforce.

Ejemplo en esta práctica, con la misma petición `GET /fuera/`:

| Modo | Respuesta HTTP | Registro |
| --- | --- | --- |
| Sin perfil | 200 | Ninguno |
| Complain | 200 | `apparmor="ALLOWED" operation="open" ... name="/srv/fuera/index.html"` |
| Enforce | 403 | `apparmor="DENIED" operation="open" ... name="/srv/fuera/index.html"` |

Otros modos que existen: **unconfined** (sin perfil, como Nginx recién instalado), reglas **audit** (registran aunque esté permitido), reglas **deny** (bloquean siempre; en `aa-logprof` se crean con **D**) y, en AppArmor 4, `flags=(kill)` para matar el proceso ante una violación.

## Reflexión: qué accesos fue necesario permitir y por qué

Nginx tiene un **proceso maestro** que arranca como root y varios **workers** que corren como `www-data`. Los dos usan el mismo binario y comparten el perfil `/usr/sbin/nginx`. Casi todos los permisos delicados los necesita el maestro, que prepara el entorno antes de bajar privilegios.

### Accesos permitidos

| Acceso | Regla | Quién | Por qué |
| --- | --- | --- | --- |
| Configuración | `/etc/nginx/** r` | Maestro | Leer `nginx.conf`, `mime.types`, `sites-enabled/*`. Solo lectura: Nginx no puede cambiar su propia configuración |
| Contenido web | `/var/www/apparmor/** r`, `/var/www/html/** r` | Workers | Servir las páginas. Solo lectura: no se puede modificar contenido a través de Nginx |
| Logs | `/var/log/nginx/*.log w` | Maestro y workers | Escribir `access.log` y `error.log`. Sin esto Nginx no arranca |
| Temporales | `/var/lib/nginx/** rw` | Maestro y workers | Cuerpos de peticiones grandes y respuestas de proxy/FastCGI |
| PID | `/run/nginx.pid rw` | Maestro y `nginx -s` | systemd y `nginx -s reload` localizan al maestro con este fichero |
| Usuarios y grupos | `include <abstractions/nameservice>` | Maestro | Resolver el usuario `www-data` |
| Librerías y sistema | `include <abstractions/base>`, `openssl` | Todos | Cargar `libc`, `libssl`, `libpcre2`, zona horaria |
| `capability net_bind_service` |  | Maestro | Los puertos por debajo de 1024 (el 80) solo los abre root |
| `capability setuid`, `setgid` |  | Maestro | Pasar los workers a `www-data` (mínimo privilegio) |
| `capability chown` |  | Maestro | Dar a `www-data` la propiedad de los logs nuevos y de `/var/lib/nginx/*` |
| `capability dac_override` |  | Maestro | Los logs son de `www-data:adm` con permisos `640`: root necesita esta capacidad para escribir en ficheros que no son suyos |
| `capability kill` |  | Maestro | Enviar señales a los workers, que tienen otro UID |
| `network inet/inet6 stream` |  | Todos | Socket TCP de escucha en el puerto 80 |
| `network unix stream` |  | Todos | `socketpair` para comunicar maestro y workers |
| Señales | `signal (send, receive) peer=/usr/sbin/nginx`, `signal (receive) peer=unconfined` | Todos | `reload` (HUP), `reopen` (USR1, logrotate), `stop` (QUIT/TERM) |

### Qué no se permitió

- **Ejecutar otros programas** (`/bin/sh`…): si alguien explota una vulnerabilidad, no puede abrir una shell.
- **Escribir en `/etc/nginx` o en `/var/www`**: no se puede modificar la configuración ni las webs desde el proceso.
- **Leer fuera de las rutas listadas** (`/etc/shadow`, `/home`, `/root`, `/srv/fuera`): demostrado con el 403 + DENIED de `/fuera/`.
- **Sockets UDP o raw**: Nginx solo sirve HTTP por TCP.
- **Capacidades como `sys_admin`, `sys_module` o `sys_ptrace`**: un Nginx comprometido no puede cargar módulos del kernel ni espiar otros procesos.

### Conclusiones

- El modo complain con `aa-genprof` descubre todo lo que hace un servicio, incluso accesos poco evidentes como `dac_override` (por los permisos de los logs) o las señales entre procesos.
- Hay que ejercitar todas las funciones durante el aprendizaje (reload, reopen, restart, 404). Lo que no se use en esa fase falta en el perfil y falla después en enforce.
- Usar Glob (`/etc/nginx/**`) evita que el perfil se rompa al añadir un sitio, pero con cuidado: `/var/www/** r` permitiría leer todas las webs del servidor.
- AppArmor no sustituye a los permisos Unix, los complementa: en la prueba de `/fuera/` los permisos Unix dejaban leer el fichero y fue AppArmor quien lo bloqueó.

## Anexos

### Anexo A. Server block

Fichero `/etc/nginx/sites-available/apparmor.asir.local`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name apparmor.asir.local;

    root  /var/www/apparmor;
    index index.html;

    access_log /var/log/nginx/apparmor.access.log;
    error_log  /var/log/nginx/apparmor.error.log;

    location / {
        try_files $uri $uri/ =404;
    }

    # Ruta FUERA del perfil de AppArmor: sirve para demostrar
    # que en modo enforce Nginx no puede leerla (403 + DENIED).
    location /fuera/ {
        alias /srv/fuera/;
    }
}
```

### Anexo B. Web de prueba

Fichero `/var/www/apparmor/index.html`:

```html
<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Nginx + AppArmor</title></head>
<body><h1>Nginx confinado con AppArmor</h1><p>Si ves esta página, el perfil en modo enforce permite servir /var/www/apparmor.</p></body></html>
```

### Fuentes

- Páginas de manual: `man aa-genprof`, `man aa-logprof`, `man aa-enforce`, `man aa-complain`, `man apparmor.d`
- [Wiki oficial de AppArmor](https://gitlab.com/apparmor/apparmor/-/wikis/home)
