# 3. Identificar hosts y usuarios

**Objetivo:** ante un equipo comprometido, sacar su IP, MAC, nombre de host, usuario y sistema operativo solo a partir del tráfico.

**ZIP:** `Wireshark-tutorial-identifying-hosts-and-users-5-pcaps.zip`

## Pasos

1. **DHCP (pcap 1):** filtrar `dhcp`. Abrir el *DHCP Request* y mirar Ethernet II (MAC origen), *Option 50 Requested IP Address* y *Option 12 Host Name*.
2. **NBNS (pcaps 1 y 2):** filtrar `nbns`. El nombre NetBIOS aparece en la columna Info.
3. **Kerberos (pcap 3):** filtrar `kerberos.CNameString`. En un paquete AS-REQ desplegar hasta *CNameString*, clic derecho > *Apply as Column*. Los nombres que acaban en `$` son cuentas de equipo; los demás, cuentas de usuario.
4. **LDAP (pcap 3):** filtrar `ldap contains "CN=Users"` para ver el nombre completo del usuario.
5. **Nombre por defecto de Windows (pcap 3):** filtrar `ip contains "DESKTOP-"` (distingue mayúsculas). Otra opción: Edit > Find Packet > *Packet details* + *String* > `desktop-`.
6. **User-Agent (pcaps 3, 4 y 5):** filtrar basic o `http.request`. Clic derecho > Follow > TCP Stream y leer la línea `User-Agent`.

## Qué se debería obtener

| Pcap | IP | MAC | Host | Usuario | Dispositivo / SO |
|---|---|---|---|---|---|
| 1 | 172.16.1.38 | f8:ff:c2:04:a5:7b | jeremiahs-MBP (DHCP) / JEREMIAHS-MBP (NBNS) | — | MacBook Pro (MAC de Apple) |
| 2 | 172.16.1.101 | 78:c2:3b:b8:93:e8 | DESKTOP-HVKYP4S (grupo WORKGROUP) | — | Windows |
| 3 | 172.16.1.141 | 00:51:8a:93:d2:f6 | DESKTOP-HSJD5R8 | rene.mccollum (Kerberos) = Rene McCollum (LDAP) | Windows 10/11, Edge 110.0.1587.69. Dominio pcapworkshop.net, DC 172.16.1.16 |
| 4 | 172.16.1.62 | ee:eb:5a:4b:32:bb | — | — | Google Pixel 4a (5G), Android 13, Chrome 113 |
| 5 | 172.16.1.35 | 36:de:af:61:93:31 | — | — | iPhone con iOS 16.4.1, Safari |

> **Capturas pendientes (T3-01 a T3-05):** una por pcap mostrando el dato clave.

La MAC y el User-Agent se pueden falsificar o anonimizar. Una identificación fiable cruza varias fuentes (DHCP + NBNS + Kerberos).
