#!/bin/bash

# Script de teste dos endpoints de Professor
# Após refatoração para usar APENAS tenant_usuario_papel

BASE_URL="http://localhost:8080"
API_URL="$BASE_URL/api/admin"

# Cores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Token de autenticação (você precisa ajustar com um token válido)
# Para obter token: POST /api/auth/login com email e senha
TOKEN=""

echo "=========================================="
echo "TESTE DOS ENDPOINTS DE PROFESSOR"
echo "Arquitetura: APENAS tenant_usuario_papel"
echo "=========================================="
echo ""

# Função para fazer request com token
make_request() {
    local method=$1
    local endpoint=$2
    local data=$3
    
    if [ -z "$TOKEN" ]; then
        echo -e "${RED}❌ TOKEN não definido! Execute login primeiro${NC}"
        return 1
    fi
    
    if [ -n "$data" ]; then
        curl -s -X "$method" "$API_URL$endpoint" \
            -H "Authorization: Bearer $TOKEN" \
            -H "Content-Type: application/json" \
            -d "$data"
    else
        curl -s -X "$method" "$API_URL$endpoint" \
            -H "Authorization: Bearer $TOKEN"
    fi
}

# Teste 1: Login
echo -e "${YELLOW}[1] Fazendo login...${NC}"
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/api/auth/login" \
    -H "Content-Type: application/json" \
    -d '{
        "email": "admin@teste.com",
        "senha": "senha123"
    }')

TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -n "$TOKEN" ]; then
    echo -e "${GREEN}✓ Login OK - Token obtido${NC}"
    echo "Token: ${TOKEN:0:50}..."
else
    echo -e "${RED}❌ Falha no login${NC}"
    echo "Response: $LOGIN_RESPONSE"
    echo ""
    echo -e "${YELLOW}Ajuste o email/senha no script ou crie um usuário admin primeiro${NC}"
    exit 1
fi
echo ""

# Teste 2: Listar professores
echo -e "${YELLOW}[2] Listando professores do tenant...${NC}"
PROFESSORES=$(make_request "GET" "/professores")
echo "$PROFESSORES" | jq '.' 2>/dev/null || echo "$PROFESSORES"
echo ""

