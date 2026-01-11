#!/bin/bash

# Script para testar o endpoint de contratos/planos ativos do mobile
# Uso: ./test_mobile_contratos.sh <token> [baseUrl]

TOKEN="${1:-}"
BASE_URL="${2:-http://localhost:8080}"

if [ -z "$TOKEN" ]; then
    echo "❌ Token não fornecido!"
    echo "Uso: $0 <token> [baseUrl]"
    echo ""
    echo "Exemplo:"
    echo "  $0 'eyJ0eXAiOiJKV1QiLCJhbGc...'"
    echo ""
    echo "Para obter um token, primeiro faça login:"
    echo "  curl -X POST $BASE_URL/auth/login \\"
    echo "    -H 'Content-Type: application/json' \\"
    echo "    -d '{\"email\": \"teste@exemplo.com\", \"senha\": \"password123\"}'"
    exit 1
fi

echo "🔍 Testando endpoint /mobile/contratos"
echo "📍 URL: $BASE_URL/mobile/contratos"
echo "🔑 Token: ${TOKEN:0:30}..."
echo ""

# Fazer requisição
RESPONSE=$(curl -s -w "\n%{http_code}" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  "$BASE_URL/mobile/contratos")

# Separar body e status code
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

echo "📊 Status HTTP: $HTTP_CODE"
echo ""
echo "📋 Resposta:"
echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"
echo ""

# Análise da resposta
if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ Requisição bem-sucedida!"
    
    # Verificar se tem contrato ativo
    CONTRATO_ATIVO=$(echo "$BODY" | jq '.data.contrato_ativo' 2>/dev/null)
    
    if [ "$CONTRATO_ATIVO" != "null" ]; then
        echo ""
        echo "📦 Informações do Contrato:"
        echo "  Plano: $(echo "$BODY" | jq -r '.data.contrato_ativo.plano.nome')"
        echo "  Valor: R\$ $(echo "$BODY" | jq -r '.data.contrato_ativo.plano.valor')"
        echo "  Status: $(echo "$BODY" | jq -r '.data.contrato_ativo.status.nome')"
        echo "  Data Início: $(echo "$BODY" | jq -r '.data.contrato_ativo.vigencia.data_inicio')"
        echo "  Data Fim: $(echo "$BODY" | jq -r '.data.contrato_ativo.vigencia.data_fim')"
        echo "  Dias Restantes: $(echo "$BODY" | jq -r '.data.contrato_ativo.vigencia.dias_restantes')"
        echo "  Percentual de Uso: $(echo "$BODY" | jq -r '.data.contrato_ativo.vigencia.percentual_uso')%"
        echo ""
        echo "💳 Pagamentos:"
        echo "$BODY" | jq -r '.data.contrato_ativo.pagamentos.lista[] | "  - \(.data_vencimento): R\$ \(.valor) - \(.status)"' 2>/dev/null
    else
        echo ""
        echo "⚠️  Nenhum contrato ativo no momento"
    fi
else
    echo "❌ Erro na requisição (HTTP $HTTP_CODE)"
    
    if [ "$HTTP_CODE" = "400" ]; then
        echo "  Possível causa: Nenhum tenant selecionado"
    elif [ "$HTTP_CODE" = "401" ]; then
        echo "  Possível causa: Token inválido ou expirado"
    fi
fi
