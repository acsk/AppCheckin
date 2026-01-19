#!/bin/bash

# ============================================
# TESTE DA API EM PRODUÇÃO
# ============================================

API_URL="https://api.appcheckin.com.br"

echo "🧪 TESTANDO API EM PRODUÇÃO"
echo "============================="
echo "URL: $API_URL"
echo ""

# ============ TESTE 1: Status da API ============
echo "✅ TESTE 1: Verificar Status"
echo "---"
echo "Comando:"
echo "curl -s $API_URL/status | jq ."
echo ""
echo "Resultado esperado: JSON com status da API"
echo ""

# ============ TESTE 2: Health Check ============
echo "✅ TESTE 2: Health Check (Banco de Dados)"
echo "---"
echo "Comando:"
echo "curl -s $API_URL/health | jq ."
echo ""
echo "Resultado esperado: { \"status\": \"ok\", \"database\": \"connected\" }"
echo ""

# ============ TESTE 3: Autenticação ============
echo "✅ TESTE 3: Testar Login"
echo "---"
echo "Comando:"
echo "curl -X POST $API_URL/auth/login \\"
echo "  -H 'Content-Type: application/json' \\"
echo "  -d '{\"email\":\"seu_email@example.com\",\"password\":\"sua_senha\"}' | jq ."
echo ""
echo "Resultado esperado: JWT token"
echo ""

# ============ TESTE 4: Requisição Autenticada ============
echo "✅ TESTE 4: Requisição com Token"
echo "---"
echo "Comando:"
echo "curl -s -H 'Authorization: Bearer SEU_TOKEN_JWT' $API_URL/usuario/perfil | jq ."
echo ""
echo "Resultado esperado: Dados do usuário autenticado"
echo ""

# ============ TESTE 5: Listar Check-ins ============
echo "✅ TESTE 5: Listar Check-ins"
echo "---"
echo "Comando:"
echo "curl -s -H 'Authorization: Bearer SEU_TOKEN_JWT' $API_URL/checkins | jq ."
echo ""
echo "Resultado esperado: Array de check-ins"
echo ""

# ============ TESTE 6: CORS ============
echo "✅ TESTE 6: Verificar CORS"
echo "---"
echo "Comando:"
echo "curl -s -I -H 'Origin: https://appcheckin.com.br' $API_URL/status"
echo ""
echo "Procure por:"
echo "Access-Control-Allow-Origin: https://appcheckin.com.br"
echo ""

echo "============================="
echo ""
