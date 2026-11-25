#!/bin/bash
set -euo pipefail
trap 'echo "ERROR: No se completo la instalacion. Revisa el mensaje anterior." >&2' ERR

if [[ "$(uname -s)" != Darwin ]]; then
    echo "Este instalador es exclusivo para macOS." >&2
    exit 1
fi
if [[ "$EUID" -eq 0 ]]; then
    echo "Ejecuta este archivo sin sudo; se pedira la contrasena solo al copiar el certificado." >&2
    exit 1
fi

QZ_APP="/Applications/QZ Tray.app"
if [[ ! -d "$QZ_APP" ]]; then
    QZ_APP="$HOME/Applications/QZ Tray.app"
fi
QZ_BIN="$QZ_APP/Contents/MacOS/QZ Tray"
QZ_RESOURCES="$QZ_APP/Contents/Resources"
if [[ ! -x "$QZ_BIN" || ! -d "$QZ_RESOURCES" ]]; then
    echo "Instala QZ Tray en Aplicaciones y vuelve a ejecutar este archivo." >&2
    exit 1
fi

echo "Cierra QZ Tray desde su icono en la barra de menus (Exit/Salir)."
read -r -p "Cuando este cerrado, presiona Enter para continuar... "

umask 077
CERT_DIR="$HOME/Library/Application Support/qz/certs"
mkdir -p "$CERT_DIR"
CERT_FILE="$(mktemp "$CERT_DIR/buhopos.XXXXXX")"
trap 'rm -f "$CERT_FILE"' EXIT
cat > "$CERT_FILE" <<'BUHOPOS_CERTIFICATE'
{{CERTIFICATE}}
BUHOPOS_CERTIFICATE
/usr/bin/openssl x509 -in "$CERT_FILE" -noout

echo "Instalando el certificado de BuhoPos..."
if [[ -w "$QZ_RESOURCES" && ( ! -e "$QZ_RESOURCES/override.crt" || -w "$QZ_RESOURCES/override.crt" ) ]]; then
    /usr/bin/install -m 644 "$CERT_FILE" "$QZ_RESOURCES/override.crt"
else
    sudo /usr/bin/install -m 644 "$CERT_FILE" "$QZ_RESOURCES/override.crt"
fi
"$QZ_BIN" --allow "$CERT_FILE"
/usr/bin/open "$QZ_APP"
echo "Listo. Vuelve a BuhoPos y pulsa Conectar."
