#!/bin/bash

# ============================================
# TESTES: Integração Assinaturas + Matrículas
# ============================================
# Executar com: bash test-integracao-assinaturas-matriculas.sh

API_URL="http://localhost:8080"
ADMIN_TOKEN="seu_token_aqui"
ACADEMIA_ID="1"

# Cores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Contadores
TESTS_PASSED=0
TESTS_FAILED=0

# ============================================
# Funções Auxiliares
# ============================================

print_header() {
    echo -e "\n${BLUE}═══════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════${NC}\n"
}

print_test() {
    echo -e "${YELLOW}🧪 TEST:${NC} $1"
}

print_success() {
    echo -e "${GREEN}✅ PASSOU:${NC} $1"
    ((TESTS_PASSED++))
}

print_error() {
    echo -e "${RED}❌ FALHOU:${NC} $1"
    ((TESTS_FAILED++))
}

check_response() {
    local response=$1
    local expected_type=$2
    local test_name=$3

    if echo "$response" | grep -q "\"type\":\"$expected_type\""; then
        print_success "$test_name"
        return 0
    else
        print_error "$test_name"
        echo "Response: $response"
        return 1
    fi
}

# ============================================
# TESTE 1: Criar Matrícula COM Assinatura
# ============================================

test_criar_matricula_com_assinatura() {
    print_header "TESTE 1: Criar Matrícula COM Assinatura Automática"

    print_test "Enviando requisição POST /admin/matriculas"

    RESPONSE=$(curl -s -X POST "$API_URL/admin/matriculas" \
        -H "Authorization: Bearer $ADMIN_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{
            "aluno_id": 1,
            "plano_id": 2,
            "data_inicio": "2025-01-20",
            "forma_pagamento": "cartao_credito",
            "criar_assinatura": true
        }')

    echo "Response: $RESPONSE"

    # Verificar se matrícula foi criada
    if echo "$RESPONSE" | grep -q '"type":"success"'; then
        print_success "Matrícula criada com assinatura"

        # Extrair IDs
        MATRICULA_ID=$(echo "$RESPONSE" | grep -o '"matricula":\{"id":[0-9]*' | grep -o '[0-9]*')
        ASSINATURA_ID=$(echo "$RESPONSE" | grep -o '"assinatura":\{"id":[0-9]*' | grep -o '[0-9]*')

        echo "  └─ Matrícula ID: $MATRICULA_ID"
        echo "  └─ Assinatura ID: $ASSINATURA_ID"

        # Armazenar para usar em testes posteriores
        export MATRICULA_ID
        export ASSINATURA_ID
    else
        print_error "Erro ao criar matrícula"
    fi
}

# ============================================
# TESTE 2: Obter Assinatura da Matrícula
# ============================================

test_obter_assinatura() {
    print_header "TESTE 2: Obter Assinatura Associada à Matrícula"

    if [ -z "$MATRICULA_ID" ]; then
        print_error "MATRICULA_ID não definido. Execute o teste anterior primeiro"
        return
    fi

    print_test "Enviando GET /admin/matriculas/{id}/assinatura"

    RESPONSE=$(curl -s -X GET "$API_URL/admin/matriculas/$MATRICULA_ID/assinatura" \
        -H "Authorization: Bearer $ADMIN_TOKEN")

    echo "Response: $RESPONSE"

    check_response "$RESPONSE" "success" "Obter assinatura da matrícula"
}

# ============================================
# TESTE 3: Suspender Matrícula (Sincroniza Assinatura)
# ============================================

test_suspender_matricula() {
    print_header "TESTE 3: Suspender Matrícula (Deve Sincronizar Assinatura)"

    if [ -z "$MATRICULA_ID" ]; then
        print_error "MATRICULA_ID não definido"
        return
    fi

    print_test "Enviando POST /admin/matriculas/{id}/suspender"

    RESPONSE=$(curl -s -X POST "$API_URL/admin/matriculas/$MATRICULA_ID/suspender" \
        -H "Authorization: Bearer $ADMIN_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{
            "motivo": "Atraso em pagamento"
        }')

    echo "Response: $RESPONSE"

    check_response "$RESPONSE" "success" "Suspender matrícula"
}

# ============================================
# TESTE 4: Verificar Sincronização
# ============================================

