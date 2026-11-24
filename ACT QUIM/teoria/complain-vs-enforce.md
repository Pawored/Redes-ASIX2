# Diferencias entre los modos complain y enforce

AppArmor es un sistema de **control de acceso obligatorio** (MAC): además de los permisos Unix normales (DAC: propietario, grupo, `rwx`), cada programa con perfil solo puede hacer lo que su perfil dice, **aunque se ejecute como root**.

Un perfil puede cargarse en dos modos principales:

| | Complain (aprendizaje) | Enforce (aplicación) |
|---|---|---|
| ¿Bloquea los accesos no permitidos? | **No** | **Sí** |
| ¿Los registra? | Sí, como `apparmor="ALLOWED"` | Sí, como `apparmor="DENIED"` |
| Qué ve el programa | Funciona igual que sin perfil | Recibe un error (`EACCES`, "Permission denied") |
| En `ps auxZ` / `aa-status` | `/usr/sbin/nginx (complain)` | `/usr/sbin/nginx (enforce)` |
| En el fichero del perfil | `/usr/sbin/nginx flags=(complain) {` | `/usr/sbin/nginx {` |
| Comando | `aa-complain /usr/sbin/nginx` | `aa-enforce /usr/sbin/nginx` |
| Para qué sirve | Crear o ampliar un perfil sin romper el servicio | Proteger el servicio en producción |
| Riesgo | No protege nada mientras está activo | Si falta una regla, el servicio falla |

## Cómo se usan juntos

1. **Complain**: el servicio funciona con normalidad y AppArmor apunta todo lo que haría falta permitir.
2. `aa-genprof` / `aa-logprof` convierten esos registros en reglas.
3. **Enforce**: a partir de aquí todo lo que no esté en el perfil se bloquea.
4. Si al actualizar el servicio falla algo, se vuelve temporalmente a complain, se ejecuta `aa-logprof` y se vuelve a enforce.

## Ejemplo en esta práctica

La misma petición `GET /fuera/` (fichero `/srv/fuera/index.html`, fuera del perfil):

| Modo | Respuesta HTTP | Registro |
|---|---|---|
| Sin perfil | 200 | — |
| Complain | 200 | `apparmor="ALLOWED" operation="open" ... name="/srv/fuera/index.html"` |
| Enforce | **403** | `apparmor="DENIED" operation="open" ... name="/srv/fuera/index.html"` |

## Otros modos (para saber que existen)

- **unconfined**: el proceso no tiene perfil (Nginx recién instalado en Ubuntu).
- **audit**: con la regla `audit` delante se registra un acceso aunque esté permitido.
- **deny**: una regla `deny` bloquea siempre y tiene prioridad sobre las de permitir; con `aa-logprof` se crea pulsando **D**.
- **kill** y **unconfined** como `flags=` (AppArmor 4): matar el proceso ante una violación, o registrar sin confinar.
