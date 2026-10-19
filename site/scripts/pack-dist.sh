#!/usr/bin/env bash
# Gera ZIP com todo o conteúdo de dist/ (index.html + assets/).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f dist/index.html || ! -d dist/assets ]]; then
  echo "❌ dist/index.html ou dist/assets não existe. Rode antes: npm run web:build"
  exit 1
fi

OUT="$ROOT/deploy-site-dist.zip"
rm -f "$OUT"
(cd dist && zip -rq "$OUT" .)

echo "✅ Pacote: $OUT"
echo "   Tamanho: $(du -h "$OUT" | cut -f1)"
echo ""
echo "No Hostinger (Gerenciador de arquivos ou FTP):"
echo "  1. Abra a pasta public_html/site/dist/ do domínio appcheckin.com.br"
echo "  2. Envie e extraia deploy-site-dist.zip DENTRO de site/dist/"
echo "  3. Confirme: site/dist/index.html e site/dist/assets/index-*.js existem no servidor"
echo "  4. O .htaccess da raiz do projeto (AppCheckin/.htaccess) fica em public_html/"
