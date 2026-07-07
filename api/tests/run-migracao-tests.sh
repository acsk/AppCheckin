#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3307}"
export DB_NAME="${DB_NAME:-appcheckin}"
export DB_USER="${DB_USER:-root}"
export DB_PASS="${DB_PASS:-root}"

echo "=== Testes unitários (crédito proporcional) ==="
php tests/MatriculaMigracaoCreditoTest.php

echo ""
echo "=== Testes funcionais (migração + banco) ==="
php tests/MatriculaMigracaoFunctionalTest.php
