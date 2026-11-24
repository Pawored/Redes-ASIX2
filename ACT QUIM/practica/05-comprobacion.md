# 5. Comprobación con el perfil en enforce

## 5.1 Nginx sigue funcionando

```bash
systemctl is-active nginx
sudo nginx -t
curl -i http://apparmor.asir.local/
```

Salida esperada:

```
active
nginx: the configuration file /etc/nginx/nginx.conf syntax is ok
nginx: configuration file /etc/nginx/nginx.conf test is successful
HTTP/1.1 200 OK
Server: nginx/1.24.0 (Ubuntu)
Content-Type: text/html
...
<h1>Nginx confinado con AppArmor</h1><p>Si ves esta página, el perfil en modo enforce permite servir /var/www/apparmor.</p>
```

Operaciones de administración, que también deben funcionar:

```bash
sudo systemctl reload nginx  && echo "reload OK"
sudo nginx -s reopen         && echo "reopen OK"
sudo systemctl restart nginx && echo "restart OK"
curl -s -o /dev/null -w '%{http_code}\n' http://apparmor.asir.local/noexiste   # 404
tail -n 3 /var/log/nginx/apparmor.access.log                                    # los logs se siguen escribiendo
```

Y no debe haber **ningún** evento `DENIED` en el uso normal:

```bash
sudo journalctl -k --since "10 min ago" | grep 'apparmor="DENIED"' | grep nginx || echo "Sin denegaciones"
```

```
Sin denegaciones
```

📷 Captura: `img/05-enforce-web-ok.png` (navegador de Windows mostrando la web con el perfil en enforce).

## 5.2 El confinamiento bloquea lo que no está permitido

La ruta `/fuera/` apunta a `/srv/fuera/`, que **no** está en el perfil. Los permisos Unix sí dejan leerla a `www-data` (antes de AppArmor respondía 200), pero ahora:

```bash
curl -i http://apparmor.asir.local/fuera/
```

```
HTTP/1.1 403 Forbidden
Server: nginx/1.24.0 (Ubuntu)
...
```

Nginx lo deja en su log de errores:

```bash
sudo tail -n 1 /var/log/nginx/apparmor.error.log
```

```
... [error] ...: *1 open() "/srv/fuera/index.html" failed (13: Permission denied), client: 127.0.0.1, server: apparmor.asir.local, request: "GET /fuera/ HTTP/1.1", host: "apparmor.asir.local"
```

Y AppArmor lo registra como **DENIED**:

```bash
sudo journalctl -k --since "5 min ago" | grep 'apparmor="DENIED"' | grep nginx
```

```
audit: type=1400 audit(...): apparmor="DENIED" operation="open" class="file" profile="/usr/sbin/nginx" name="/srv/fuera/index.html" pid=... comm="nginx" requested_mask="r" denied_mask="r" fsuid=33 ouid=0
```

Esto demuestra que, aunque un atacante consiguiera cambiar la configuración de Nginx (o explotar una vulnerabilidad de *path traversal*), el proceso **no podría leer nada fuera de las rutas del perfil**, por ejemplo `/etc/shadow` o `/home`.

📷 Captura: `img/05-enforce-403.png` (403 en el navegador + línea DENIED en la terminal).

## 5.3 Guardar las evidencias

```bash
sudo ./scripts/evidencias.sh 03-enforce
```

Genera [evidencias/03-enforce.txt](../evidencias/) con: estado de AppArmor, procesos confinados, perfil cargado, respuestas HTTP (200, 404 y 403) y eventos `DENIED`.

## 5.4 Volver atrás (si algo falla)

```bash
sudo aa-complain /usr/sbin/nginx     # vuelve a modo aprendizaje
sudo systemctl restart nginx
sudo aa-logprof                      # añadir lo que faltaba
sudo aa-enforce /usr/sbin/nginx
```

Para quitar el perfil del todo: `sudo aa-disable /usr/sbin/nginx`.
