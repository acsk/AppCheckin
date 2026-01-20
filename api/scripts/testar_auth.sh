#!/bin/bash

# ============================================================
# Script para testar endpoints de autenticação
# ============================================================

API_URL="http://localhost:8080"
EMAIL_TESTE="teste@example.com"
SENHA_TESTE="senha123"

echo "🧪 TESTES DE AUTENTICAÇÃO"
echo "================================"
echo ""

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Teste 1: Health Check
echo "📌 TESTE 1: Health Check"
echo "URL: GET $API_URL/health"
RESPONSE=$(curl -s -w "\n%{http_code}" -X GET "$API_URL/health")
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" -eq 200 ]; then
    echo -e "${GREEN}✅ PASSOU (200)${NC}"
    echo "Resposta: $BODY"
else
    echo -e "${RED}❌ FALHOU ($HTTP_CODE)${NC}"
    echo "Resposta: $BODY"
fi
echo ""

# Teste 2: Registrar novo usuário
echo "📌 TESTE 2: Registrar Novo Usuário"
echo "URL: POST $API_URL/auth/register"
echo "Body: { nome, email, senha }"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_URL/auth/register" \
  -H "Content-Type: application/json" \
  -d "{
    \"nome\": \"Usuário Teste\",
    \"email\": \"$EMAIL_TESTE\",
    \"senha\": \"$SENHA_TESTE\"
  }")
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" -eq 201 ] || [ "$HTTP_CODE" -eq 422 ]; then
    if [ "$HTTP_CODE" -eq 201 ]; then
        echo -e "${GREEN}✅ PASSOU (201)${NC}"
        TOKEN=$(echo "$BODY" | grep -o '"token":"[^"]*' | cut -d'"' -f4)
        echo "Token gerado: ${TOKEN:0:50}..."
    else
        echo -e "${YELLOW}⚠️ USUÁRIO JÁ EXISTE (422)${NC}"
    fi
else
    echo -e "${RED}❌ FALHOU ($HTTP_CODE)${NC}"
fi
echo "Resposta: $BODY"
echo ""

# Teste 3: Login
echo "📌 TESTE 3: Fazer Login"
echo "URL: POST $API_URL/auth/login"
echo "Body: { email: \"$EMAIL_TESTE\", senha: \"$SENHA_TESTE\" }"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d "{
    \"email\": \"$EMAIL_TESTE\",
    \"senha\": \"$SENHA_TESTE\"
  }")
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" -eq 200 ]; then
    echo -e "${GREEN}✅ PASSOU (200)${NC}"
    TOKEN=$(echo "$BODY" | grep -o '"token":"[^"]*' | cut -d'"' -f4)
    echo "Token: ${TOKEN:0:50}..."
    echo "Resposta completa:"
    echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"
else
    echo -e "${RED}❌ FALHOU ($HTTP_CODE)${NC}"
    echo "Resposta:"
    echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"
fi
echo ""

# Teste 4: Validar credenciais inválidas
echo "📌 TESTE 4: Login com Credenciais Inválidas"
echo "URL: POST $API_URL/auth/login"
echo "Body: { email: \"$EMAIL_TESTE\", senha: \"senhaerrada\" }"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d "{
    \"email\": \"$EMAIL_TESTE\",
    \"senha\": \"senhaerrada\"
  }")
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" -eq 401 ]; then
    echo -e "${GREEN}✅ PASSOU (401 - Correto!)${NC}"
    echo "Resposta: $BODY"
else
    echo -e "${RED}❌ FALHOU (esperava 401, recebeu $HTTP_CODE)${NC}"
    echo "Resposta: $BODY"
fi
echo ""

echo "🏁 TESTES COMPLETOS"
echo "================================"
