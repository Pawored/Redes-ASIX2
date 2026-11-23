# 6. Examinar infecciones de Trickbot

**Objetivo:** reconocer el patrón de tráfico de Trickbot, un troyano bancario y ladrón de credenciales activo desde 2016. Se distribuye por malspam o lo instalan otros malware como Emotet.

**Pcaps** (malware-traffic-analysis.net, 25/09/2019, contraseña `infected`):

- `2019-09-25-Trickbot-gtag-ono19-infection-traffic.pcap.zip` (Trickbot por malspam)
- `2019-09-25-Emotet-infection-with-Trickbot-in-AD-environment.pcap.zip` (Emotet + Trickbot en Active Directory)

Los resultados de esta parte son los del artículo de Unit 42; no están comprobados con tshark.

## A) Trickbot por malspam

Aplicar **basic** y después **basic+**. La cadena de infección:

1. El enlace del correo descarga un ZIP con `InvoiceAndStatement.lnk` desde `www.dchristjan[.]com`.
2. El .lnk descarga el ejecutable inicial de Trickbot desde `144.91.69[.]195`.
3. Comprobaciones de IP pública contra servicios legítimos (`ipecho[.]net`, `icanhazip[.]com`, `checkip.amazonaws[.]com`, `ipinfo[.]io`…). No son maliciosas por sí solas, pero en este contexto son un indicador.
4. C2 por HTTPS en puertos no estándar: varios intentos al 443 y conexión con éxito a `187.58.56[.]26:449`. Los puertos 447/449 son típicos de Trickbot.
5. Robo de datos: filtrar `http.request and tcp.port eq 8082`. POST a `170.238.117[.]187` con la ruta `/ono19/BACHMANN-BTO-PC_W617601.<id>/`. El sufijo `81/` lleva contraseñas, `83/` datos de formularios y `90/` información del sistema. `ono19` es el *gtag* (identificador de campaña). Usar Follow > TCP Stream.

   > **Captura pendiente (T6-01).**

6. Más ejecutables disfrazados: filtrar `http.request and ip contains .png`. Son 3 GET a ficheros .png cuya cabecera dice `image/png` pero que en realidad son EXE/DLL. Exportarlos con Export Objects > HTTP y comprobarlo con `file`.

   > **Captura pendiente (T6-02).**

## Certificados

Filtrar `tls.handshake.type == 11` y abrir el certificado del tráfico al puerto 449. Trae los valores por defecto de OpenSSL: countryName=AU, stateOrProvinceName=*Some-State*, organizationName=*Internet Widgits Pty Ltd*. Compararlo con uno legítimo de Microsoft (US, Washington, Redmond, Microsoft Corporation).

> **Captura pendiente (T6-03):** los dos certificados.

## B) Trickbot instalado por Emotet en un AD

El cliente infectado es 10.9.25.102 y el controlador de dominio 10.9.25.9. Emotet usa HTTP con datos codificados; Trickbot usa HTTPS. Se busca el tráfico SMB repetido por el TCP 445 del cliente al DC (seguir el flujo desde el puerto 49321, un exploit tipo EternalBlue). Después el propio DC descarga Trickbot desde `185.98.87[.]185/wredneg2.png`. Es movimiento lateral.

> **Captura pendiente (T6-04).**
