# Incidencias

Problemas encontrados durante la práctica, con el mensaje real, la causa y la solución. La salida completa está en [`pruebas/incidencias_reproducidas.txt`](pruebas/incidencias_reproducidas.txt).

## 1. `nginx -t` falla: `socket() [::]:80 failed (97: Address family not supported by protocol)`

```
nginx: [emerg] socket() [::]:80 failed (97: Address family not supported by protocol)
nginx: configuration file /etc/nginx/nginx.conf test failed
```

- **Causa:** los server blocks tienen `listen [::]:80;` (IPv6) pero la máquina tiene IPv6 desactivado.
- **Solución:** quitar las líneas `listen [::]:80;` de todos los sitios (incluido `default`) o activar IPv6 en el sistema. Volver a ejecutar `sudo nginx -t`.

## 2. `502 Bad Gateway` en la web PHP

```
[crit] connect() to unix:/run/php/php8.3-fpm.sock failed (2: No such file or directory)
while connecting to upstream, server: php.asir.local
```

- **Causa:** NGINX intenta pasar la petición a PHP-FPM, pero el servicio no está arrancado (no existe el socket). Lo mismo ocurre si el `fastcgi_pass` apunta a otra versión (`php8.1-fpm.sock`) o a un socket con otro nombre.
- **Solución:**
  ```bash
  sudo systemctl enable --now php8.3-fpm
  ls -l /run/php/                                         # debe aparecer php8.3-fpm.sock
  grep '^listen' /etc/php/8.3/fpm/pool.d/www.conf         # debe coincidir con fastcgi_pass
  ```

## 3. El fichero `.php` se descarga en vez de ejecutarse (fuga de código fuente)

```
$ curl -sI http://web1.asir.local/test.php | grep Content-Type
Content-Type: application/octet-stream
$ curl -s http://web1.asir.local/test.php
<?php echo "hola"; ?>
```

- **Causa:** el sitio no tiene el bloque `location ~ \.php$` con `fastcgi_pass`, así que NGINX trata el `.php` como un fichero cualquiera.
- **Riesgo de seguridad:** cualquiera puede leer el código, incluidas las contraseñas de la BD (*source code disclosure*).
- **Solución:** añadir el bloque FastCGI en los sitios PHP y **no** dejar ficheros `.php` en los sitios estáticos. Si el navegador sigue descargándolo tras corregirlo, borrar la caché del navegador.

## 4. `403 Forbidden` en un directorio

```
[error] directory index of "/var/www/web2/vacio/" is forbidden
```

- **Causa:** el directorio no tiene fichero índice (`index.html`) y el listado de directorios (`autoindex`) está desactivado. También aparece 403 si `www-data` no tiene permisos de lectura (`Permission denied` en el log).
- **Solución:** crear el índice o revisar permisos (`chown -R www-data:www-data`, directorios 755, ficheros 644). No se activa `autoindex` por seguridad.

## 5. `500 Internal Server Error` en la web con BD

```
FastCGI sent in stderr: "PHP message: Error BD: SQLSTATE[HY000] [1045]
Access denied for user 'agenda_user'@'localhost' (using password: YES)"
```

- **Causa:** contraseña incorrecta en `config.php` (otras causas posibles: usuario creado para `'%'` en vez de `'localhost'`, falta el `GRANT`, MariaDB parado o falta la extensión `php8.3-mysql` → `could not find driver`).
- **Solución:** corregir la contraseña y comprobar:
  ```bash
  sudo mariadb -e "SHOW GRANTS FOR 'agenda_user'@'localhost';"
  mariadb -u agenda_user -p agenda -e "SELECT COUNT(*) FROM contactos;"
  php -m | grep -i pdo_mysql
  ```
- **Nota:** gracias a `error_log()` el detalle técnico queda en `/var/log/nginx/app_error.log` y al usuario solo se le muestra un mensaje genérico.

## 6. Las tildes salen mal en la web (`Ana GarcÃa`)

- **Causa:** el `.sql` se importó sin indicar la codificación; el cliente `mariadb` interpretó el fichero UTF-8 como latin1 y guardó los caracteres mal (*mojibake*).
- **Solución:** añadir `SET NAMES utf8mb4;` al principio del `.sql` (o importar con `mariadb --default-character-set=utf8mb4 < agenda.sql`), recrear la BD y usar `charset=utf8mb4` en el DSN de PDO. Comprobación: `SELECT HEX(nombre)` muestra `C3AD` para la "í" (UTF-8 correcto).

## 7. Error de sintaxis al recargar

```
[emerg] invalid number of arguments in "root" directive in /etc/nginx/sites-enabled/web2.asir.local:8
```

- **Causa:** faltaba el `;` al final de `root /var/www/web2`, así que NGINX leyó la línea siguiente como parte de la directiva.
- **Solución:** añadir el `;`. Hacer **siempre** `sudo nginx -t` antes de `systemctl reload nginx`: si hay un error, el reload no se aplica y el servidor sigue funcionando con la configuración anterior.

## 8. El dominio no carga desde el navegador del anfitrión

- **Causa:** el nombre `web1.asir.local` no se resuelve en el equipo cliente (solo se añadió al `/etc/hosts` del servidor), o se accede por IP y responde el `default_server`.
- **Solución:** añadir la línea al `hosts` del equipo cliente (en Windows `C:\Windows\System32\drivers\etc\hosts`, editado como administrador) y acceder siempre por nombre. Comprobar con `ping web1.asir.local`.

## 9. No responde el ping a la máquina virtual

```
PS C:\Users\Alumno> ping 192.168.88.255
Haciendo ping a 192.168.88.255 con 32 bytes de datos:
Tiempo de espera agotado para esta solicitud.
```

- **Causa:** estaba haciendo ping a `192.168.88.255`, que es la dirección de **broadcast** de la red `192.168.88.0/24`, no la IP de la VM.
- **Solución:** consultar la IP real con `ip a` en Ubuntu (interfaz `ens37`, adaptador Solo anfitrión): `192.168.88.128`. `ping 192.168.88.128` responde correctamente y esa es la IP que se pone en el `hosts` de Windows.
