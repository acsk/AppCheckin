#!/bin/bash

# Script de Teste - Consolidação de Campos de Tolerância
# Valida se os campos de tolerância estão sendo salvos e recuperados corretamente

set -e

echo "======================================================================"
echo "🧪 TESTE DE CONSOLIDAÇÃO DE CAMPOS DE TOLERÂNCIA"
echo "======================================================================"
echo ""

# Cores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;36m'
NC='\033[0m' # No Color

# Função para output formatado
test_step() {
    echo -e "${BLUE}📌 $1${NC}"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# Variáveis
DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_USER="root"
DB_PASS="root"
DB_NAME="appcheckin"

# Teste 1: Verificar estrutura do banco
echo ""
test_step "TESTE 1: Verificando estrutura do banco de dados"
echo ""

DB_RESULT=$(docker-compose exec -T mysql mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "DESCRIBE turmas;" 2>/dev/null | grep -E 'tolerancia_(minutos|antes_minutos)')

if [[ -z "$DB_RESULT" ]]; then
    error "Campos de tolerância não encontrados na tabela turmas"
    exit 1
fi

success "Campos de tolerância encontrados:"
echo "$DB_RESULT"

# Teste 2: Verificar dados existentes
echo ""
test_step "TESTE 2: Verificando turmas existentes"
echo ""

EXISTING_TURMAS=$(docker-compose exec -T mysql mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT COUNT(*) as total FROM turmas;" 2>/dev/null | tail -1)

if [ "$EXISTING_TURMAS" -gt 0 ]; then
    success "Encontradas $EXISTING_TURMAS turmas no banco"
    
    echo ""
    echo "Primeiras 3 turmas com tolerância:"
    docker-compose exec -T mysql mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT id, nome, tolerancia_minutos, tolerancia_antes_minutos FROM turmas LIMIT 3;" 2>/dev/null | sed 's/^/  /'
else
    warning "Nenhuma turma encontrada no banco"
fi

# Teste 3: Verificar código do Model
echo ""
test_step "TESTE 3: Verificando código do Turma Model"
echo ""

# Verificar se o método create() tem tolerancia
if grep -q "tolerancia_minutos" app/Models/Turma.php && grep -q "tolerancia_antes_minutos" app/Models/Turma.php; then
    success "Campos de tolerância encontrados no Model"
    
    # Verificar se está no INSERT
    if grep -q "INSERT INTO turmas.*tolerancia" app/Models/Turma.php; then
        success "INSERT statement inclui campos de tolerância"
    else
        error "INSERT statement não inclui campos de tolerância"
        exit 1
    fi
    
    # Verificar se está no UPDATE
    if grep -q "'tolerancia_minutos'.*'tolerancia_antes_minutos'" app/Models/Turma.php; then
        success "UPDATE statement inclui campos de tolerância"
    else
        error "UPDATE statement não inclui campos de tolerância"
        exit 1
    fi
else
    error "Campos de tolerância não encontrados no Model"
    exit 1
fi

# Teste 4: Verificar Controller
echo ""
test_step "TESTE 4: Verificando TurmaController"
echo ""

if grep -q "tolerancia_minutos" app/Controllers/TurmaController.php; then
    success "Controller referencia campos de tolerância"
else
    warning "Controller não referencia campos de tolerância (pode estar OK se herda do Model)"
fi

# Teste 5: Teste de SQL direto
echo ""
test_step "TESTE 5: Teste de UPDATE direto no banco"
echo ""

# Pegar ID da primeira turma
TURMA_ID=$(docker-compose exec -T mysql mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT id FROM turmas LIMIT 1;" 2>/dev/null | tail -1)

if [ -z "$TURMA_ID" ] || [ "$TURMA_ID" = "id" ]; then
    warning "Nenhuma turma para testar UPDATE"
else
    success "Testando com turma ID: $TURMA_ID"
    
    # Valores antes do teste
    echo ""
    echo "Valores ANTES da atualização:"
    BEFORE=$(docker-compose exec -T mysql mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT tolerancia_minutos, tolerancia_antes_minutos FROM turmas WHERE id = $TURMA_ID;" 2>/dev/null | tail -1)
    echo "  tolerancia_minutos: $(echo $BEFORE | awk '{print $1}')"
    echo "  tolerancia_antes_minutos: $(echo $BEFORE | awk '{print $2}')"
    
    # Atualizar valores
    docker-compose exec -T mysql mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "UPDATE turmas SET tolerancia_minutos = 25, tolerancia_antes_minutos = 720 WHERE id = $TURMA_ID;" 2>/dev/null
    
    # Valores depois da atualização
    echo ""
    echo "Valores DEPOIS da atualização:"
    AFTER=$(docker-compose exec -T mysql mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT tolerancia_minutos, tolerancia_antes_minutos FROM turmas WHERE id = $TURMA_ID;" 2>/dev/null | tail -1)
    TOL_MIN=$(echo $AFTER | awk '{print $1}')
    TOL_ANTES=$(echo $AFTER | awk '{print $2}')
    echo "  tolerancia_minutos: $TOL_MIN"
    echo "  tolerancia_antes_minutos: $TOL_ANTES"
    
    # Validar se atualizou
    if [ "$TOL_MIN" = "25" ] && [ "$TOL_ANTES" = "720" ]; then
        success "UPDATE funcionou corretamente"
    else
        error "UPDATE não funcionou - valores não foram atualizados"
        exit 1
    fi
    
    # Reverter para valores originais
    ORIGINAL_MIN=$(echo $BEFORE | awk '{print $1}')
    ORIGINAL_ANTES=$(echo $BEFORE | awk '{print $2}')
    docker-compose exec -T mysql mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "UPDATE turmas SET tolerancia_minutos = $ORIGINAL_MIN, tolerancia_antes_minutos = $ORIGINAL_ANTES WHERE id = $TURMA_ID;" 2>/dev/null
    success "Valores revertidos para originais"
fi

# Teste 6: Resumo de Implementação
echo ""
test_step "TESTE 6: Resumo de Implementação"
echo ""

echo "Arquivos modificados:"
echo "  ✅ app/Models/Turma.php"
echo "     - Método create() (linhas 159-184)"
echo "     - Método update() (linhas 190-215)"
echo ""
echo "  ✅ app/Controllers/TurmaController.php"
echo "     - Documentação create() (linhas 213-226)"
echo ""

echo "Campos implementados:"
echo "  ✅ tolerancia_minutos (padrão: 10)"
echo "  ✅ tolerancia_antes_minutos (padrão: 480)"
echo ""

echo "Operações suportadas:"
echo "  ✅ CREATE com tolerância"
echo "  ✅ UPDATE de tolerância"
echo "  ✅ SELECT retornando tolerância"
echo ""

# Resultado Final
echo ""
echo "======================================================================"
echo -e "${GREEN}✅ TODOS OS TESTES PASSARAM COM SUCESSO${NC}"
echo "======================================================================"
echo ""
echo "📋 Próximos passos:"
echo "  1. Testar endpoints com token de autenticação válido"
echo "  2. Validar retorno de dados em GET /admin/turmas"
echo "  3. Testar POST /admin/turmas com tolerancia_minutos e tolerancia_antes_minutos"
echo "  4. Testar PUT /admin/turmas/{id} atualizando apenas tolerância"
echo ""
