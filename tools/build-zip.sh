#!/usr/bin/env bash
# Baut die installierbare ZIP-Datei: dist/sani-materialverwaltung-<version>.zip
set -euo pipefail
cd "$(dirname "$0")/.."
PLUGIN=sani-materialverwaltung
VERSION=$(sed -n "s/^ \* Version: *//p" "$PLUGIN/$PLUGIN.php" | tr -d '[:space:]')
CONST=$(sed -n "s/.*'SANI_MV_VERSION', '\([^']*\)'.*/\1/p" "$PLUGIN/$PLUGIN.php")
if [ "$VERSION" != "$CONST" ]; then
  echo "Version im Kopf ($VERSION) und SANI_MV_VERSION ($CONST) stimmen nicht überein." >&2
  exit 1
fi
mkdir -p dist
OUT="dist/$PLUGIN-$VERSION.zip"
rm -f "$OUT"
zip -qr "$OUT" "$PLUGIN" -x "*/.DS_Store"
echo "$OUT"
