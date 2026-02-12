#!/bin/bash

BASE_URL="http://localhost:8080"
CPF="33344455566"  # Ana Costa (do seed)

echo "=========================================="
echo "TESTE: BUSCA GLOBAL DE PROFESSOR POR CPF"
echo "=========================================="
echo ""
echo "Testando busca GLOBAL (sem filtro de tenant) do professor:"
echo "CPF: $CPF"
echo ""

# 1. Fazer login
echo "1. Fazendo login..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@admin.com",
    "senha": "123456"
  }')

TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*' | sed 's/"token":"//')

if [ -z "$TOKEN" ]; then
    echo "❌ Erro: Não foi possível obter o token"
    echo "Response: $LOGIN_RESPONSE"
    exit 1
fi

echo "✅ Login realizado com sucesso"
echo ""

# 2. Buscar professor globalmente
echo "2. Buscando professor globalmente (GET /admin/professores/global/cpf/$CPF)..."
echo ""
GLOBAL_RESPONSE=$(curl -s -X GET "$BASE_URL/admin/professores/global/cpf/$CPF" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json")

echo "Response:"
echo "$GLOBAL_RESPONSE" | jq '.'
echo ""

# Verificar se encontrou
if echo "$GLOBAL_RESPONSE" | grep -q '"professor"'; then
    echo "✅ Professor encontrado globalmente!"
    
    # Extrair informações
    VINCULADO=$(echo "$GLOBAL_RESPONSE" | grep -o '"vinculado_ao_tenant_atual":[^,}]*' | sed 's/"vinculado_ao_tenant_atual"://')
    
    echo ""
    echo "Vinculado ao tenant atual: $VINCULADO"
    echo ""
    
    if [ "$VINCULADO" == "false" ]; then
        echo "ℹ️ Professor NÃO está vinculado ao tenant atual"
        echo "   Use POST /admin/professores para associá-lo"
    else
        echo "✅ Professor JÁ está vinculado ao tenant atual"
    fi
else
    echo "❌ Professor não encontrado globalmente"
fi

echo ""
echo "=========================================="
echo "3. COMPARAÇÃO: Busca dentro do tenant"
echo "=========================================="
echo ""
echo "Buscando professor no tenant (GET /admin/professores/cpf/$CPF)..."
echo ""
TENANT_RESPONSE=$(curl -s -X GET "$BASE_URL/admin/professores/cpf/$CPF" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json")

echo "Response:"
echo "$TENANT_RESPONSE" | jq '.'
echo ""

if echo "$TENANT_RESPONSE" | grep -q '"professor"'; then
    echo "✅ Professor encontrado no tenant (já está vinculado)"
else
    echo "❌ Professor NÃO encontrado no tenant (não está vinculado)"
fi

echo ""
echo "=========================================="
echo "CONCLUSÃO"
echo "=========================================="
echo ""
echo "Diferença entre os endpoints:"
echo ""
echo "• GET /admin/professores/global/cpf/{cpf}"
echo "  → Busca em TODOS os professores do sistema"
echo "  → Retorna campo 'vinculado_ao_tenant_atual'"
echo "  → Útil para verificar se professor existe antes de associar"
echo ""
echo "• GET /admin/professores/cpf/{cpf}"
echo "  → Busca APENAS professores vinculados ao tenant"
echo "  → Retorna 404 se não estiver vinculado"
echo "  → Útil para buscar professores já cadastrados no tenant"
echo ""
