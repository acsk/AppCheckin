#!/bin/bash
# Pós-processamento do expo export --platform web (assets + fontes usadas no app).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

cp -r assets dist/
bash scripts/sync-icon-fonts.sh
bash scripts/inject-fonts.sh
cp public/fonts.css dist/fonts.css

echo "Pós-export web concluído."
