#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Hostinger e outros hosts costumam ter php 7.4 como padrão; a API exige 8.2+.
resolve_php() {
  local candidate version_id
  for candidate in \
    "${PHP_BIN:-}" \
    php8.2 php82 php \
    /opt/alt/php82/usr/bin/php \
    /opt/alt/php83/usr/bin/php \
    /usr/local/bin/php82 \
    /usr/bin/php82; do
    [[ -n "$candidate" ]] || continue
    if ! command -v "$candidate" >/dev/null 2>&1; then
      [[ -x "$candidate" ]] || continue
    fi
    version_id="$("$candidate" -r 'echo PHP_VERSION_ID;' 2>/dev/null || echo 0)"
    if [[ "$version_id" -ge 80200 ]]; then
      echo "$candidate"
      return 0
    fi
  done
  return 1
}

PHP="$(resolve_php || true)"
if [[ -z "$PHP" ]]; then
  echo "❌ PHP 8.2+ não encontrado. Defina PHP_BIN=/caminho/para/php8.2" >&2
  echo "   Hostinger: /opt/alt/php82/usr/bin/php tests/run-migracao-tests.sh prod" >&2
  exit 1
fi

echo "Usando: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"
echo ""

MODE="${1:-local}"

if [[ "$MODE" == "prod" ]]; then
  echo "=== Testes PRODUÇÃO (somente leitura) ==="
  "$PHP" tests/MatriculaMigracaoProdTest.php
  exit $?
fi

export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3307}"
export DB_NAME="${DB_NAME:-appcheckin}"
export DB_USER="${DB_USER:-root}"
export DB_PASS="${DB_PASS:-root}"

echo "=== Testes unitários (crédito proporcional) ==="
"$PHP" tests/MatriculaMigracaoCreditoTest.php

echo ""
echo "=== Testes funcionais (migração + banco local) ==="
"$PHP" tests/MatriculaMigracaoFunctionalTest.php
