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

ENTRY=$(echo "$HTML" | grep -oE '/_expo/static/js/web/entry-[a-f0-9]+\.js' | head -1)
if [ -z "$ENTRY" ]; then
  echo "Erro: entry JS (/_expo/...) não encontrado no HTML."
  exit 1
fi

URL="$BASE$ENTRY"
echo "Testando: $URL"

CT=$(curl -sI "$URL" | grep -i '^content-type:' | head -1)
echo "$CT"

BODY=$(curl -fsSL "$URL" | head -c 40)
if echo "$BODY" | grep -q '^<!'; then
  echo ""
  echo "FALHA: servidor retornou HTML. Suba a pasta dist/_expo/ para o mesmo nível do index.html."
  exit 1
fi

echo ""
echo "OK: bundle JS servido corretamente."
exit 0