test_verificar_sincronizacao() {
    print_header "TESTE 4: Verificar Status de Sincronização"

    if [ -z "$ASSINATURA_ID" ]; then
        print_error "ASSINATURA_ID não definido"
        return
    fi

    print_test "Enviando GET /admin/assinaturas/{id}/status-sincronizacao"

    RESPONSE=$(curl -s -X GET "$API_URL/admin/assinaturas/$ASSINATURA_ID/status-sincronizacao" \
        -H "Authorization: Bearer $ADMIN_TOKEN")

    echo "Response: $RESPONSE"

    # Verificar se está sincronizado
    if echo "$RESPONSE" | grep -q '"sincronizado":true'; then
        print_success "Assinatura sincronizada corretamente"
    else
        print_error "Assinatura e matrícula desincronizadas"
    fi

    # Verificar se ambos têm status 'suspensa'
    if echo "$RESPONSE" | grep -q '"assinatura_status":"suspensa"'; then
        print_success "Status da assinatura é SUSPENSA (sincronizado)"
    else
        print_error "Status da assinatura não é SUSPENSA"
    fi
}

# ============================================
# TESTE 5: Reativar Matrícula
# ============================================

test_reativar_matricula() {
    print_header "TESTE 5: Reativar Matrícula (Sincroniza Assinatura)"

    if [ -z "$MATRICULA_ID" ]; then
        print_error "MATRICULA_ID não definido"
        return
    fi

    print_test "Enviando POST /admin/matriculas/{id}/reativar"

    RESPONSE=$(curl -s -X POST "$API_URL/admin/matriculas/$MATRICULA_ID/reativar" \
        -H "Authorization: Bearer $ADMIN_TOKEN")

    echo "Response: $RESPONSE"

    check_response "$RESPONSE" "success" "Reativar matrícula"

    # Verificar se sincronizou
    sleep 1  # Aguardar trigger
    test_verificar_sincronizacao_apos_reativar
}

test_verificar_sincronizacao_apos_reativar() {
    print_test "Verificando se assinatura foi reativada"

    RESPONSE=$(curl -s -X GET "$API_URL/admin/assinaturas/$ASSINATURA_ID/status-sincronizacao" \
        -H "Authorization: Bearer $ADMIN_TOKEN")

    if echo "$RESPONSE" | grep -q '"assinatura_status":"ativa"'; then
        print_success "Assinatura reativada (status ATIVA)"
    else
        print_error "Assinatura não foi reativada"
    fi
}

# ============================================
# TESTE 6: Criar Matrícula SEM Assinatura
# ============================================

test_criar_matricula_sem_assinatura() {
    print_header "TESTE 6: Criar Matrícula SEM Assinatura Automática"

    print_test "Enviando POST /admin/matriculas com criar_assinatura=false"

    RESPONSE=$(curl -s -X POST "$API_URL/admin/matriculas" \
        -H "Authorization: Bearer $ADMIN_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{
            "aluno_id": 2,
            "plano_id": 3,
            "data_inicio": "2025-01-20",
            "forma_pagamento": "dinheiro",
            "criar_assinatura": false
        }')

    echo "Response: $RESPONSE"

    if echo "$RESPONSE" | grep -q '"type":"success"'; then
        print_success "Matrícula criada SEM assinatura"
        MATRICULA_ID_2=$(echo "$RESPONSE" | grep -o '"matricula":\{"id":[0-9]*' | grep -o '[0-9]*')
        export MATRICULA_ID_2
        echo "  └─ Matrícula ID: $MATRICULA_ID_2"
    else
        print_error "Erro ao criar matrícula sem assinatura"
    fi
}

# ============================================
# TESTE 7: Criar Assinatura para Matrícula Existente
# ============================================

test_criar_assinatura_para_matricula_existente() {
    print_header "TESTE 7: Criar Assinatura para Matrícula Existente"

    if [ -z "$MATRICULA_ID_2" ]; then
        test_criar_matricula_sem_assinatura
    fi

    if [ -z "$MATRICULA_ID_2" ]; then
        print_error "MATRICULA_ID_2 não definido"
        return
    fi

    print_test "Enviando POST /admin/matriculas/{id}/assinatura"

    RESPONSE=$(curl -s -X POST "$API_URL/admin/matriculas/$MATRICULA_ID_2/assinatura" \
        -H "Authorization: Bearer $ADMIN_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{
            "renovacoes": 12
        }')

    echo "Response: $RESPONSE"

    check_response "$RESPONSE" "success" "Criar assinatura para matrícula existente"
}

# ============================================
# TESTE 8: Listar Matrículas COM Assinaturas
# ============================================

test_listar_matriculas_com_assinaturas() {
    print_header "TESTE 8: Listar Matrículas com Dados de Assinatura"

    print_test "Enviando GET /admin/matriculas?incluir_assinaturas=true"

    RESPONSE=$(curl -s -X GET "$API_URL/admin/matriculas?incluir_assinaturas=true" \
        -H "Authorization: Bearer $ADMIN_TOKEN")

    echo "Response: $RESPONSE" | head -c 500

    if echo "$RESPONSE" | grep -q '"assinatura'; then
        print_success "Matrículas listadas COM dados de assinatura"
    else
        print_error "Dados de assinatura não incluídos na listagem"
    fi
}

