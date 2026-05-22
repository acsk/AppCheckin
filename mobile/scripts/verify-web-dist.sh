#!/bin/bash
# Valida que index.html aponta para um bundle JS que existe em dist/.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HTML="$ROOT/dist/index.html"
STATIC_DIR="expo-static"

if [ ! -f "$HTML" ]; then
  echo "Erro: rode npm run web:build antes."
  exit 1
fi

ENTRY=$(grep -oE '/expo-static/static/js/web/entry-[a-f0-9]+\.js' "$HTML" | head -1)
if [ -z "$ENTRY" ]; then
  echo "Erro: script entry (/expo-static/...) não encontrado em dist/index.html"
  echo "Rode npm run web:build:clean (rename-expo-static pode não ter rodado)."
  exit 1
fi

JS="$ROOT/dist${ENTRY}"
if [ ! -f "$JS" ]; then
  echo "Erro: bundle ausente: $JS"
  exit 1
fi

FIRST=$(head -c 1 "$JS")
if [ "$FIRST" = "<" ]; then
  echo "Erro: $JS parece HTML, não JavaScript."
  exit 1
fi

if [ ! -d "$ROOT/dist/$STATIC_DIR/static/js/web" ]; then
  echo "Erro: pasta dist/$STATIC_DIR/static/js/web ausente."
  exit 1
fi

echo "OK: $ENTRY ($(du -h "$JS" | cut -f1))"
