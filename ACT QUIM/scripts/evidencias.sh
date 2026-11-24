#!/usr/bin/env bash
# Guarda en evidencias/<fase>.txt la salida de los comandos de cada fase.
# Uso (desde la carpeta de la práctica):
#   sudo ./scripts/evidencias.sh 01-nginx      # Nginx sin AppArmor
#   sudo ./scripts/evidencias.sh 02-complain   # perfil en modo complain
#   sudo ./scripts/evidencias.sh 03-enforce    # perfil en modo enforce

set -u

FASE="${1:-}"
HOST="apparmor.asir.local"
PERFIL="/usr/sbin/nginx"
DIR="$(cd "$(dirname "$0")/.." && pwd)/evidencias"

case "$FASE" in
  01-nginx|02-complain|03-enforce) ;;
  *) echo "Uso: sudo $0 {01-nginx|02-complain|03-enforce}"; exit 1 ;;
esac

if [ "$(id -u)" -ne 0 ]; then
  echo "Ejecuta el script con sudo."; exit 1
fi

mkdir -p "$DIR"
SALIDA="$DIR/$FASE.txt"

# Muestra el comando y su salida
run() {
  echo
  echo "\$ $*"
  bash -c "$*" 2>&1
}

http() {
  run "curl -s -o /dev/null -w 'GET $1 -> %{http_code}\n' http://$HOST$1"
}

eventos() {
  # $1 = ALLOWED o DENIED
  run "journalctl -k -b --no-pager | grep 'profile=\"$PERFIL\"' | grep 'apparmor=\"$1\"'"
}

{
  echo "##### Fase: $FASE"
  echo "##### Fecha: $(date '+%F %T')  Equipo: $(hostname)  Kernel: $(uname -r)"

  run "nginx -v"
  run "systemctl is-active nginx"
  run "ps auxZ | grep '[n]ginx'"

  case "$FASE" in
    01-nginx)
      run "nginx -t"
      run "ss -tlnp | grep ':80 '"
      run "cat /etc/nginx/sites-available/$HOST"
      run "curl -si http://$HOST/"
      http "/"
      http "/fuera/"
      run "ls /etc/apparmor.d/ | grep -i nginx || echo 'No hay perfil de nginx'"
      ;;

    02-complain)
      run "aa-status | grep -A3 -i 'complain mode'"
      http "/"
      http "/noexiste"
      echo
      echo "##### Resumen de eventos ALLOWED por tipo"
      run "journalctl -k -b --no-pager | grep 'profile=\"$PERFIL\"' | grep -oE 'operation=\"[^\"]+\"|capname=\"[^\"]+\"' | sort | uniq -c | sort -rn"
      echo
      echo "##### Eventos ALLOWED (modo complain)"
      eventos ALLOWED
      run "cat /etc/apparmor.d/usr.sbin.nginx"
      ;;

    03-enforce)
      run "aa-status | grep -A3 -i 'enforce mode' | head -20"
      run "aa-status | grep -i nginx"
      run "apparmor_parser -QT /etc/apparmor.d/usr.sbin.nginx && echo 'Perfil compila OK'"
      run "nginx -t"
      run "systemctl reload nginx && echo 'reload OK'"
      run "nginx -s reopen && echo 'reopen OK'"
      sleep 1
      run "curl -si http://$HOST/"
      http "/"
      http "/noexiste"
      http "/fuera/"
      run "tail -n 3 /var/log/nginx/apparmor.access.log"
      run "tail -n 2 /var/log/nginx/apparmor.error.log"
      echo
      echo "##### Eventos DENIED (modo enforce)"
      eventos DENIED
      run "cat /etc/apparmor.d/usr.sbin.nginx"
      ;;
  esac
} | tee "$SALIDA"

echo
echo "Evidencias guardadas en: $SALIDA"
