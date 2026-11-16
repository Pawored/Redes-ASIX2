# 1. Instalación y preparación

## 1.0 Red de la máquina virtual (VMware)

La VM tiene dos adaptadores de red:

| Adaptador | Tipo | Interfaz | IP | Para qué |
|---|---|---|---|---|
| 1 | NAT (VMnet8) | `ens33` | 192.168.126.128 | Salida a internet para `apt` |
| 2 | Solo anfitrión (VMnet1) | `ens37` | 192.168.88.128 | Acceso desde Windows a las webs |

Comprobación desde Windows (PowerShell): `ping 192.168.88.128`.

> Ojo: la dirección que acaba en `.255` (192.168.88.255) es la de *broadcast* de la red, no la de la VM; hacerle ping no responde.

## 1.1 Instalar los paquetes

```bash
sudo apt update
sudo apt install -y nginx php8.3-fpm php8.3-mysql mariadb-server apache2-utils curl
```

| Paquete | Para qué |
|---|---|
| `nginx` | Servidor web |
| `php8.3-fpm` | Intérprete PHP como servicio FastCGI (NGINX no ejecuta PHP por sí mismo) |
| `php8.3-mysql` | Extensión PDO/mysqli para conectar PHP con MySQL/MariaDB |
| `mariadb-server` | Base de datos |
| `apache2-utils` | Trae el comando `htpasswd` para crear usuarios de la zona protegida |

> En Debian 12 la versión de PHP es la 8.2: cambia `8.3` por `8.2` en paquetes, servicios y en la ruta del socket.

## 1.2 Arrancar y habilitar los servicios

```bash
sudo systemctl enable --now nginx php8.3-fpm mariadb
systemctl status nginx php8.3-fpm mariadb --no-pager
```

Comprobaciones rápidas:

```bash
nginx -v                       # nginx version: nginx/1.24.0 (Ubuntu)
php -v                         # PHP 8.3.x
ls -l /run/php/                # debe existir php8.3-fpm.sock
sudo ss -tlnp | grep ':80'     # nginx escuchando en el 80
```

## 1.3 Asegurar MariaDB

```bash
sudo mysql_secure_installation
```

Responder: cambiar contraseña de root (o mantener `unix_socket`), **eliminar usuarios anónimos**, **no permitir login remoto de root**, **eliminar la BD test** y recargar privilegios.

## 1.4 Cortafuegos (si UFW está activo)

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx HTTP'      # puerto 80
sudo ufw enable
sudo ufw status
```

## 1.5 Resolución de nombres

No tenemos DNS, así que los dominios se resuelven con el fichero `hosts` **del equipo desde el que vas a navegar** (el cliente). En mi caso el servidor tiene la IP `192.168.88.128` (interfaz `ens37`, ver `ip a`).

**Linux / el propio servidor** – `/etc/hosts`:

```
192.168.88.128   web1.asir.local web2.asir.local php.asir.local app.asir.local
```

**Windows (equipo anfitrión)** – `C:\Windows\System32\drivers\etc\hosts` (abrir el Bloc de notas como administrador):

```
192.168.88.128   web1.asir.local web2.asir.local php.asir.local app.asir.local
```

Comprobar: `ping web1.asir.local` debe responder la IP del servidor.

## 1.6 Ocultar la versión de NGINX (buena práctica)

En `/etc/nginx/nginx.conf`, dentro del bloque `http { }`, descomentar:

```nginx
server_tokens off;
```

Así las páginas de error y la cabecera `Server` muestran solo `nginx` y no `nginx/1.24.0 (Ubuntu)`, lo que da menos información a un atacante.

## 1.7 Flujo de trabajo para cada sitio

Siempre que se crea o modifica un sitio se sigue el mismo ciclo:

```bash
sudo nano /etc/nginx/sites-available/SITIO          # 1. crear/editar el server block
sudo ln -s /etc/nginx/sites-available/SITIO /etc/nginx/sites-enabled/   # 2. habilitarlo
sudo nginx -t                                       # 3. comprobar sintaxis
sudo systemctl reload nginx                         # 4. aplicar sin cortar conexiones
```
