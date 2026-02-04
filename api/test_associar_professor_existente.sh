#!/bin/bash

# Teste de Associação de Professores Existentes
# Este script testa o cenário 2: Professor existe globalmente, mas não está vinculado ao tenant

BASE_URL="http://localhost:8080/api"
EMAIL="superadmin@appcheckin.com"
SENHA="Admin@123"

echo "================================================"
echo "🧪 TESTE: ASSOCIAR PROFESSORES EXISTENTES"
echo "================================================"
echo ""

# [1] Login
echo "📝 [1/4] Fazendo login..."
LOGIN=$(curl -s -X POST "$BASE_URL/v1/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL\",\"senha\":\"$SENHA\"}")

if echo "$LOGIN" | grep -q "error"; then
    LOGIN=$(curl -s -X POST "$BASE_URL/signin" \
        -H "Content-Type: application/json" \
        -d "{\"email\":\"$EMAIL\",\"senha\":\"$SENHA\"}")
fi

TOKEN=$(echo "$LOGIN" | jq -r '.token // empty' 2>/dev/null)

if [ -z "$TOKEN" ]; then
    echo "❌ Falha no login!"
    echo "$LOGIN" | jq '.' 2>/dev/null || echo "$LOGIN"
    exit 1
fi

echo "✅ Login OK!"
TENANT_ID=$(echo "$LOGIN" | jq -r '.tenant_id // empty' 2>/dev/null)
echo "   Tenant ID: $TENANT_ID"
echo ""

# [2] Verificar professores antes da associação
echo "📊 [2/4] Verificando professores no tenant ANTES..."
PROFS_ANTES=$(curl -s -X GET "$BASE_URL/admin/professores" \
    -H "Authorization: Bearer $TOKEN")

COUNT_ANTES=$(echo "$PROFS_ANTES" | jq '.professores | length' 2>/dev/null)
echo "Total de professores no tenant: $COUNT_ANTES"
echo ""

# [3] Associar Professor 1: Maria Oliveira
echo "🔗 [3/4] Associando Professor 1: Maria Oliveira..."
PROF1=$(curl -s -X POST "$BASE_URL/admin/professores" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "nome": "Maria Oliveira",
        "email": "prof.maria.oliveira@exemplo.com",
        "cpf": "11122233344",
        "telefone": "11987654321"
    }')

echo "$PROF1" | jq '.' 2>/dev/null || echo "$PROF1"
echo ""

# Verificar resultado
if echo "$PROF1" | jq -e '.professor.id' >/dev/null 2>&1; then
    PROF1_ID=$(echo "$PROF1" | jq -r '.professor.id')
    USUARIO_CRIADO=$(echo "$PROF1" | jq -r '.usuario.criado')
    PROF_EXISTIA=$(echo "$PROF1" | jq -r '.professor_existia')
    TEM_SENHA=$(echo "$PROF1" | jq -e '.credenciais.senha_temporaria' >/dev/null 2>&1 && echo "sim" || echo "não")
    
    echo "✅ Professor associado:"
    echo "   ID: $PROF1_ID"
    echo "   Usuário criado? $USUARIO_CRIADO"
    echo "   Professor existia? $PROF_EXISTIA"
    echo "   Gerou senha? $TEM_SENHA"
    
    if [ "$USUARIO_CRIADO" = "false" ] && [ "$PROF_EXISTIA" = "true" ] && [ "$TEM_SENHA" = "não" ]; then
        echo "   ✅ CENÁRIO 2 FUNCIONANDO! (Professor existente associado)"
    else
        echo "   ⚠️  Resultado inesperado"
    fi
else
    echo "❌ Erro ao associar professor"
    ERROR_MSG=$(echo "$PROF1" | jq -r '.message // "Erro desconhecido"')
    echo "   Mensagem: $ERROR_MSG"
fi
echo ""

# [4] Verificar professores após associação
echo "📊 [4/4] Verificando professores no tenant DEPOIS..."
PROFS_DEPOIS=$(curl -s -X GET "$BASE_URL/admin/professores" \
    -H "Authorization: Bearer $TOKEN")

COUNT_DEPOIS=$(echo "$PROFS_DEPOIS" | jq '.professores | length' 2>/dev/null)
echo "Total de professores no tenant: $COUNT_DEPOIS"
echo ""

# Verificar se Maria Oliveira aparece na lista
MARIA_NA_LISTA=$(echo "$PROFS_DEPOIS" | jq -r '.professores[] | select(.cpf == "11122233344") | .nome' 2>/dev/null)
if [ -n "$MARIA_NA_LISTA" ]; then
    echo "✅ Maria Oliveira agora aparece na lista do tenant"
    
    # Mostrar detalhes
    echo "$PROFS_DEPOIS" | jq '.professores[] | select(.cpf == "11122233344")' 2>/dev/null
else
    echo "⚠️  Maria Oliveira NÃO aparece na lista"
fi
echo ""

# RESUMO
echo "================================================"
echo "📊 RESUMO DO TESTE"
echo "================================================"
echo ""
echo "Professores seed criados (sem tenant):"
echo "  1. Maria Oliveira   - CPF: 11122233344"
echo "  2. Pedro Santos     - CPF: 22233344455"
echo "  3. Ana Costa        - CPF: 33344455566"
echo ""
echo "Professores no tenant:"
echo "  ANTES:  $COUNT_ANTES"
echo "  DEPOIS: $COUNT_DEPOIS"
echo "  DIFF:   $((COUNT_DEPOIS - COUNT_ANTES))"
echo ""

if [ $((COUNT_DEPOIS - COUNT_ANTES)) -eq 1 ]; then
    echo "✅ Professor associado com sucesso!"
    echo "✅ Arquitetura simplificada funcionando!"
    echo ""
    echo "🔍 Verificar no banco:"
    echo "   SELECT * FROM tenant_usuario_papel"
    echo "   WHERE usuario_id = 101 AND papel_id = 2;"
else
    echo "⚠️  Número de professores não aumentou conforme esperado"
fi

echo ""
echo "================================================"
echo "✅ TESTE CONCLUÍDO"
echo "================================================"
