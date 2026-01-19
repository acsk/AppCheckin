#!/bin/bash

# ============================================
# TESTES DE ROTAS ABERTAS
# ============================================

API="https://api.appcheckin.com.br"

echo "🧪 TESTANDO ROTAS ABERTAS - AppCheckin API"
echo "==========================================="
echo ""

# ============ TESTE 1: Ping (PHP rodando) ============
echo "✅ TESTE 1: Ping - Verifica se PHP está rodando"
echo "---"
echo "Comando:"
echo "curl -s $API/ping | jq ."
echo ""
echo "Teste agora:"
curl -s "$API/ping" | jq .
echo ""
echo "✓ Se receber 'pong' com timestamp → PHP está rodando!"
echo ""
echo "---"
echo ""

# ============ TESTE 2: Status ============
echo "✅ TESTE 2: Status - Verifica se API está online"
echo "---"
echo "Comando:"
echo "curl -s $API/status | jq ."
echo ""
echo "Teste agora:"
curl -s "$API/status" | jq .
echo ""
echo "✓ Se receber 'online' → API está funcionando!"
echo ""
echo "---"
echo ""

# ============ TESTE 3: Health Check ============
echo "✅ TESTE 3: Health - Verifica PHP + Banco de Dados"
echo "---"
echo "Comando:"
echo "curl -s $API/health | jq ."
echo ""
echo "Teste agora:"
curl -s "$API/health" | jq .
echo ""
echo "✓ Se receber 'database: connected' → Banco está OK!"
echo "✗ Se receber 'database: disconnected' → Verifique credenciais do .env"
echo ""
echo "---"
echo ""

echo "🎯 RESUMO DOS TESTES"
echo "==========================================="
echo "• /ping   → PHP rodando?"
echo "• /status → API online?"
echo "• /health → Banco de dados conectado?"
echo ""
