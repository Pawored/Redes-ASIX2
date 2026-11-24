# 5. Descifrar tráfico HTTPS

**Objetivo:** comprobar que con el fichero de claves de sesión TLS (key log, el que genera la variable `SSLKEYLOGFILE` en navegadores) el HTTPS se lee como HTTP.

**Ficheros:** `Wireshark-tutorial-on-decrypting-HTTPS-SSL-TLS-traffic.pcap` y `Wireshark-tutorial-KeysLogFile.txt`. Contiene una infección de **Dridex**.

## Pasos

1. **Antes de descifrar:** abrir el pcap y aplicar el botón **basic**. Solo se ven IPs, puertos y nombres SNI; `http.request` da 0 resultados. Los dominios son `config.edge.skype.com`, `settings-win-ppe.data.microsoft.com`, `self.events.data.microsoft.com`, `foodsgoodforliver[.]com` (94.103.84.245) y `105711[.]com` (162.255.119.253).

   > **Captura pendiente (T5-01):** antes de descifrar.

2. **Abrir el key log** en un editor: son líneas `CLIENT_RANDOM <random> <master secret>`.

   > **Captura pendiente (T5-02):** el fichero de claves.

3. **Cargar las claves:** Edit > Preferences > Protocols > **TLS**. En *(Pre)-Master-Secret log filename* pulsar Browse, elegir `Wireshark-tutorial-KeysLogFile.txt` y OK.

   > **Captura pendiente (T5-03):** la ventana de preferencias.

4. **Después de descifrar:** volver a aplicar basic. Aparecen las peticiones HTTP bajo las líneas HTTPS:
   - `GET foodsgoodforliver[.]com/invest_20.dll`: descarga de la DLL de Dridex.
   - `POST 105711[.]com/docs.php` (3 veces): tráfico de mando y control (C2).
   - Tráfico legítimo a Microsoft y Skype.

   > **Captura pendiente (T5-04):** después de descifrar.

5. **Seguir el flujo:** clic derecho en el GET > Follow > HTTP Stream. La respuesta empieza por `MZ`.
6. **Exportar la DLL:** File > Export Objects > HTTP > `invest_20.dll`. `file` debe decir *PE32 executable (DLL)*. SHA256:

   ```
   31cf42b2a7c5c558f44cfc67684cc344c17d4946d3a1e0b2cecb8eb58173cb2f
   ```

TLS protege en tránsito, pero quien tenga las claves de sesión lo lee todo. Por eso la inspección TLS corporativa es posible, y por eso hay que proteger los ficheros de claves.
