# Wireshark – Amenazas y ataques

**Módulo 0378 – Seguridad y Alta Disponibilidad · Actividad M0378T1A1 · CFGS ASIR**

Alumno: Pau Bonet

Guía paso a paso de los 6 tutoriales de Wireshark de Unit 42 (Palo Alto Networks). Para cada tutorial se indica qué hacer, en qué orden y qué se debería ver. Los resultados de los tutoriales 1–5 están comprobados con tshark 4.2.2 sobre los mismos pcaps.

| Tutorial | Página | Tiempo aprox. |
|---|---|---|
| 0. Preparación del entorno | [Preparación](guia/00-preparacion.md) | 20 min |
| 1. Personalizar las columnas | [Columnas](guia/01-columnas.md) | 30 min |
| 2. Filtros de visualización | [Filtros](guia/02-filtros.md) | 40 min |
| 3. Identificar hosts y usuarios | [Hosts y usuarios](guia/03-hosts-usuarios.md) | 40 min |
| 4. Exportar objetos de un pcap | [Exportar objetos](guia/04-exportar-objetos.md) | 40 min |
| 5. Descifrar tráfico HTTPS | [Descifrar HTTPS](guia/05-descifrar-https.md) | 30 min |
| 6. Examinar infecciones de Trickbot | [Trickbot](guia/06-trickbot.md) | 40 min |

## Entorno

| Elemento | Valor |
|---|---|
| Sistema | Ubuntu Desktop (máquina virtual en VMware Workstation, 4 GB de RAM, 2 CPU) |
| Red | Adaptador 1 NAT + Adaptador 2 Solo anfitrión, **desconectados** durante el análisis |
| Herramienta | Wireshark (paquete `wireshark` de Ubuntu), perfil propio `Customized` |
| Material | pcaps de Unit 42 y de malware-traffic-analysis.net (ZIP con contraseña `infected`) |
| Capturas de pantalla | Desde el anfitrión Windows (Win+Shift+S) |

No se captura tráfico en directo: todos los tutoriales trabajan sobre ficheros `.pcap` ya grabados, que se abren con **File > Open**.

## Estructura

```
├── README.md      ← esta página
├── SUMMARY.md     ← índice para GitBook
├── guia/          ← un fichero por tutorial
└── img/           ← capturas de pantalla
```

## Fuentes

- [Unit 42 – Changing Your Column Display](https://unit42.paloaltonetworks.com/unit42-customizing-wireshark-changing-column-display/)
- [Unit 42 – Display Filter Expressions](https://unit42.paloaltonetworks.com/using-wireshark-display-filter-expressions/)
- [Unit 42 – Identifying Hosts and Users](https://unit42.paloaltonetworks.com/using-wireshark-identifying-hosts-and-users/)
- [Unit 42 – Exporting Objects from a Pcap](https://unit42.paloaltonetworks.com/using-wireshark-exporting-objects-from-a-pcap/)
- [Unit 42 – Decrypting HTTPS Traffic](https://unit42.paloaltonetworks.com/wireshark-tutorial-decrypting-https-traffic/)
- [Unit 42 – Examining Trickbot Infections](https://unit42.paloaltonetworks.com/wireshark-tutorial-examining-trickbot-infections/)
- [Repositorio de pcaps (GitHub)](https://github.com/PaloAltoNetworks/Unit42-Wireshark-tutorials)
- [Repositorio HTTPS (GitHub)](https://github.com/pan-unit42/wireshark-tutorial-decrypting-HTTPS-traffic)
- [Pcaps de Trickbot – malware-traffic-analysis.net](https://www.malware-traffic-analysis.net/2019/09/25/index.html)
