# AppArmor – Confinar Nginx

Alumno: Pau Bonet

## Objetivo

Instalar un servidor web **Nginx** en Ubuntu y confinarlo con un perfil de **AppArmor**:

1. Generar un perfil inicial con `aa-genprof` en **modo complain** (aprendizaje).
2. Usar Nginx con normalidad para que AppArmor registre todos los accesos que necesita.
3. Revisar los registros y ajustar el perfil con `aa-logprof`.
4. Pasar el perfil a **modo enforce** con `aa-enforce`.
5. Comprobar que Nginx sigue funcionando y que **todo lo que no está en el perfil queda bloqueado**.

## Entregable

| Punto del entregable | Dónde está |
|---|---|
| 1. Configuración inicial de Nginx | [01 – Nginx](practica/01-nginx.md) |
| 2. Creación y ajuste del perfil de AppArmor | [02 – Perfil con aa-genprof](practica/02-perfil-aa-genprof.md) |
| 3. Evidencias de los registros en modo complain | [03 – Modo complain y registros](practica/03-modo-complain.md) |
| 4. Perfil final en modo enforce | [04 – Modo enforce](practica/04-modo-enforce.md) · [perfil](config/apparmor/usr.sbin.nginx) |
| 5. Comprobación de que Nginx responde | [05 – Comprobación](practica/05-comprobacion.md) |
| 6. Reflexión sobre los accesos permitidos | [Reflexión](reflexion.md) |
| Diferencias entre complain y enforce | [Complain vs enforce](teoria/complain-vs-enforce.md) |

## Entorno

| Elemento | Valor |
|---|---|
| Sistema | Ubuntu 24.04 LTS (máquina virtual en VMware Workstation) |
| Red | Adaptador 1 NAT (`ens33`, internet) + Adaptador 2 Solo anfitrión (`ens37`, `192.168.88.128`) |
| Cliente | Windows (anfitrión, `192.168.88.1`) con navegador, y `curl` en la propia VM |
| Servidor web | nginx 1.24.0 (paquete de Ubuntu) |
| AppArmor | 4.0.1 (`apparmor`, `apparmor-utils`) |
| Sitio de prueba | `apparmor.asir.local` → `/var/www/apparmor` |
| Registros | Journal del kernel (`journalctl -k`) y `/var/log/syslog` |

## Esquema

```
 Cliente (navegador / curl)
        │  HTTP :80   Host: apparmor.asir.local
        ▼
 ┌──────────────────── Ubuntu 24.04 ─────────────────────────────┐
 │                                                               │
 │   nginx master (root) ──fork──► workers (www-data)            │
 │        │                                                      │
 │        └── confinados por el perfil /usr/sbin/nginx           │
 │                                                               │
 │   Permitido:  /etc/nginx/** (r)      /var/www/apparmor/** (r) │
 │               /var/log/nginx/*.log (w)  /var/lib/nginx/** (rw)│
 │               puerto 80, señales entre procesos nginx         │
 │                                                               │
 │   Todo lo demás ──► DENIED (bloqueado y registrado)           │
 └───────────────────────────────────────────────────────────────┘
```

## Estructura

```
├── README.md                     ← esta página
├── SUMMARY.md                    ← índice para GitBook
├── practica/                     ← pasos realizados (01 a 05)
├── teoria/complain-vs-enforce.md ← diferencias entre modos
├── reflexion.md                  ← qué accesos se permitieron y por qué
├── config/
│   ├── apparmor/usr.sbin.nginx   ← perfil final (/etc/apparmor.d/usr.sbin.nginx)
│   └── nginx/apparmor.asir.local ← server block (/etc/nginx/sites-available/)
├── www/apparmor/index.html       ← web de prueba (/var/www/apparmor/)
├── scripts/evidencias.sh         ← guarda las salidas de cada fase en evidencias/
├── evidencias/                   ← salidas reales de la VM
└── img/                          ← capturas de pantalla
```

## Comandos clave

```bash
sudo aa-genprof /usr/sbin/nginx        # crea el perfil y lo pone en complain
sudo aa-logprof                        # revisa los registros nuevos y amplía el perfil
sudo aa-complain /usr/sbin/nginx       # modo aprendizaje (registra, no bloquea)
sudo aa-enforce  /usr/sbin/nginx       # modo aplicación (bloquea y registra)
sudo aa-status                         # qué perfiles hay cargados y en qué modo
sudo journalctl -k | grep apparmor     # ver los eventos de AppArmor
```

## Fuentes

- `man aa-genprof`, `man aa-logprof`, `man aa-enforce`, `man aa-complain`, `man apparmor.d`
- [Wiki oficial de AppArmor](https://gitlab.com/apparmor/apparmor/-/wikis/home)
- Abstracciones usadas: `/etc/apparmor.d/abstractions/{base,nameservice,openssl}`
