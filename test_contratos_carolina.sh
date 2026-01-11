#!/bin/bash

# Teste do endpoint mobile/contratos com carolina

echo "🔍 Testando /mobile/contratos com carolina.ferreira@tenant4.com"
echo ""

# 1. Login
echo "1️⃣ Fazendo login..."
LOGIN_RESPONSE=$(curl -s -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"carolina.ferreira@tenant4.com","senha":"123456"}')

TOKEN=$(echo "$LOGIN_RESPONSE" | python3 -c "import sys, json; print(json.load(sys.stdin).get('token', 'null'))" 2>/dev/null)

if [ "$TOKEN" = "null" ] || [ -z "$TOKEN" ]; then
    echo "❌ Falha no login!"
    echo "Resposta:"
    echo "$LOGIN_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$LOGIN_RESPONSE"
    exit 1
fi

echo "✅ Login bem-sucedido!"
echo "Token: ${TOKEN:0:40}..."
echo ""

# 2. Chamar endpoint /mobile/contratos
echo "2️⃣ Chamando GET /mobile/contratos..."
RESPONSE=$(curl -s http://localhost:8080/mobile/contratos \
  -H "Authorization: Bearer $TOKEN")

echo "Resposta:"
echo "$RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$RESPONSE"