# ============================================
# TESTE 9: Listar Assinaturas Sem Matrícula
# ============================================

test_listar_assinaturas_orfas() {
    print_header "TESTE 9: Listar Assinaturas Sem Matrícula Associada"

    print_test "Enviando GET /admin/assinaturas/sem-matricula"

    RESPONSE=$(curl -s -X GET "$API_URL/admin/assinaturas/sem-matricula" \
        -H "Authorization: Bearer $ADMIN_TOKEN")

    echo "Response: $RESPONSE" | head -c 500

    check_response "$RESPONSE" "success" "Listar assinaturas órfãs"
}

# ============================================
# TESTE 10: Sincronizar Manualmente
# ============================================

test_sincronizar_manualmente() {
    print_header "TESTE 10: Forçar Sincronização Manual"

    if [ -z "$ASSINATURA_ID" ]; then
        print_error "ASSINATURA_ID não definido"
        return
    fi

    print_test "Enviando POST /admin/assinaturas/{id}/sincronizar-matricula"

    RESPONSE=$(curl -s -X POST "$API_URL/admin/assinaturas/$ASSINATURA_ID/sincronizar-matricula" \
        -H "Authorization: Bearer $ADMIN_TOKEN")

    echo "Response: $RESPONSE"

    check_response "$RESPONSE" "success" "Sincronizar manualmente"
}

# ============================================
# TESTE 11: Verificar Integridade de Dados
# ============================================

test_integridade_dados() {
    print_header "TESTE 11: Verificar Integridade de Dados (Sem Desincronizações)"

    print_test "Verificando assinaturas desincronizadas"

    RESPONSE=$(curl -s -X GET "$API_URL/admin/assinaturas/desincronizadas" \
        -H "Authorization: Bearer $ADMIN_TOKEN")

    echo "Response: $RESPONSE" | head -c 500

    if echo "$RESPONSE" | grep -q '"desincronizacoes":0\|"total":0'; then
        print_success "Nenhuma desincronização detectada"
    else
        print_error "Desincronizações detectadas!"
    fi
}

# ============================================
# TESTE 12: Validar Regras de Negócio
# ============================================

test_validacoes() {
    print_header "TESTE 12: Validar Regras de Negócio"

    # Teste 12a: Não permitir assinatura duplicada
    print_test "Tentando criar assinatura duplicada"

    RESPONSE=$(curl -s -X POST "$API_URL/admin/matriculas/$MATRICULA_ID/assinatura" \
        -H "Authorization: Bearer $ADMIN_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{}')

    if echo "$RESPONSE" | grep -q '"type":"error"'; then
        print_success "Assinatura duplicada rejeitada corretamente"
    else
        print_error "Sistema permitiu assinatura duplicada (deveria ter rejeitado)"
    fi

    # Teste 12b: Verificar se cancelamento de assinatura cancela matrícula
    print_test "Testando cancelamento em cascata"
    # (Implementar se houver regra de cascata)
}

# ============================================
# RELATÓRIO FINAL
# ============================================

print_report() {
    echo -e "\n${BLUE}═══════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}RELATÓRIO DE TESTES${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════${NC}\n"

    echo -e "✅ Testes Passados: ${GREEN}$TESTS_PASSED${NC}"
    echo -e "❌ Testes Falhados: ${RED}$TESTS_FAILED${NC}"
    echo -e "📊 Total: $((TESTS_PASSED + TESTS_FAILED))"

    TAXA_SUCESSO=$((TESTS_PASSED * 100 / (TESTS_PASSED + TESTS_FAILED))%)

    if [ $TESTS_FAILED -eq 0 ]; then
        echo -e "\n${GREEN}🎉 TODOS OS TESTES PASSARAM! 🎉${NC}\n"
    else
        echo -e "\n${YELLOW}⚠️  Alguns testes falharam. Verifique os erros acima.${NC}\n"
    fi
}

# ============================================
# EXECUTAR TODOS OS TESTES
# ============================================

main() {
    print_header "INICIANDO TESTES DE INTEGRAÇÃO ASSINATURAS + MATRÍCULAS"

    echo "API URL: $API_URL"
    echo "Token: ${ADMIN_TOKEN:0:20}..."
    echo ""

    test_criar_matricula_com_assinatura
    test_obter_assinatura
    test_suspender_matricula
    test_verificar_sincronizacao
    test_reativar_matricula
    test_criar_matricula_sem_assinatura
    test_criar_assinatura_para_matricula_existente
    test_listar_matriculas_com_assinaturas
    test_listar_assinaturas_orfas
    test_sincronizar_manualmente
    test_integridade_dados
    test_validacoes

    print_report
}

# Executar
main
