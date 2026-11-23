# 4. Exportar objetos de un pcap

**Objetivo:** sacar del tráfico los ficheros transferidos (malware, páginas, correos) y verificarlos por tipo y hash, no por su extensión.

**ZIP:** `Wireshark-tutorial-extracting-objects-5-pcaps.zip` · **en la VM de Ubuntu y sin abrir los ficheros.**

El menú es siempre **File > Export Objects >** HTTP / SMB / IMF / FTP-DATA. Después, en la terminal, se verifica cada fichero:

```bash
file <fichero>
sha256sum <fichero>
```

## Pcaps

| Pcap | Protocolo y método | Qué sale | Verificación |
|---|---|---|---|
| 1 de 5 | Filtro `http.request`, Export Objects > HTTP | `Invoice&MSO-Request.doc` y `knr.exe` de `smart-fax[.]com` (107.180.50.162) | .doc = Composite Document File V2, SHA256 `f808229a…a054fb`. .exe = PE32 executable, SHA256 `749e1616…2db18` |
| 2 de 5 | Export Objects > HTTP, primera fila | Página de phishing de PayPal en `www.paypalaccountsloginn.myddns[.]com`; luego un POST a `/auth/verifychallenge` con las credenciales | Abrirla solo en un entorno aislado: puede llamar a dominios maliciosos |
| 3 de 5 | Export Objects > SMB | Dos .exe copiados por SMB a `\\10.6.26[.]6\C$\WINDOWS\` (712 kB y 115 kB): Trickbot moviéndose al controlador de dominio | Columna Content Type a `[100.0%]` = fichero completo. SHA256 `59896ae5…010040` y `cf99990b…f82560` |
| 4 de 5 | Filtro `smtp.data.fragment` (50 asuntos), Export Objects > IMF | 50 correos .eml de spam de sextorsión | Se abren con un editor de texto o Thunderbird |
| 5 de 5 | Filtro `ftp.request.command or (ftp-data and tcp.seq eq 1)`, Export Objects > FTP-DATA | USER/PASS en claro, RETR de q.exe, w.exe, e.exe, r.exe y t.exe, y 5 STOR que suben logs HTML con credenciales robadas | FTP-DATA exporta los HTML pero no los .exe (ver abajo) |

## Exportar los .exe del pcap 5 a mano

1. Filtrar `ftp-data.command contains ".exe" and tcp.seq eq 1`.
2. Follow > TCP Stream.
3. *Show data as* > **Raw**.
4. *Save as…* `q.exe`.

El flujo empieza por `MZ` y "This program cannot be run in DOS mode": es la firma de un ejecutable de Windows. SHA256 de q.exe: `ca34b092…d2a3bd`.

> **Capturas pendientes (T4-01 a T4-05):** ventana de Export Objects de cada pcap + terminal con `file`/`sha256sum`. Opcional: un hash buscado en VirusTotal.
