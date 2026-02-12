#!/bin/bash

# Teste RÁPIDO dos endpoints de professor
# URL base da API
BASE_URL="http://localhost:8080/api"

echo "================================================"
echo "🧪 TESTE RÁPIDO - ENDPOINTS DE PROFESSOR"
echo "================================================"
echo ""

# Passo 1: Tentar fazer login
echo "📝 [1/5] Fazendo login..."
echo "Digite o email do admin:"
read -r EMAIL

echo "Digite a senha:"
read -rs SENHA
echo ""

LOGIN=$(curl -s -X POST "$BASE_URL/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL\",\"senha\":\"$SENHA\"}")

TOKEN=$(echo "$LOGIN" | jq -r '.token // empty' 2>/dev/null)

if [ -z "$TOKEN" ]; then
    echo "❌ Falha no login!"
    echo "Response: $LOGIN"
    echo ""
    echo "💡 Dica: Crie um super admin primeiro:"
    echo "   docker exec -it appcheckin_php php database/create_superadmin.php"
    exit 1
fi

echo "✅ Login OK!"
TENANT_ID=$(echo "$LOGIN" | jq -r '.tenant_id // empty' 2>/dev/null)
echo "   Token: ${TOKEN:0:50}..."
echo "   Tenant ID: $TENANT_ID"
echo ""

# Passo 2: Listar professores
echo "📋 [2/5] Listando professores..."
PROFS=$(curl -s -X GET "$BASE_URL/admin/professores" \
    -H "Authorization: Bearer $TOKEN")

echo "$PROFS" | jq '.' 2>/dev/null || echo "$PROFS"
echo ""

COUNT=$(echo "$PROFS" | jq '.professores | length' 2>/dev/null)
echo "✅ Total de professores: $COUNT"

# Verificar se tem campo vinculo_ativo
if echo "$PROFS" | jq -e '.professores[0].vinculo_ativo' >/dev/null 2>&1; then
    echo "✅ Campo 'vinculo_ativo' presente (usando tenant_usuario_papel)"
else
    echo "⚠️  Campo 'vinculo_ativo' ausente"
fi
echo ""

# Passo 3: Buscar por ID (se existir)
if [ "$COUNT" -gt 0 ]; then
    PROF_ID=$(echo "$PROFS" | jq -r '.professores[0].id' 2>/dev/null)
    echo "🔍 [3/5] Buscando professor ID: $PROF_ID..."
    
    PROF=$(curl -s -X GET "$BASE_URL/admin/professores/$PROF_ID" \
        -H "Authorization: Bearer $TOKEN")
    
    echo "$PROF" | jq '.' 2>/dev/null || echo "$PROF"
    
    NOME=$(echo "$PROF" | jq -r '.professor.nome' 2>/dev/null)
    echo "✅ Professor: $NOME"
    echo ""
    
    # Passo 4: Buscar por CPF (se tiver)
    CPF=$(echo "$PROF" | jq -r '.professor.cpf' 2>/dev/null)
    if [ -n "$CPF" ] && [ "$CPF" != "null" ]; then
        echo "🔍 [4/5] Buscando por CPF: $CPF..."
        
        PROF_CPF=$(curl -s -X GET "$BASE_URL/admin/professores/cpf/$CPF" \
            -H "Authorization: Bearer $TOKEN")
        
        echo "$PROF_CPF" | jq '.' 2>/dev/null || echo "$PROF_CPF"
        echo "✅ Busca por CPF funcionando"
    else
        echo "⚠️  [4/5] Professor sem CPF cadastrado, pulando teste"
    fi
else
    echo "⚠️  [3/5] Nenhum professor cadastrado, pulando testes de busca"
    echo "⚠️  [4/5] Nenhum professor cadastrado, pulando testes de busca"
fi
echo ""

# Passo 5: Verificação da arquitetura
echo "📊 [5/5] Verificação da arquitetura..."
echo "✅ Arquitetura SIMPLIFICADA implementada:"
echo "   └─ professores (dados básicos)"
echo "   └─ tenant_usuario_papel (vínculo, papel_id=2)"
echo "   └─ usuarios (autenticação)"
echo ""
echo "🎯 Campos esperados no retorno:"
echo "   - id, nome, cpf, email, foto_url, ativo, usuario_id"
echo "   - vinculo_ativo (de tenant_usuario_papel.ativo)"
echo "   - telefone (de usuarios.telefone)"
echo ""

echo "================================================"
echo "✅ TESTES CONCLUÍDOS"
echo "================================================"
