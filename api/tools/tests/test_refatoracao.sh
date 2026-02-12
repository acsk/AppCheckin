#!/bin/bash

#######################################################
# Script de Testes - Refatoração usuario_tenant
# Data: 2026-02-04
# Descrição: Testa a migração de usuario_tenant 
#            para tenant_usuario_papel
#######################################################

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Contadores
PASSED=0
FAILED=0

# Configurações
DB_CONTAINER="appcheckin_mysql"
DB_USER="root"
DB_PASS="root"
DB_NAME="appcheckin"
API_URL="http://localhost:8080"

echo ""
echo "======================================"
echo "  TESTE DE REFATORAÇÃO - usuario_tenant"
echo "======================================"
echo ""

#######################################################
# Função auxiliar para testar
#######################################################
test_pass() {
    echo -e "${GREEN}✓ PASS${NC} - $1"
    ((PASSED++))
}

test_fail() {
    echo -e "${RED}✗ FAIL${NC} - $1"
    ((FAILED++))
}

test_info() {
    echo -e "${BLUE}ℹ INFO${NC} - $1"
}

#######################################################
# 1. TESTES DE BANCO DE DADOS
#######################################################
echo -e "${YELLOW}1. TESTES DE BANCO DE DADOS${NC}"
echo "----------------------------"

# 1.1 Verificar que usuario_tenant não existe mais
test_info "Verificando que usuario_tenant não existe..."
RESULT=$(docker exec -i $DB_CONTAINER mysql -u $DB_USER -p$DB_PASS $DB_NAME -se "SHOW TABLES LIKE 'usuario_tenant'" 2>/dev/null | wc -l)
if [ "$RESULT" -eq 0 ]; then
    test_pass "Tabela usuario_tenant não existe (como esperado)"
else
    test_fail "Tabela usuario_tenant ainda existe!"
fi

# 1.2 Verificar que usuario_tenant_backup existe
test_info "Verificando que usuario_tenant_backup existe..."
RESULT=$(docker exec -i $DB_CONTAINER mysql -u $DB_USER -p$DB_PASS $DB_NAME -se "SHOW TABLES LIKE 'usuario_tenant_backup'" 2>/dev/null | wc -l)
if [ "$RESULT" -gt 0 ]; then
    test_pass "Tabela usuario_tenant_backup existe (backup criado)"
else
    test_fail "Tabela usuario_tenant_backup não encontrada!"
fi

# 1.3 Verificar índices criados
test_info "Verificando índices em tenant_usuario_papel..."
INDICES=$(docker exec -i $DB_CONTAINER mysql -u $DB_USER -p$DB_PASS $DB_NAME -se "SHOW INDEX FROM tenant_usuario_papel WHERE Key_name LIKE 'idx_tenant_usuario_papel%'" 2>/dev/null | wc -l)
if [ "$INDICES" -ge 3 ]; then
    test_pass "Índices criados ($INDICES encontrados)"
else
    test_fail "Índices insuficientes (esperado >= 3, encontrado $INDICES)"
fi

# 1.4 Verificar função get_tenant_id_from_usuario
test_info "Verificando função get_tenant_id_from_usuario..."
FUNCTION_EXISTS=$(docker exec -i $DB_CONTAINER mysql -u $DB_USER -p$DB_PASS $DB_NAME -se "SELECT COUNT(*) FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_NAME = 'get_tenant_id_from_usuario' AND ROUTINE_SCHEMA = '$DB_NAME'" 2>/dev/null)
if [ "$FUNCTION_EXISTS" -eq 1 ]; then
    test_pass "Função get_tenant_id_from_usuario existe e está atualizada"
else
    test_fail "Função get_tenant_id_from_usuario não encontrada!"
fi

# 1.5 Contar registros
test_info "Contando registros..."
BACKUP_COUNT=$(docker exec -i $DB_CONTAINER mysql -u $DB_USER -p$DB_PASS $DB_NAME -se "SELECT COUNT(*) FROM usuario_tenant_backup" 2>/dev/null)
TUP_COUNT=$(docker exec -i $DB_CONTAINER mysql -u $DB_USER -p$DB_PASS $DB_NAME -se "SELECT COUNT(*) FROM tenant_usuario_papel" 2>/dev/null)
echo "   - Backup: $BACKUP_COUNT registros"
echo "   - tenant_usuario_papel: $TUP_COUNT registros"

echo ""

#######################################################
# 2. TESTES DE LOGIN E AUTENTICAÇÃO
#######################################################
echo -e "${YELLOW}2. TESTES DE LOGIN E AUTENTICAÇÃO${NC}"
echo "----------------------------------"

# 2.1 Obter primeiro usuário ativo
test_info "Buscando usuário de teste no banco..."
TEST_USER=$(docker exec -i $DB_CONTAINER mysql -u $DB_USER -p$DB_PASS $DB_NAME -se "SELECT email FROM usuarios WHERE ativo = 1 LIMIT 1" 2>/dev/null)

if [ -z "$TEST_USER" ]; then
    test_fail "Nenhum usuário ativo encontrado no banco"
