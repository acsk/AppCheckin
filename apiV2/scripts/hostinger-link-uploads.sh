#!/usr/bin/env bash
# Hostinger: liga apiV2/public/uploads → api/public/uploads (fotos compartilhadas com a Slim).
#
# Uso (SSH):
#   cd ~/domains/appcheckin.com.br/public_html/apiV2
#   bash scripts/hostinger-link-uploads.sh
#
# Requer: mesma conta Hostinger; pastas api/ e apiV2/ sob public_html.

set -euo pipefail

ROOT="${HOSTINGER_ROOT:-$HOME/domains/appcheckin.com.br/public_html}"
APIV2_PUBLIC="${ROOT}/apiV2/public"
SLIM_UPLOADS="${ROOT}/api/public/uploads"

if [[ ! -d "$SLIM_UPLOADS/fotos" ]]; then
  echo "❌ Pasta Slim não encontrada: ${SLIM_UPLOADS}/fotos"
  echo "   Ajuste HOSTINGER_ROOT se o caminho for diferente."
  exit 1
fi

mkdir -p "$APIV2_PUBLIC"

TARGET="${APIV2_PUBLIC}/uploads"

if [[ -L "$TARGET" ]]; then
  echo "ℹ️  Symlink já existe: $(readlink "$TARGET")"
  ln -sfn "$SLIM_UPLOADS" "$TARGET"
  echo "✅ Symlink atualizado → ${SLIM_UPLOADS}"
elif [[ -d "$TARGET" ]]; then
  if [[ -z "$(ls -A "$TARGET" 2>/dev/null || true)" ]] || [[ "$(ls -A "$TARGET" | wc -l)" -le 1 ]]; then
    rmdir "$TARGET/fotos" 2>/dev/null || true
    rm -rf "$TARGET"
    ln -sfn "$SLIM_UPLOADS" "$TARGET"
    echo "✅ Pasta vazia removida; symlink criado → ${SLIM_UPLOADS}"
  else
    echo "⚠️  ${TARGET} existe e não está vazio. Renomeie ou mova antes de linkar."
    echo "   Ex.: mv uploads uploads.bak && bash $0"
    exit 1
  fi
else
  ln -sfn "$SLIM_UPLOADS" "$TARGET"
  echo "✅ Symlink criado: ${TARGET} → ${SLIM_UPLOADS}"
fi

chmod -R 755 "$SLIM_UPLOADS" 2>/dev/null || true

echo ""
echo "Arquivos em uploads/fotos:"
ls -la "$TARGET/fotos" | head -8

echo ""
echo "Próximo passo (na pasta apiV2):"
echo "  /opt/alt/php84/usr/bin/php artisan uploads:verify-fotos"
