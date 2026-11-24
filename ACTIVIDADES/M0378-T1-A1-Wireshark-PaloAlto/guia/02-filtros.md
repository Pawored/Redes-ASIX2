# 2. Filtros de visualización

**Objetivo:** crear 3 botones de filtro reutilizables y usarlos en 5 pcaps con 5 tipos de malware.

**ZIP:** `Wireshark-tutorial-filter-expressions-5-pcaps.zip` · con el perfil *Customized* activo.

## 2.1 Crear los botones

Se escribe la expresión en la barra de filtro (verde = válida, roja = error, amarilla = aviso), se pulsa el **+** a la derecha de la barra, se pone la etiqueta y se guarda.

| Botón | Expresión | Para qué sirve |
|---|---|---|
| basic | `(http.request or tls.handshake.type eq 1) and !(ssdp)` | Peticiones HTTP y dominios HTTPS, sin ruido SSDP |
| basic+ | `(http.request or tls.handshake.type eq 1 or (tcp.flags.syn eq 1 and tcp.flags.ack eq 0)) and !(ssdp)` | Añade los SYN: tráfico no web y conexiones fallidas |
| basic+dns | `(http.request or tls.handshake.type eq 1 or (tcp.flags.syn eq 1 and tcp.flags.ack eq 0) or dns) and !(ssdp)` | Añade DNS: relaciona dominios con IPs |

Operadores: `and`/`&&`, `or`/`||`, `!`/`not`, `eq`/`==`, `contains`.

> **Captura pendiente (T2-01):** los 3 botones en la barra.

## 2.2 Recorrido por los 5 pcaps

| Pcap | Filtro | Qué se debe encontrar | Malware |
|---|---|---|---|
| 1 de 5 | `http.request` | 49 POST desde 10.8.15.101 a `194.55.224[.]9/luiz/five/fre.php`. Clic derecho > Follow > HTTP Stream para ver el C2 | Loki Bot |
| 2 de 5 | basic | HTTP a `vrondafarih[.]com` (139.59.26.99) y HTTPS a `magiketchinn[.]com` (104 veces) y `magizanqomo[.]com`, ambos en 2.56.177.122 | IcedID (Bokbot) |
| 3 de 5 | basic+dns | DNS de `adaisreal.ddns[.]net` que resuelve a 87.121.221.212 y SYN a ese host al puerto TCP 7888 (paquetes 1478–1480) | Ave Maria / Warzone RAT |
| 4 de 5 | `ftp.request.command or (ftp-data and tcp.seq eq 1)` | FTP a 190.107.177.239 (`valvulasthermovalve[.]cl`). USER y PASS en claro y un STOR que sube `PW_user1-DESKTOP-USER1PC_…html` con credenciales robadas | Infostealer por FTP |
| 5 de 5 | `smtp or dns`, luego `smtp.req.command` y `smtp.data.fragment` | Muchos DNS de servidores de correo, la mayoría con STARTTLS. `smtp.data.fragment` da 7 correos sin cifrar | Spambot (spam tipo Emotet con adjunto OneNote) |

En el pcap 4 se guarda también el filtro de FTP como botón.

> **Capturas pendientes (T2-02 a T2-06):** una por pcap.
