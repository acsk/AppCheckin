#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

MODE="${1:-local}"

if [[ "$MODE" == "prod" ]]; then
  echo "=== Testes PRODUÇÃO (somente leitura) ==="
  php tests/MatriculaMigracaoProdTest.php
  exit $?
fi

export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3307}"
export DB_NAME="${DB_NAME:-appcheckin}"
export DB_USER="${DB_USER:-root}"
export DB_PASS="${DB_PASS:-root}"

echo "=== Testes unitários (crédito proporcional) ==="
php tests/MatriculaMigracaoCreditoTest.php

echo ""
echo "=== Testes funcionais (migração + banco local) ==="
php tests/MatriculaMigracaoFunctionalTest.php
