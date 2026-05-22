#!/bin/bash
# Verifica se o deploy em produção serve JS real (não HTML).
# Uso: ./scripts/check-remote-deploy.sh https://mobile.appcheckin.com.br
set -euo pipefail

BASE="${1:-https://mobile.appcheckin.com.br}"
BASE="${BASE%/}"

HTML=$(curl -fsSL "$BASE/" 2>/dev/null || true)
if [ -z "$HTML" ]; then
  echo "Falha ao baixar $BASE/"
  exit 1
fi

ENTRY=$(echo "$HTML" | grep -oE '/expo-static/static/js/web/entry-[a-f0-9]+\.js' | head -1)
if [ -z "$ENTRY" ]; then
  ENTRY=$(echo "$HTML" | grep -oE '/_expo/static/js/web/entry-[a-f0-9]+\.js' | head -1)
  if [ -n "$ENTRY" ]; then
    echo "AVISO: index ainda referencia /_expo/ — faça novo build e deploy."
  else
    echo "Erro: entry JS não encontrado no HTML."
    exit 1
  fi
fi

URL="$BASE$ENTRY"
echo "Testando: $URL"

HEADERS=$(curl -sI "$URL")
CT=$(echo "$HEADERS" | grep -i '^content-type:' | head -1)
CL=$(echo "$HEADERS" | grep -i '^content-length:' | head -1)

echo "$CT"
echo "$CL"

BODY=$(curl -fsSL "$URL" | head -c 40)
if echo "$BODY" | grep -q '^<!'; then
  echo ""
  echo "FALHA: servidor retornou HTML no lugar do bundle JS."
  echo "Suba a pasta dist/expo-static/ inteira para o servidor (Hostinger ignora pastas _expo)."
  exit 1
fi

if echo "$BODY" | grep -q 'var __BUNDLE_START_TIME__\|function\|export '; then
  echo ""
  echo "OK: bundle JS servido corretamente."
  exit 0
fi

echo ""
echo "AVISO: resposta inesperada: $BODY"
exit 1
