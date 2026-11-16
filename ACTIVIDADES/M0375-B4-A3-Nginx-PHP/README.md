# Práctica NGINX + PHP + MySQL

**Módulo 0375 – Servicios en Red · Tema B4 · CFGS ASIR**

Alumno: Pau Bonet

Esta documentación recoge la parte práctica y la parte escrita de la práctica de NGINX:

| Apartado de la rúbrica | Dónde está | Puntos |
|---|---|---|
| Dos webs estáticas con dominio distinto y mismo puerto (80) | [Webs estáticas](practica/02-webs-estaticas.md) | 2 |
| Acceder a zona protegida | [Zona protegida](practica/03-zona-protegida.md) | 1 |
| Una web con PHP | [Web PHP](practica/04-web-php.md) | 2 |
| Una web con PHP + MySQL | [Web PHP + MySQL](practica/05-web-php-mysql.md) | 3 |
| Parte escrita | [Teoría](teoria/01-servidores-web.md), [NGINX](teoria/02-nginx.md), [Códigos de estado](teoria/03-codigos-estado.md), [Incidencias](incidencias.md) | 2 |

## Entorno

| Elemento | Valor |
|---|---|
| Sistema | Ubuntu 24.04 LTS (máquina virtual en VMware Workstation) |
| Red | Adaptador 1 NAT (`ens33`, internet) + Adaptador 2 Solo anfitrión (`ens37`, `192.168.88.128`) |
| Cliente | Windows (anfitrión, `192.168.88.1`) con Chrome |
| Servidor web | nginx 1.24.0 |
| PHP | PHP 8.3 (PHP-FPM, socket `/run/php/php8.3-fpm.sock`) |
| Base de datos | MariaDB 10.11 |
| Cliente de pruebas | navegador + `curl` |

## Esquema

```
                         ┌──────────────── Ubuntu Server (nginx :80) ───────────────┐
 Cliente                 │                                                          │
 (navegador / curl)      │  Host: web1.asir.local ──► /var/www/web1  (estática)     │
   │                     │        └─ /privado/    ──► auth_basic (.htpasswd)        │
   │  HTTP :80           │  Host: web2.asir.local ──► /var/www/web2  (estática)     │
   └────────────────────►│  Host: php.asir.local  ──► /var/www/php ─┐               │
                         │  Host: app.asir.local  ──► /var/www/app ─┤ FastCGI       │
                         │                                          ▼               │
                         │                                  PHP-FPM 8.3 ──► MariaDB │
                         └──────────────────────────────────────────────────────────┘
```

Los cuatro sitios comparten **la misma IP y el mismo puerto 80**. NGINX decide qué sitio servir leyendo la cabecera HTTP `Host` y comparándola con la directiva `server_name` de cada bloque `server` (*virtual hosts* basados en nombre).

## Estructura del repositorio

```
├── README.md                 ← esta página
├── SUMMARY.md                ← índice para GitBook
├── practica/                 ← pasos realizados (parte práctica)
├── teoria/                   ← respuestas a las preguntas (parte escrita)
├── incidencias.md            ← problemas encontrados y solución
├── config/nginx/             ← server blocks (/etc/nginx/sites-available/)
├── www/                      ← contenido de cada web (/var/www/)
├── sql/agenda.sql            ← base de datos de la web PHP + MySQL
├── pruebas/                  ← salida real de las pruebas con curl
└── img/                      ← capturas
```
