# 0. Preparación

## 0.1 Entorno

VM con Ubuntu Desktop en VMware (4 GB de RAM y 2 CPU sobran). Los pcaps contienen malware real para Windows (Loki Bot, IcedID, Trickbot, Dridex…), que en Ubuntu no se puede ejecutar. Aun así, nunca se abren los `.exe`/`.dll` que se exportan.

## 0.2 Instalar Wireshark

Con el adaptador NAT conectado, en una terminal (Ctrl+Alt+T):

```bash
sudo apt update && sudo apt install -y wireshark git unzip
```

Si pregunta si los usuarios sin privilegios pueden capturar paquetes, da igual la respuesta: no se va a capturar en directo. Wireshark se abre desde el menú de aplicaciones, nunca con `sudo`.

## 0.3 Descargar los pcaps

```bash
mkdir ~/wireshark-lab && cd ~/wireshark-lab
git clone https://github.com/PaloAltoNetworks/Unit42-Wireshark-tutorials            # tutoriales 1–4
git clone https://github.com/pan-unit42/wireshark-tutorial-decrypting-HTTPS-traffic  # tutorial 5
```

Tutorial 6 (Trickbot): en Firefox, entrar en malware-traffic-analysis.net, página del 25/09/2019, y guardar los dos ZIP en `~/wireshark-lab`.

## 0.4 Extraer

Todos los ZIP tienen la contraseña `infected`. Se extraen a una carpeta `pcaps`:

```bash
find ~/wireshark-lab -name "*.zip" -exec unzip -o -P infected {} -d ~/wireshark-lab/pcaps \;
```

Si algún ZIP da error de contraseña o de método:

```bash
sudo apt install -y p7zip-full
7z x -pinfected fichero.zip -o$HOME/wireshark-lab/pcaps
```

## 0.5 Desconectar la red de la VM

En VMware, **VM > Settings > Network Adapter (NAT)**: desmarcar *Connected* y *Connect at power on*. Lo mismo con **Network Adapter 2 (Host-only)**. En **Options > Shared Folders**, dejarlo en *Disabled*.

## 0.6 Abrir los pcaps

En Wireshark: **File > Open** > `~/wireshark-lab/pcaps`. No hace falta elegir ninguna interfaz de red.

## 0.7 Capturas de pantalla

Desde Windows con Win+Shift+S sobre la ventana de VMware. Se guardan en `img/` con nombres tipo `T1-01-perfil.png` o `T2-03-basic-dns.png`.
