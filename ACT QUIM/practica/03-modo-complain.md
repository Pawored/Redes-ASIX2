# 3. Modo complain: evidencias de los registros

En modo complain AppArmor **no bloquea nada**: deja hacer al proceso y escribe en el registro del kernel cada acceso que el perfil no cubre, marcado como `apparmor="ALLOWED"`.

## 3.1 Comprobar que el perfil está en complain

```bash
sudo aa-status
```

```
apparmor module is loaded.
... profiles are loaded.
... profiles are in enforce mode.
   ...
1 profiles are in complain mode.
   /usr/sbin/nginx
...
3 processes are in complain mode.
   /usr/sbin/nginx (1234) 
   /usr/sbin/nginx (1235) 
   /usr/sbin/nginx (1236) 
```

```bash
ps auxZ | grep [n]ginx
```

```
/usr/sbin/nginx (complain)  root      1234 ... nginx: master process ...
/usr/sbin/nginx (complain)  www-data  1235 ... nginx: worker process
/usr/sbin/nginx (complain)  www-data  1236 ... nginx: worker process
```

## 3.2 Dónde se guardan los registros

| Fuente | Comando | Cuándo |
|---|---|---|
| Journal del kernel | `sudo journalctl -k \| grep 'apparmor='` | Siempre (systemd) |
| syslog | `sudo grep 'apparmor=' /var/log/syslog` | Si está instalado `rsyslog` (Ubuntu Server sí lo trae) |
| auditd | `sudo grep 'apparmor=' /var/log/audit/audit.log` | Si está instalado `auditd` |

> `aa-genprof` y `aa-logprof` leen `/var/log/audit/audit.log` o `/var/log/syslog`. Si en la VM no existe ninguno de los dos (Ubuntu Desktop 24.04 puede no traer `rsyslog`), instalar uno: `sudo apt install rsyslog`.

## 3.3 Ver los eventos de Nginx

Filtrar solo los de nuestro perfil:

```bash
sudo journalctl -k --no-pager | grep 'profile="/usr/sbin/nginx"' | grep 'apparmor="ALLOWED"'
```

Formato de cada tipo de evento (los valores de `pid`, `audit(...)` y fecha cambian en cada máquina):

**Lectura de configuración:**

```
audit: type=1400 audit(...): apparmor="ALLOWED" operation="open" class="file" profile="/usr/sbin/nginx" name="/etc/nginx/nginx.conf" pid=1234 comm="nginx" requested_mask="r" denied_mask="r" fsuid=0 ouid=0
```

**Lectura de la web (la hace un worker, `fsuid=33` = www-data):**

```
audit: type=1400 audit(...): apparmor="ALLOWED" operation="open" class="file" profile="/usr/sbin/nginx" name="/var/www/apparmor/index.html" pid=1235 comm="nginx" requested_mask="r" denied_mask="r" fsuid=33 ouid=0
```

**Escritura de logs:**

```
audit: type=1400 audit(...): apparmor="ALLOWED" operation="open" class="file" profile="/usr/sbin/nginx" name="/var/log/nginx/apparmor.access.log" pid=1234 comm="nginx" requested_mask="wc" denied_mask="wc" fsuid=0 ouid=0
```

**Capacidades:**

```
audit: type=1400 audit(...): apparmor="ALLOWED" operation="capable" class="cap" profile="/usr/sbin/nginx" pid=1234 comm="nginx" capability=10 capname="net_bind_service"
audit: type=1400 audit(...): apparmor="ALLOWED" operation="capable" class="cap" profile="/usr/sbin/nginx" pid=1234 comm="nginx" capability=1 capname="dac_override"
audit: type=1400 audit(...): apparmor="ALLOWED" operation="capable" class="cap" profile="/usr/sbin/nginx" pid=1235 comm="nginx" capability=6 capname="setgid"
```

**Red y señales:**

```
audit: type=1400 audit(...): apparmor="ALLOWED" operation="create" class="net" profile="/usr/sbin/nginx" pid=1234 comm="nginx" family="inet" sock_type="stream" protocol=6 requested_mask="create" denied_mask="create"
audit: type=1400 audit(...): apparmor="ALLOWED" operation="signal" class="signal" profile="/usr/sbin/nginx" pid=1234 comm="nginx" requested_mask="send" denied_mask="send" signal=quit peer="/usr/sbin/nginx"
```

Cómo leer un evento:

| Campo | Significado |
|---|---|
| `apparmor="ALLOWED"` | Acceso no cubierto por el perfil, **permitido** porque está en complain |
| `operation` | Qué intentó: `open`, `mkdir`, `capable`, `create`, `signal`… |
| `profile` | Perfil que lo registró |
| `name` | Fichero afectado |
| `requested_mask` | Permiso pedido: `r` lectura, `w` escritura, `c` crear, `a` añadir… |
| `fsuid` | UID con el que accede (0 = root → maestro, 33 = www-data → worker) |
| `capname` | Capacidad de Linux solicitada |

## 3.4 Resumen de los eventos

Para ver de un vistazo qué tipos de acceso ha pedido Nginx:

```bash
sudo journalctl -k --no-pager | grep 'profile="/usr/sbin/nginx"' \
  | grep -oE 'operation="[^"]+"|capname="[^"]+"' | sort | uniq -c | sort -rn
```

Guardar la evidencia de esta fase (estado del perfil, procesos y todos los eventos `ALLOWED`):

```bash
sudo ./scripts/evidencias.sh 02-complain
```

El resultado queda en [evidencias/02-complain.txt](../evidencias/).

📷 Capturas: `img/03-aa-genprof.png` (preguntas de aa-genprof), `img/03-complain-logs.png` (eventos ALLOWED).
