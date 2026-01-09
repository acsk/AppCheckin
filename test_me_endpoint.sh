#!/bin/bash

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🧪 Teste do Endpoint /me${NC}\n"

# 1. Login
echo -e "${YELLOW}1️⃣ Fazendo login...${NC}"
LOGIN_RESPONSE=$(curl -s -X POST 'http://localhost:8080/auth/login' \
  -H 'Content-Type: application/json' \
  -d '{"email":"teste@exemplo.com","senha":"password123"}')

TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.token')
USER_ID=$(echo "$LOGIN_RESPONSE" | jq -r '.user.id')
USER_NOME=$(echo "$LOGIN_RESPONSE" | jq -r '.user.nome')

if [ -z "$TOKEN" ] || [ "$TOKEN" == "null" ]; then
  echo -e "${RED}❌ Erro ao fazer login${NC}"
  echo "$LOGIN_RESPONSE" | jq '.'
  exit 1
fi

echo -e "${GREEN}✅ Login realizado${NC}"
echo -e "   Usuário: $USER_NOME (ID: $USER_ID)"
echo -e "   Token: ${TOKEN:0:30}...\n"

# 2. Chamar /me
echo -e "${YELLOW}2️⃣ Chamando GET /me...${NC}"
ME_RESPONSE=$(curl -s -X GET 'http://localhost:8080/me' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json')

ME_ID=$(echo "$ME_RESPONSE" | jq -r '.id')
ME_CPF=$(echo "$ME_RESPONSE" | jq -r '.cpf')
ME_CEP=$(echo "$ME_RESPONSE" | jq -r '.cep')
ME_TELEFONE=$(echo "$ME_RESPONSE" | jq -r '.telefone')
ME_NOME=$(echo "$ME_RESPONSE" | jq -r '.nome')

if [ -z "$ME_ID" ] || [ "$ME_ID" == "null" ]; then
  echo -e "${RED}❌ Erro ao chamar /me${NC}"
  echo "$ME_RESPONSE" | jq '.'
  exit 1
fi

echo -e "${GREEN}✅ /me retornou dados${NC}"
echo -e "   ID: $ME_ID"
echo -e "   Nome: $ME_NOME"
echo -e "   CPF: $ME_CPF"
echo -e "   CEP: $ME_CEP"
echo -e "   Telefone: $ME_TELEFONE\n"

# 3. Verificar dados completos
echo -e "${YELLOW}3️⃣ Dados Completos retornados:${NC}"
echo "$ME_RESPONSE" | jq '.'

echo -e "\n${GREEN}✅ Teste concluído com sucesso!${NC}"
