# 1. Configuración inicial de Nginx

## 1.1 Instalar los paquetes

```bash
sudo apt update
sudo apt install -y nginx apparmor apparmor-utils curl
```

| Paquete | Para qué |
|---|---|
| `nginx` | Servidor web |
| `apparmor` | Módulo de seguridad y `apparmor_parser` (normalmente ya viene instalado en Ubuntu) |
| `apparmor-utils` | Herramientas `aa-genprof`, `aa-logprof`, `aa-complain`, `aa-enforce`, `aa-status` |
| `curl` | Hacer peticiones HTTP de prueba desde la terminal |

Comprobaciones:

```bash
nginx -v                 # nginx version: nginx/1.24.0 (Ubuntu)
sudo aa-status | head -3 # apparmor module is loaded.
ls /etc/apparmor.d/ | grep -i nginx   # no sale nada: Ubuntu NO trae perfil para Nginx
```

> Que no exista ningún perfil para Nginx es justo lo que nos interesa: tras la instalación Nginx corre **sin confinar** (`unconfined`) y hay que crearle el perfil desde cero.

```bash
ps auxZ | grep [n]ginx
```

```
unconfined  root      ... nginx: master process /usr/sbin/nginx -g daemon on; master_process on;
unconfined  www-data  ... nginx: worker process
```

## 1.2 Crear la web de prueba

```bash
sudo mkdir -p /var/www/apparmor
sudo nano /var/www/apparmor/index.html
```

Contenido: [www/apparmor/index.html](../www/apparmor/index.html).

```bash
sudo chown -R root:root /var/www/apparmor
sudo chmod -R 755 /var/www/apparmor
```

También se crea un directorio **fuera** de lo que permitirá el perfil, para demostrar después el bloqueo:

```bash
sudo mkdir -p /srv/fuera
echo "<h1>Esto NO deberia verse en modo enforce</h1>" | sudo tee /srv/fuera/index.html
```

## 1.3 Server block

```bash
sudo nano /etc/nginx/sites-available/apparmor.asir.local
```

Contenido: [config/nginx/apparmor.asir.local](../config/nginx/apparmor.asir.local).

| Directiva | Valor | Para qué |
|---|---|---|
| `server_name` | `apparmor.asir.local` | Virtual host por nombre |
| `root` | `/var/www/apparmor` | Carpeta de la web (será lectura permitida en el perfil) |
| `access_log` / `error_log` | `/var/log/nginx/apparmor.*.log` | Logs propios del sitio (escritura permitida en el perfil) |
| `location /fuera/` | `alias /srv/fuera/` | Ruta **no** permitida en el perfil → prueba de bloqueo |

Activar el sitio y recargar:

```bash
sudo ln -s /etc/nginx/sites-available/apparmor.asir.local /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## 1.4 Resolución de nombres

En la VM:

```bash
echo "127.0.0.1 apparmor.asir.local" | sudo tee -a /etc/hosts
```

En Windows (como administrador), añadir a `C:\Windows\System32\drivers\etc\hosts`:

```
192.168.88.128  apparmor.asir.local
```

## 1.5 Comprobación antes de AppArmor

```bash
curl -i http://apparmor.asir.local/
curl -s -o /dev/null -w '%{http_code}\n' http://apparmor.asir.local/fuera/
```

Salida esperada:

```
HTTP/1.1 200 OK
Server: nginx/1.24.0 (Ubuntu)
Content-Type: text/html
...
<h1>Nginx confinado con AppArmor</h1>

200
```

Sin AppArmor, **las dos rutas responden 200**: Nginx puede leer cualquier fichero que le permitan los permisos Unix, incluido `/srv/fuera`.

Guardar la evidencia de esta fase:

```bash
sudo ./scripts/evidencias.sh 01-nginx
```

📷 Captura: `img/01-nginx-sin-apparmor.png` (navegador de Windows mostrando la web).
