# 6. Pruebas

Resumen de las pruebas realizadas. La salida completa está en [`pruebas/resultados_curl.txt`](../pruebas/resultados_curl.txt).

| # | Prueba | Comando | Resultado esperado | Resultado |
|---|---|---|---|---|
| 1 | web1 por nombre | `curl http://web1.asir.local/` | Página "Web estática 1" | ✅ 200 |
| 2 | web2 por nombre | `curl http://web2.asir.local/` | Página "Web estática 2" | ✅ 200 |
| 3 | Misma IP, distinto Host | `curl -H "Host: web2.asir.local" http://127.0.0.1/` | web2 | ✅ 200 |
| 4 | Acceso por IP | `curl http://127.0.0.1/` | Conexión cerrada por `return 444` (curl muestra `000`) | ✅ |
| 5 | Zona privada sin login | `curl -I http://web1.asir.local/privado/` | 401 + `WWW-Authenticate` | ✅ 401 |
| 6 | Zona privada contraseña mala | `curl -u alumno:malapass ...` | 401 | ✅ 401 |
| 7 | Zona privada contraseña buena | `curl -u alumno:Asir2026 ...` | Contenido privado | ✅ 200 |
| 8 | Fichero oculto | `curl http://web1.asir.local/.htpasswd` | Denegado | ✅ 403 |
| 9 | PHP se ejecuta | `curl http://php.asir.local/?nombre=ASIR` | "Hola, ASIR" + SAPI `fpm-fcgi` | ✅ 200 |
| 10 | XSS | `?nombre=<script>...` | Se muestra escapado | ✅ |
| 11 | .php inexistente | `curl http://php.asir.local/noexiste.php` | 404 (no se pasa a PHP) | ✅ 404 |
| 12 | Lectura BD | `curl http://app.asir.local/` | Tabla con contactos | ✅ 200 |
| 13 | Inserción BD | `curl -X POST -d "accion=alta&..."` | "Contacto añadido" y fila nueva en MariaDB | ✅ |
| 14 | Inyección SQL | `nombre=x' OR '1'='1` | Guardado como texto | ✅ |
| 15 | Credenciales BD | `curl http://app.asir.local/config.php` | Denegado | ✅ 403 |

Comandos útiles para depurar mientras se prueba:

```bash
sudo nginx -t                                   # sintaxis
sudo tail -f /var/log/nginx/app_error.log       # errores del sitio en tiempo real
sudo tail -f /var/log/nginx/app_access.log      # peticiones y código devuelto
sudo journalctl -u php8.3-fpm -n 30             # errores de PHP-FPM
curl -I http://web1.asir.local/                 # solo cabeceras (código de estado)
```
