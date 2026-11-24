# Evidencias

Salida real de la máquina virtual en cada fase, generada con [scripts/evidencias.sh](../scripts/evidencias.sh):

| Fichero | Fase | Qué contiene |
|---|---|---|
| `01-nginx.txt` | Nginx sin AppArmor | Versión, procesos `unconfined`, puerto 80, `/` y `/fuera/` → 200 |
| `02-complain.txt` | Perfil en complain | `aa-status`, procesos `(complain)`, resumen y lista de eventos `ALLOWED`, perfil |
| `03-enforce.txt` | Perfil en enforce | `aa-status`, procesos `(enforce)`, reload/reopen, `/` → 200, `/noexiste` → 404, `/fuera/` → 403, eventos `DENIED`, perfil final |

```bash
cd "ACT QUIM"
chmod +x scripts/evidencias.sh
sudo ./scripts/evidencias.sh 01-nginx      # después del paso 1
sudo ./scripts/evidencias.sh 02-complain   # después del paso 2/3
sudo ./scripts/evidencias.sh 03-enforce    # después del paso 4/5
```