# Verificar se retornou professores
PROF_COUNT=$(echo "$PROFESSORES" | jq '.professores | length' 2>/dev/null)
if [ -n "$PROF_COUNT" ] && [ "$PROF_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓ $PROF_COUNT professor(es) encontrado(s)${NC}"
    
    # Verificar campos esperados
    PRIMEIRO_PROF=$(echo "$PROFESSORES" | jq '.professores[0]' 2>/dev/null)
    echo -e "${YELLOW}Campos do primeiro professor:${NC}"
    echo "$PRIMEIRO_PROF" | jq 'keys' 2>/dev/null
    
    # Verificar se tem vinculo_ativo (novo campo)
    HAS_VINCULO=$(echo "$PRIMEIRO_PROF" | jq 'has("vinculo_ativo")' 2>/dev/null)
    if [ "$HAS_VINCULO" = "true" ]; then
        echo -e "${GREEN}✓ Campo 'vinculo_ativo' presente (tenant_usuario_papel)${NC}"
    else
        echo -e "${RED}❌ Campo 'vinculo_ativo' ausente${NC}"
    fi
    
    # Pegar ID do primeiro professor para próximos testes
    PROF_ID=$(echo "$PRIMEIRO_PROF" | jq -r '.id' 2>/dev/null)
    PROF_CPF=$(echo "$PRIMEIRO_PROF" | jq -r '.cpf' 2>/dev/null)
else
    echo -e "${YELLOW}⚠ Nenhum professor encontrado no tenant${NC}"
    PROF_ID=""
    PROF_CPF=""
fi
echo ""

# Teste 3: Buscar professor por ID (se tiver)
if [ -n "$PROF_ID" ] && [ "$PROF_ID" != "null" ]; then
    echo -e "${YELLOW}[3] Buscando professor por ID: $PROF_ID${NC}"
    PROFESSOR=$(make_request "GET" "/professores/$PROF_ID")
    echo "$PROFESSOR" | jq '.' 2>/dev/null || echo "$PROFESSOR"
    
    # Verificar se retornou dados
    PROF_NOME=$(echo "$PROFESSOR" | jq -r '.professor.nome' 2>/dev/null)
    if [ -n "$PROF_NOME" ] && [ "$PROF_NOME" != "null" ]; then
        echo -e "${GREEN}✓ Professor encontrado: $PROF_NOME${NC}"
    else
        echo -e "${RED}❌ Professor não encontrado ou erro${NC}"
    fi
    echo ""
fi

# Teste 4: Buscar professor por CPF (se tiver)
if [ -n "$PROF_CPF" ] && [ "$PROF_CPF" != "null" ]; then
    echo -e "${YELLOW}[4] Buscando professor por CPF: $PROF_CPF${NC}"
    PROFESSOR_CPF=$(make_request "GET" "/professores/cpf/$PROF_CPF")
    echo "$PROFESSOR_CPF" | jq '.' 2>/dev/null || echo "$PROFESSOR_CPF"
    
    # Verificar se retornou dados
    PROF_CPF_NOME=$(echo "$PROFESSOR_CPF" | jq -r '.professor.nome' 2>/dev/null)
    if [ -n "$PROF_CPF_NOME" ] && [ "$PROF_CPF_NOME" != "null" ]; then
        echo -e "${GREEN}✓ Professor encontrado por CPF: $PROF_CPF_NOME${NC}"
    else
        echo -e "${RED}❌ Professor não encontrado por CPF ou erro${NC}"
    fi
    echo ""
fi

# Teste 5: Criar novo professor (teste básico)
echo -e "${YELLOW}[5] Testando criação de professor...${NC}"
CPF_TESTE="12345678901"
EMAIL_TESTE="teste.professor@example.com"

CREATE_RESPONSE=$(make_request "POST" "/professores" '{
    "nome": "Professor Teste API",
    "email": "'$EMAIL_TESTE'",
    "cpf": "'$CPF_TESTE'",
    "telefone": "11999999999",
    "senha": "senha123"
}')

echo "$CREATE_RESPONSE" | jq '.' 2>/dev/null || echo "$CREATE_RESPONSE"

# Verificar se criou
NOVO_ID=$(echo "$CREATE_RESPONSE" | jq -r '.id' 2>/dev/null)
if [ -n "$NOVO_ID" ] && [ "$NOVO_ID" != "null" ]; then
    echo -e "${GREEN}✓ Professor criado com ID: $NOVO_ID${NC}"
    
    # Verificar se o vínculo foi criado em tenant_usuario_papel
    echo -e "${YELLOW}Verificando vínculo em tenant_usuario_papel...${NC}"
    PROF_CRIADO=$(make_request "GET" "/professores/$NOVO_ID")
    VINCULO_ATIVO=$(echo "$PROF_CRIADO" | jq -r '.professor.vinculo_ativo' 2>/dev/null)
    
    if [ "$VINCULO_ATIVO" = "1" ] || [ "$VINCULO_ATIVO" = "true" ]; then
        echo -e "${GREEN}✓ Vínculo ativo em tenant_usuario_papel${NC}"
    else
        echo -e "${RED}❌ Vínculo não está ativo${NC}"
    fi
else
    # Pode ser que já exista
    ERROR_MSG=$(echo "$CREATE_RESPONSE" | jq -r '.message' 2>/dev/null)
    if [[ "$ERROR_MSG" == *"já cadastrado"* ]]; then
        echo -e "${YELLOW}⚠ Professor já existe (esperado se rodou teste antes)${NC}"
    else
        echo -e "${RED}❌ Erro ao criar professor${NC}"
    fi
fi
echo ""

# Teste 6: Verificar estrutura do banco
echo -e "${YELLOW}[6] Resumo da arquitetura:${NC}"
echo "✓ professores → contém dados básicos (nome, cpf, email, usuario_id)"
echo "✓ tenant_usuario_papel → vínculo com tenant (papel_id=2 para professor)"
echo "✓ usuarios → dados de autenticação (email, senha, telefone)"
echo ""
echo -e "${GREEN}Campos esperados no retorno:${NC}"
echo "  - id, nome, cpf, email, foto_url, ativo, usuario_id"
echo "  - vinculo_ativo (vem de tenant_usuario_papel.ativo)"
echo "  - telefone (vem de usuarios.telefone)"
echo ""

echo "=========================================="
echo "TESTES CONCLUÍDOS"
echo "=========================================="
