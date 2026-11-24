# 2. Creación y ajuste del perfil con aa-genprof

`aa-genprof` crea un perfil casi vacío para un ejecutable, lo carga en **modo complain** y luego lee los registros del sistema para ir proponiendo reglas. Se necesitan **dos terminales**.

## 2.1 Terminal 1: lanzar aa-genprof

```bash
sudo aa-genprof /usr/sbin/nginx
```

```
Updating AppArmor profiles in /etc/apparmor.d.
Writing updated profile for /usr/sbin/nginx.
Setting /usr/sbin/nginx to complain mode.

Before you begin, you may wish to check if a
profile already exists for the application you
wish to confine. See the following wiki page for
more information:
https://gitlab.com/apparmor/apparmor/wikis/Profiles

Profiling: /usr/sbin/nginx

Please start the application to be profiled in
another window and exercise its functionality now.

Once completed, select the "Scan" option below in
order to scan the system logs for AppArmor events.

For each AppArmor event, you will be given the
opportunity to choose whether the access should be
allowed or denied.

[(S)can system log for AppArmor events] / (F)inish
```

En este momento existe `/etc/apparmor.d/usr.sbin.nginx` con un perfil mínimo y `flags=(complain)`. **No pulsar nada todavía.**

## 2.2 Terminal 2: usar Nginx (fase de aprendizaje)

Un perfil solo se aplica a los procesos que arrancan **después** de cargarlo, así que primero se reinicia Nginx:

```bash
sudo systemctl restart nginx
ps auxZ | grep [n]ginx
```

```
/usr/sbin/nginx (complain)  root      ... nginx: master process /usr/sbin/nginx -g daemon on; master_process on;
/usr/sbin/nginx (complain)  www-data  ... nginx: worker process
```

Ahora se "ejercita" todo lo que hará Nginx en producción, para que quede registrado:

```bash
sudo nginx -t                                   # test de configuración
curl -s http://apparmor.asir.local/ >/dev/null  # servir la web
curl -s http://apparmor.asir.local/noexiste     # 404 (escribe en error.log)
curl -s http://localhost/ >/dev/null            # sitio por defecto (/var/www/html)
sudo systemctl reload nginx                     # recarga (señal HUP)
sudo nginx -s reopen                            # reabrir logs (señal USR1, lo usa logrotate)
sudo systemctl restart nginx                    # parar y arrancar
```

> **No** se visita `/fuera/` durante el aprendizaje: lo que no se use ahora no entrará en el perfil, y así se podrá comprobar el bloqueo después.

## 2.3 Terminal 1: escanear los registros

Pulsar **S**. `aa-genprof` va mostrando cada acceso registrado y pregunta qué hacer. Ejemplos:

**Capacidades:**

```
Profile:    /usr/sbin/nginx
Capability: net_bind_service
Severity:   8

 [1 - capability net_bind_service,]
(A)llow / [(D)eny] / (I)gnore / Audi(t) / Abo(r)t / (F)inish
```

→ **A** (Allow). Se repite con `setuid`, `setgid`, `chown`, `dac_override` y `kill`.

**Ficheros con abstracción disponible:**

```
Profile:  /usr/sbin/nginx
Path:     /etc/nsswitch.conf
New Mode: r
Severity: unknown

 [1 - include <abstractions/nameservice>]
  2 - /etc/nsswitch.conf r,
(A)llow / [(D)eny] / (I)gnore / (G)lob / Glob with (E)xtension / (N)ew / Audi(t) / Abo(r)t / (F)inish
```

→ Elegir **1** y **A**: la abstracción `nameservice` agrupa `/etc/passwd`, `/etc/group`, `/etc/nsswitch.conf`, el socket de `nscd`, etc. (Nginx los necesita para resolver el usuario `www-data`).

**Ficheros que conviene generalizar (Glob):**

```
Profile:  /usr/sbin/nginx
Path:     /etc/nginx/sites-enabled/apparmor.asir.local
New Mode: r
Severity: unknown

 [1 - /etc/nginx/sites-enabled/apparmor.asir.local r,]
(A)llow / [(D)eny] / (I)gnore / (G)lob / Glob with (E)xtension / (N)ew / Audi(t) / (O)wner permissions off / Abo(r)t / (F)inish
```

→ Pulsar **G** dos veces hasta que quede `/etc/nginx/** r,` y luego **A**. Así cualquier fichero de configuración nuevo no rompe el perfil.

Se hace igual con:

| Acceso registrado | Regla final (con Glob) |
|---|---|
| `/var/www/apparmor/index.html r` | `/var/www/apparmor/** r,` |
| `/var/log/nginx/apparmor.access.log w` | `/var/log/nginx/*.log w,` |
| `/var/lib/nginx/body` (y proxy, fastcgi…) | `/var/lib/nginx/** rw,` |

**Red y señales:**

```
Profile:        /usr/sbin/nginx
Network Family: inet
Socket Type:    stream

 [1 - network inet stream,]
(A)llow / [(D)eny] / (I)gnore / Audi(t) / Abo(r)t / (F)inish
```

```
Profile:  /usr/sbin/nginx
Signal:   quit
Peer:     /usr/sbin/nginx
Access:   send

 [1 - signal send set=quit peer=/usr/sbin/nginx,]
```

→ **A** en todos.

Al terminar la ronda, guardar:

```
= Changed Local Profiles =

The following local profiles were changed. Would you like to save them?

 [1 - /usr/sbin/nginx]
(S)ave Changes / Save Selec(t)ed Profile / [(V)iew Changes] / View Changes b/w (C)lean profiles / Abo(r)t
```

→ **S**. Después, **F** para terminar `aa-genprof`.

## 2.4 Segunda pasada con aa-logprof

Tras guardar, se vuelve a usar Nginx (otra vez los comandos del punto 2.2) y se ejecuta:

```bash
sudo aa-logprof
```

`aa-logprof` hace lo mismo que el escaneo de `aa-genprof` pero sin crear el perfil: solo pregunta por los **eventos nuevos**. Se repite hasta que diga que no hay nada nuevo:

```
Reading log entries from /var/log/syslog.
```

(sin preguntas = el perfil ya cubre todo lo que hace Nginx).

## 2.5 Ajustes a mano

Por último se edita el perfil para dejarlo limpio y comentado:

```bash
sudo nano /etc/apparmor.d/usr.sbin.nginx
```

Ajustes realizados respecto a lo que generó la herramienta:

| Ajuste | Motivo |
|---|---|
| Unir las reglas de señales en `signal (send, receive) peer=/usr/sbin/nginx,` | `aa-logprof` crea una regla por cada señal (`quit`, `hup`, `usr1`, `term`…) |
| Añadir `signal (receive) peer=unconfined,` | systemd (sin confinar) envía las señales de `reload`/`stop` |
| Añadir `network inet6 stream,` | El server block escucha también en `[::]:80` |
| Añadir `/usr/lib/nginx/modules/*.so mr,` | Si se instala un módulo dinámico (`libnginx-mod-*`) no hay que rehacer el perfil |
| Añadir `/var/www/html/** r,` | Sitio por defecto de Ubuntu |
| Comentarios en cada bloque | Para poder explicar cada regla |
| **No** añadir `/srv/fuera/` | Debe quedar bloqueado |

Recargar el perfil (sigue en complain) y comprobar la sintaxis:

```bash
sudo apparmor_parser -r /etc/apparmor.d/usr.sbin.nginx
sudo aa-status | grep -A2 complain
```

El perfil final está en [config/apparmor/usr.sbin.nginx](../config/apparmor/usr.sbin.nginx).
