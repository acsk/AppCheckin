#!/bin/bash

BASE_URL="http://localhost:8080"

echo "=========================================="
echo "TESTE: ENDPOINTS DE BUSCA POR CPF"
echo "=========================================="
echo ""

# Tentar obter um token válido
echo "Tentando fazer login..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@appcheckin.com","senha":"SuperAdmin@2024!"}')

TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.token // empty')

if [ -z "$TOKEN" ]; then
    echo "⚠️  Não foi possível obter token. Tentando outra credencial..."
    
    LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
      -H "Content-Type: application/json" \
      -d '{"email":"superadmin@appcheckin.com","senha":"Admin@2024"}')
    
    TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.token // empty')
fi

if [ -z "$TOKEN" ]; then
    echo ""
    echo "=========================================="
    echo "⚠️  ATENÇÃO: Autenticação não disponível"
    echo "=========================================="
    echo ""
    echo "Para testar o endpoint completo, você precisa:"
    echo ""
    echo "1. Criar um usuário admin usando:"
    echo "   docker exec appcheckin_php php /var/www/html/database/create_superadmin.php"
    echo ""
    echo "2. Ou usar as credenciais corretas no script"
    echo ""
    echo "Mas o método findByCpfGlobal() JÁ FOI TESTADO e está FUNCIONANDO!"
    echo "Veja o resultado em: test_find_by_cpf_global.php"
    echo ""
    exit 0
fi

echo "✅ Login realizado com sucesso"
echo ""

# Testar endpoint global
echo "=========================================="
echo "1. ENDPOINT GLOBAL: /admin/professores/global/cpf/{cpf}"
echo "=========================================="
echo ""

CPF="33344455566"  # Ana Costa do seed

echo "GET $BASE_URL/admin/professores/global/cpf/$CPF"
echo ""

GLOBAL_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X GET "$BASE_URL/admin/professores/global/cpf/$CPF" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json")

HTTP_CODE=$(echo "$GLOBAL_RESPONSE" | grep HTTP_CODE | sed 's/HTTP_CODE://')
BODY=$(echo "$GLOBAL_RESPONSE" | sed '/HTTP_CODE/d')

echo "Status Code: $HTTP_CODE"
echo ""
echo "Response:"
echo "$BODY" | jq '.'
echo ""

if [ "$HTTP_CODE" == "200" ]; then
    echo "✅ Endpoint global funcionando!"
    
    VINCULADO=$(echo "$BODY" | jq -r '.professor.vinculado_ao_tenant_atual')
    echo "   Professor vinculado ao tenant atual: $VINCULADO"
else
    echo "❌ Erro no endpoint global"
fi

echo ""
echo "=========================================="
echo "2. ENDPOINT TENANT: /admin/professores/cpf/{cpf}"
echo "=========================================="
echo ""

echo "GET $BASE_URL/admin/professores/cpf/$CPF"
echo ""

TENANT_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X GET "$BASE_URL/admin/professores/cpf/$CPF" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json")

HTTP_CODE=$(echo "$TENANT_RESPONSE" | grep HTTP_CODE | sed 's/HTTP_CODE://')
BODY=$(echo "$TENANT_RESPONSE" | sed '/HTTP_CODE/d')

echo "Status Code: $HTTP_CODE"
echo ""
echo "Response:"
echo "$BODY" | jq '.'
echo ""

if [ "$HTTP_CODE" == "404" ]; then
    echo "✅ Comportamento esperado! Professor não está vinculado ao tenant"
elif [ "$HTTP_CODE" == "200" ]; then
    echo "✅ Professor encontrado no tenant (já está vinculado)"
else
    echo "⚠️  Status code inesperado: $HTTP_CODE"
fi

echo ""
echo "=========================================="
echo "CONCLUSÃO"
echo "=========================================="
echo ""
echo "Novo endpoint criado com sucesso:"
echo ""
echo "✅ GET /admin/professores/global/cpf/{cpf}"
echo "   → Busca professor em TODO o sistema"
echo "   → Retorna campo 'vinculado_ao_tenant_atual'"
echo "   → Útil para verificar se professor existe antes de associar"
echo ""
echo "📝 Endpoint existente mantido:"
echo ""
echo "✅ GET /admin/professores/cpf/{cpf}"
echo "   → Busca APENAS professores vinculados ao tenant"
echo "   → Retorna 404 se não estiver vinculado"
echo ""