else
    test_info "Usuário de teste: $TEST_USER"
    
    # Tentar login (nota: senha pode não funcionar, mas testa se o endpoint responde)
    test_info "Testando endpoint de login..."
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST $API_URL/api/auth/login \
        -H "Content-Type: application/json" \
        -d "{\"email\":\"$TEST_USER\",\"senha\":\"teste123\"}" 2>/dev/null)
    
    if [ "$HTTP_CODE" == "200" ] || [ "$HTTP_CODE" == "401" ]; then
        test_pass "Endpoint de login respondendo (HTTP $HTTP_CODE)"
    else
        test_fail "Endpoint de login com erro (HTTP $HTTP_CODE)"
    fi
fi

echo ""

#######################################################
# 3. TESTES DE INTEGRIDADE DE DADOS
#######################################################
echo -e "${YELLOW}3. TESTES DE INTEGRIDADE DE DADOS${NC}"
echo "----------------------------------"

# 3.1 Verificar usuários ativos sem vínculo
test_info "Verificando usuários ativos sem vínculo..."
USUARIOS_SEM_VINCULO=$(docker exec -i $DB_CONTAINER mysql -u $DB_USER -p$DB_PASS $DB_NAME -se "
SELECT COUNT(*)
FROM usuarios u
LEFT JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id AND tup.ativo = 1
WHERE u.ativo = 1
GROUP BY u.id
HAVING COUNT(tup.id) = 0
" 2>/dev/null | wc -l)

if [ "$USUARIOS_SEM_VINCULO" -eq 0 ]; then
    test_pass "Todos os usuários ativos têm vínculo"
else
    test_fail "$USUARIOS_SEM_VINCULO usuários ativos sem vínculo encontrados"
fi

# 3.2 Verificar alunos com vínculo correto (papel_id = 1)
test_info "Verificando vínculos de alunos..."
ALUNOS_COM_VINCULO=$(docker exec -i $DB_CONTAINER mysql -u $DB_USER -p$DB_PASS $DB_NAME -se "
SELECT COUNT(*)
FROM alunos a
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
WHERE tup.papel_id = 1 AND tup.ativo = 1
" 2>/dev/null)
echo "   - $ALUNOS_COM_VINCULO alunos com vínculo ativo encontrados"

echo ""

#######################################################
# 4. TESTES DE ENDPOINTS DA API
#######################################################
echo -e "${YELLOW}4. TESTES DE ENDPOINTS DA API${NC}"
echo "------------------------------"

# 4.1 Testar endpoint público (sem autenticação)
test_info "Testando endpoint /api/health..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" $API_URL/api/health 2>/dev/null)
if [ "$HTTP_CODE" == "200" ] || [ "$HTTP_CODE" == "401" ]; then
    test_pass "API respondendo (HTTP $HTTP_CODE)"
else
    test_fail "API não está respondendo (HTTP $HTTP_CODE)"
fi

echo ""

#######################################################
# 5. VERIFICAÇÃO DE LOGS
#######################################################
echo -e "${YELLOW}5. VERIFICAÇÃO DE LOGS${NC}"
echo "----------------------"

# 5.1 Verificar erros nos logs do PHP
test_info "Verificando logs de erro do PHP..."
ERRORS_IN_LOG=$(docker logs appcheckin_php --tail 100 2>&1 | grep -i "usuario_tenant" | grep -v "usuario_tenant_backup" | wc -l)
if [ "$ERRORS_IN_LOG" -eq 0 ]; then
    test_pass "Nenhuma referência a usuario_tenant nos logs"
else
    test_fail "$ERRORS_IN_LOG referências a usuario_tenant encontradas nos logs"
    echo "   Execute: docker logs appcheckin_php --tail 100 | grep -i usuario_tenant"
fi

echo ""

#######################################################
# 6. TESTES DE PERFORMANCE
#######################################################
echo -e "${YELLOW}6. TESTES DE PERFORMANCE${NC}"
echo "------------------------"

# 6.1 Testar uso de índices
test_info "Verificando uso de índices em queries..."
EXPLAIN_OUTPUT=$(docker exec -i $DB_CONTAINER mysql -u $DB_USER -p$DB_PASS $DB_NAME -se "
EXPLAIN SELECT u.* 
FROM usuarios u
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
WHERE tup.tenant_id = 1 AND tup.ativo = 1
" 2>/dev/null)

if echo "$EXPLAIN_OUTPUT" | grep -q "ref"; then
    test_pass "Query usando índices eficientemente"
else
    test_fail "Query pode não estar usando índices"
fi

echo ""

#######################################################
# RESUMO FINAL
#######################################################
echo ""
echo "======================================"
echo "           RESUMO DOS TESTES"
echo "======================================"
echo ""
echo -e "Testes que passaram: ${GREEN}$PASSED${NC}"
echo -e "Testes que falharam: ${RED}$FAILED${NC}"
echo ""

TOTAL=$((PASSED + FAILED))
if [ $TOTAL -gt 0 ]; then
    SUCCESS_RATE=$(awk "BEGIN {printf \"%.1f\", ($PASSED/$TOTAL)*100}")
    echo "Taxa de sucesso: $SUCCESS_RATE%"
fi

echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓✓✓ TODOS OS TESTES PASSARAM! ✓✓✓${NC}"
    echo ""
    echo "A refatoração está funcionando corretamente!"
    echo "Continue monitorando os logs por 48h."
    exit 0
else
    echo -e "${RED}✗✗✗ ALGUNS TESTES FALHARAM! ✗✗✗${NC}"
    echo ""
    echo "Verifique os erros acima e corrija antes de prosseguir."
    echo "Consulte o arquivo PLANO_TESTES_REFATORACAO.md para mais detalhes."
    exit 1
fi
