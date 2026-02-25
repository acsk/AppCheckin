#!/bin/bash
# ==============================================
# Fake Mercado Pago API Server
# ==============================================
# Inicia o servidor fake na porta 8085
#
# Uso:
#   ./tools/fake-mp-api/start.sh
#   ./tools/fake-mp-api/start.sh 8085   # porta customizada
#
# Parar:
#   Ctrl+C
# ==============================================

PORT=${1:-8085}
DIR="$(cd "$(dirname "$0")" && pwd)"

echo "=========================================="
echo "  🧪 Fake Mercado Pago API"
echo "=========================================="
echo "  Porta:    ${PORT}"
echo "  URL:      http://localhost:${PORT}"
echo "  Health:   http://localhost:${PORT}/fake/health"
echo "  Storage:  http://localhost:${PORT}/fake/storage"
echo "=========================================="
echo ""
echo "  Configure no .env da API:"
echo "  MP_FAKE_API_URL=http://localhost:${PORT}"
echo ""
echo "  Pressione Ctrl+C para parar"
echo "=========================================="
echo ""

php -S "localhost:${PORT}" "${DIR}/server.php"
