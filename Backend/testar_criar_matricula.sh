#!/bin/bash

# Script para criar uma nova matrícula via POST e ver os pagamentos

ENDPOINT="http://localhost:8084/admin/matriculas"
TOKEN="seu_token_jwt_aqui"

# Dados para a nova matrícula
USUARIO_ID=1
PLANO_ID=1
DATA_INICIO="2026-01-15"

echo "🚀 Criando nova matrícula..."
echo "Endpoint: $ENDPOINT"
echo "Usuário ID: $USUARIO_ID"
echo "Plano ID: $PLANO_ID"
echo ""

# Fazer o POST
RESPONSE=$(curl -s -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{
    \"usuario_id\": $USUARIO_ID,
    \"plano_id\": $PLANO_ID,
    \"data_inicio\": \"$DATA_INICIO\",
    \"valor\": 110.00
  }")

echo "📋 Resposta:"
echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE"

# Extrair o ID da matrícula criada
MATRICULA_ID=$(echo "$RESPONSE" | jq '.matricula.id' 2>/dev/null)

if [ ! -z "$MATRICULA_ID" ] && [ "$MATRICULA_ID" != "null" ]; then
  echo ""
  echo "✅ Matrícula #$MATRICULA_ID criada com sucesso!"
  echo ""
  echo "💰 Buscando pagamentos..."
  
  PAGAMENTOS=$(curl -s -X GET "http://localhost:8084/admin/matriculas/$MATRICULA_ID/pagamentos" \
    -H "Authorization: Bearer $TOKEN")
  
  echo "📊 Pagamentos encontrados:"
  echo "$PAGAMENTOS" | jq '.' 2>/dev/null || echo "$PAGAMENTOS"
else
  echo ""
  echo "❌ Erro ao criar matrícula!"
  echo "Resposta completa: $RESPONSE"
fi
