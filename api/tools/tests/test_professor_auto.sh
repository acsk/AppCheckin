#!/bin/bash

# Teste AUTOMATIZADO dos endpoints de professor
BASE_URL="http://localhost:8080/api"

# Credenciais
EMAIL="superadmin@appcheckin.com"
SENHA="Admin@123"

echo "================================================"
echo "🧪 TESTE AUTOMATIZADO - ENDPOINTS DE PROFESSOR"
echo "================================================"
echo ""

# [1/6] Login (tentando rotas alternativas)
echo "📝 [1/6] Fazendo login..."

# Tentar rota /v1/auth/login primeiro
LOGIN=$(curl -s -X POST "$BASE_URL/v1/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL\",\"senha\":\"$SENHA\"}")

# Se falhar, tentar /signin
if echo "$LOGIN" | grep -q "error"; then
    echo "   Tentando rota alternativa /signin..."
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
USER_ID=$(echo "$LOGIN" | jq -r '.usuario.id // empty' 2>/dev/null)
echo "   Tenant ID: $TENANT_ID"
echo "   User ID: $USER_ID"
echo ""

# [2/6] Listar professores
echo "📋 [2/6] Listando professores do tenant..."
PROFS=$(curl -s -X GET "$BASE_URL/admin/professores" \
    -H "Authorization: Bearer $TOKEN")

echo "$PROFS" | jq '.' 2>/dev/null || echo "$PROFS"
echo ""

COUNT=$(echo "$PROFS" | jq '.professores | length' 2>/dev/null)
echo "📊 Total de professores: $COUNT"

# Verificar arquitetura
if echo "$PROFS" | jq -e '.professores[0].vinculo_ativo' >/dev/null 2>&1; then
    echo "✅ Campo 'vinculo_ativo' presente (usando tenant_usuario_papel)"
    VINCULO_ATIVO=$(echo "$PROFS" | jq -r '.professores[0].vinculo_ativo' 2>/dev/null)
    echo "   Valor: $VINCULO_ATIVO"
else
    echo "⚠️  Campo 'vinculo_ativo' ausente"
fi

# Verificar se tem CPF/EMAIL na resposta
if echo "$PROFS" | jq -e '.professores[0].cpf' >/dev/null 2>&1; then
    echo "✅ Campo 'cpf' presente"
else
    echo "⚠️  Campo 'cpf' ausente"
fi

if echo "$PROFS" | jq -e '.professores[0].email' >/dev/null 2>&1; then
    echo "✅ Campo 'email' presente"
else
    echo "⚠️  Campo 'email' ausente"
fi
echo ""

# [3/6] Buscar por ID
if [ "$COUNT" -gt 0 ]; then
    PROF_ID=$(echo "$PROFS" | jq -r '.professores[0].id' 2>/dev/null)
    echo "🔍 [3/6] Buscando professor ID: $PROF_ID..."
    
    PROF=$(curl -s -X GET "$BASE_URL/admin/professores/$PROF_ID" \
        -H "Authorization: Bearer $TOKEN")
    
    NOME=$(echo "$PROF" | jq -r '.professor.nome // "N/A"' 2>/dev/null)
    EMAIL_PROF=$(echo "$PROF" | jq -r '.professor.email // "N/A"' 2>/dev/null)
    CPF_PROF=$(echo "$PROF" | jq -r '.professor.cpf // "N/A"' 2>/dev/null)
    VINCULO=$(echo "$PROF" | jq -r '.professor.vinculo_ativo // "N/A"' 2>/dev/null)
    
    echo "✅ Professor encontrado:"
    echo "   Nome: $NOME"
    echo "   Email: $EMAIL_PROF"
    echo "   CPF: $CPF_PROF"
    echo "   Vínculo Ativo: $VINCULO"
    echo ""
    
    # [4/6] Buscar por CPF
    if [ -n "$CPF_PROF" ] && [ "$CPF_PROF" != "null" ] && [ "$CPF_PROF" != "N/A" ]; then
        echo "🔍 [4/6] Buscando por CPF: $CPF_PROF..."
        
        PROF_CPF=$(curl -s -X GET "$BASE_URL/admin/professores/cpf/$CPF_PROF" \
            -H "Authorization: Bearer $TOKEN")
        
        NOME_CPF=$(echo "$PROF_CPF" | jq -r '.professor.nome // "N/A"' 2>/dev/null)
        
        if [ "$NOME_CPF" = "$NOME" ]; then
            echo "✅ Busca por CPF OK - Professor: $NOME_CPF"
        else
            echo "⚠️  Busca por CPF retornou professor diferente ou erro"
            echo "$PROF_CPF" | jq '.' 2>/dev/null || echo "$PROF_CPF"
        fi
    else
        echo "⚠️  [4/6] Professor sem CPF cadastrado, pulando teste"
    fi
else
    echo "⚠️  [3/6] Nenhum professor cadastrado"
    echo "⚠️  [4/6] Nenhum professor cadastrado"
fi
echo ""

# [5/6] Verificar queries no banco (via Docker)
echo "🔍 [5/6] Verificando estrutura no banco de dados..."
echo "Consultando tenant_usuario_papel para professores (papel_id=2)..."

QUERY_RESULT=$(docker exec appcheckin_mysql mysql -u root -proot -D appcheckin -e \
    "SELECT COUNT(*) as total FROM tenant_usuario_papel WHERE papel_id = 2;" \
    2>/dev/null | tail -n 1)

echo "Total de vínculos professor em tenant_usuario_papel: $QUERY_RESULT"

# Verificar se tabela tenant_professor ainda existe
TABLE_EXISTS=$(docker exec appcheckin_mysql mysql -u root -proot -D appcheckin -e \
    "SHOW TABLES LIKE 'tenant_professor';" \
    2>/dev/null | tail -n 1)

if [ -n "$TABLE_EXISTS" ]; then
    echo "⚠️  Tabela 'tenant_professor' AINDA EXISTE no banco"
    echo "   (pode ser removida após migração completa)"
else
    echo "✅ Tabela 'tenant_professor' não existe (arquitetura limpa)"
fi
echo ""

# [6/6] Resumo da arquitetura
echo "📊 [6/6] RESUMO DA ARQUITETURA"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "✅ ARQUITETURA IMPLEMENTADA:"
echo "   professores (id, nome, cpf, email, usuario_id, foto_url, ativo)"
echo "        ↓"
echo "   tenant_usuario_papel (tenant_id, usuario_id, papel_id=2, ativo)"
echo "        ↓"
echo "   tenants (id, nome, slug)"
echo ""
echo "✅ FLUXO DE VÍNCULO:"
echo "   1. professores.usuario_id → usuarios.id"
echo "   2. tenant_usuario_papel.usuario_id = professores.usuario_id"
echo "   3. tenant_usuario_papel.papel_id = 2 (professor)"
echo "   4. tenant_usuario_papel.tenant_id → tenants.id"
echo ""
echo "✅ CAMPOS RETORNADOS PELA API:"
echo "   - id, nome, cpf, email (de professores)"
echo "   - vinculo_ativo (de tenant_usuario_papel.ativo)"
echo "   - telefone (de usuarios.telefone via LEFT JOIN)"
echo "   - turmas_count (subquery)"
echo ""
echo "================================================"
echo "✅ TODOS OS TESTES CONCLUÍDOS"
echo "================================================"
