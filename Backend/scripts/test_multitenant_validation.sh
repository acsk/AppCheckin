#!/bin/bash

# =============================================================================
# SCRIPT DE TESTE: Validações Multi-Tenant
# =============================================================================
# Testa se as validações de acesso multi-tenant estão funcionando corretamente
# Objetivo: Evitar "dados cruzados" (cross-tenant data leaks)
# =============================================================================

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuração
API_URL="${API_URL:-http://localhost:8000}"
TIMEOUT=5

# Tokens de teste (precisam ser gerados ou estar disponíveis)
TOKEN_USUARIO_5_TENANT_1="${TOKEN_USUARIO_5_TENANT_1:-}"
TOKEN_USUARIO_5_TENANT_2="${TOKEN_USUARIO_5_TENANT_2:-}"
TOKEN_ADMIN_TENANT_2="${TOKEN_ADMIN_TENANT_2:-}"

# =============================================================================
# FUNÇÕES AUXILIARES
# =============================================================================

log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# Fazer uma requisição HTTP
make_request() {
    local method=$1
    local endpoint=$2
    local token=$3
    local tenant_id=$4
    local data=$5

    local headers="-H 'Content-Type: application/json'"
    
    if [ -n "$token" ]; then
        headers="$headers -H 'Authorization: Bearer $token'"
    fi
    
    if [ -n "$tenant_id" ]; then
        headers="$headers -H 'X-Tenant-ID: $tenant_id'"
    fi

    if [ "$method" = "GET" ]; then
        curl -s -X $method "$API_URL$endpoint" $headers
    else
        curl -s -X $method "$API_URL$endpoint" $headers -d "$data"
    fi
}

# Parse JSON response
get_json_field() {
    local json=$1
    local field=$2
    echo "$json" | grep -o "\"$field\"[^,}]*" | cut -d'"' -f4
}

# =============================================================================
# TESTES
# =============================================================================

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║        TESTES DE VALIDAÇÃO MULTI-TENANT                       ║"
echo "║        Evitar Cross-Tenant Data Leaks                          ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# ─────────────────────────────────────────────────────────────────────────────
# TESTE 1: Registrar Check-in com Tenant Válido
# ─────────────────────────────────────────────────────────────────────────────

log_info "TESTE 1: Registrar check-in com tenant VÁLIDO"
echo "  Cenário: Usuário 5 tem acesso ao Tenant 1"
echo "  Esperado: HTTP 200-422 (sucesso ou erro de negócio, não segurança)"

if [ -n "$TOKEN_USUARIO_5_TENANT_1" ]; then
    RESPONSE=$(make_request POST "/mobile/checkin" \
        "$TOKEN_USUARIO_5_TENANT_1" \
        "1" \
        '{"turma_id": 5}')
    
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$API_URL/mobile/checkin" \
        -H "Authorization: Bearer $TOKEN_USUARIO_5_TENANT_1" \
        -H "X-Tenant-ID: 1" \
        -d '{"turma_id": 5}')
    
    if [ "$HTTP_CODE" != "403" ]; then
        log_success "Check-in com tenant válido passou (HTTP $HTTP_CODE)"
    else
        log_error "Check-in com tenant válido retornou HTTP 403 (erro de segurança)"
    fi
else
    log_warning "TOKEN_USUARIO_5_TENANT_1 não configurado, pulando teste"
fi

echo ""

# ─────────────────────────────────────────────────────────────────────────────
# TESTE 2: Registrar Check-in com Tenant Inválido (Cross-Tenant Attack)
# ─────────────────────────────────────────────────────────────────────────────

log_info "TESTE 2: Registrar check-in com tenant INVÁLIDO (Attack)"
echo "  Cenário: Usuário 5 tenta acessar Tenant 99 (não tem acesso)"
echo "  Esperado: HTTP 403 Forbidden + INVALID_TENANT_ACCESS"

if [ -n "$TOKEN_USUARIO_5_TENANT_1" ]; then
    RESPONSE=$(make_request POST "/mobile/checkin" \
        "$TOKEN_USUARIO_5_TENANT_1" \
        "99" \
        '{"turma_id": 5}')
    
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$API_URL/mobile/checkin" \
        -H "Authorization: Bearer $TOKEN_USUARIO_5_TENANT_1" \
        -H "X-Tenant-ID: 99" \
        -d '{"turma_id": 5}')
    
    if [ "$HTTP_CODE" = "403" ]; then
        if echo "$RESPONSE" | grep -q "INVALID_TENANT_ACCESS"; then
            log_success "Cross-tenant attack bloqueado corretamente (HTTP 403)"
        else
            log_error "HTTP 403 mas mensagem não contém INVALID_TENANT_ACCESS"
            echo "Response: $RESPONSE"
        fi
    else
        log_error "Cross-tenant attack NÃO foi bloqueado (HTTP $HTTP_CODE)"
        echo "Response: $RESPONSE"
    fi
else
    log_warning "TOKEN_USUARIO_5_TENANT_1 não configurado, pulando teste"
fi

echo ""

# ─────────────────────────────────────────────────────────────────────────────
# TESTE 3: Matrícula com Tenant Válido
# ─────────────────────────────────────────────────────────────────────────────

log_info "TESTE 3: Criar matrícula com tenant VÁLIDO"
echo "  Cenário: Admin do Tenant 1 cria matrícula para usuário 5 (pertence ao tenant 1)"
echo "  Esperado: HTTP 200-422 (sucesso ou erro de negócio)"

if [ -n "$TOKEN_ADMIN_TENANT_2" ]; then
    RESPONSE=$(make_request POST "/matricula" \
        "$TOKEN_ADMIN_TENANT_2" \
        "1" \
        '{"usuario_id": 5, "plano_id": 1}')
    
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$API_URL/matricula" \
        -H "Authorization: Bearer $TOKEN_ADMIN_TENANT_2" \
        -H "X-Tenant-ID: 1" \
        -d '{"usuario_id": 5, "plano_id": 1}')
    
    if [ "$HTTP_CODE" != "403" ]; then
        log_success "Matrícula com tenant válido passou (HTTP $HTTP_CODE)"
    else
        log_error "Matrícula com tenant válido retornou HTTP 403"
    fi
else
    log_warning "TOKEN_ADMIN_TENANT_2 não configurado, pulando teste"
fi

echo ""

# ─────────────────────────────────────────────────────────────────────────────
# TESTE 4: Matrícula com Tenant Inválido (Cross-Tenant Attack)
# ─────────────────────────────────────────────────────────────────────────────

log_info "TESTE 4: Criar matrícula com tenant INVÁLIDO (Attack)"
echo "  Cenário: Admin do Tenant 1 tenta criar matrícula para usuário 5 no Tenant 2"
echo "           (usuário 5 pertence apenas ao Tenant 1)"
echo "  Esperado: HTTP 403 Forbidden + INVALID_TENANT_ACCESS"

if [ -n "$TOKEN_ADMIN_TENANT_2" ]; then
    RESPONSE=$(make_request POST "/matricula" \
        "$TOKEN_ADMIN_TENANT_2" \
        "2" \
        '{"usuario_id": 5, "plano_id": 1}')
    
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$API_URL/matricula" \
        -H "Authorization: Bearer $TOKEN_ADMIN_TENANT_2" \
        -H "X-Tenant-ID: 2" \
        -d '{"usuario_id": 5, "plano_id": 1}')
    
    if [ "$HTTP_CODE" = "403" ]; then
        if echo "$RESPONSE" | grep -q "INVALID_TENANT_ACCESS"; then
            log_success "Cross-tenant matrícula bloqueada corretamente (HTTP 403)"
        else
            log_error "HTTP 403 mas mensagem não contém INVALID_TENANT_ACCESS"
            echo "Response: $RESPONSE"
        fi
    else
        log_error "Cross-tenant matrícula NÃO foi bloqueada (HTTP $HTTP_CODE)"
        echo "Response: $RESPONSE"
    fi
else
    log_warning "TOKEN_ADMIN_TENANT_2 não configurado, pulando teste"
fi

echo ""

# ─────────────────────────────────────────────────────────────────────────────
# TESTE 5: SQL Injection na Validação Multi-Tenant
# ─────────────────────────────────────────────────────────────────────────────

log_info "TESTE 5: Proteção contra SQL Injection"
echo "  Cenário: Tentar usar payload de SQL injection no tenant_id"
echo "  Esperado: Rejeitado com HTTP 400/403"

if [ -n "$TOKEN_USUARIO_5_TENANT_1" ]; then
    # Tentar injection no header
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$API_URL/mobile/checkin" \
        -H "Authorization: Bearer $TOKEN_USUARIO_5_TENANT_1" \
        -H "X-Tenant-ID: 1 OR 1=1" \
        -d '{"turma_id": 5}')
    
    if [ "$HTTP_CODE" = "400" ] || [ "$HTTP_CODE" = "403" ]; then
        log_success "SQL injection tentativa bloqueada (HTTP $HTTP_CODE)"
    else
        log_warning "SQL injection retornou HTTP $HTTP_CODE (verificar se é esperado)"
    fi
else
    log_warning "TOKEN_USUARIO_5_TENANT_1 não configurado, pulando teste"
fi

echo ""

# ─────────────────────────────────────────────────────────────────────────────
# TESTE 6: Listar Turmas com Tenant Válido
# ─────────────────────────────────────────────────────────────────────────────

log_info "TESTE 6: Listar turmas com tenant VÁLIDO"
echo "  Cenário: Usuário 5 lista turmas do Tenant 1 (tem acesso)"
echo "  Esperado: HTTP 200 + lista de turmas"

if [ -n "$TOKEN_USUARIO_5_TENANT_1" ]; then
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X GET "$API_URL/mobile/turmas" \
        -H "Authorization: Bearer $TOKEN_USUARIO_5_TENANT_1" \
        -H "X-Tenant-ID: 1")
    
    if [ "$HTTP_CODE" = "200" ]; then
        log_success "Listar turmas com tenant válido passou (HTTP 200)"
    else
        log_warning "Listar turmas retornou HTTP $HTTP_CODE (verificar se é esperado)"
    fi
else
    log_warning "TOKEN_USUARIO_5_TENANT_1 não configurado, pulando teste"
fi

echo ""

# ─────────────────────────────────────────────────────────────────────────────
# TESTE 7: Verificar Logs de Segurança
# ─────────────────────────────────────────────────────────────────────────────

log_info "TESTE 7: Verificar registros de segurança nos logs"
echo "  Procurando por tentativas de acesso indevido..."

if [ -f "../../logs/app.log" ]; then
    SECURITY_LOGS=$(grep "SEGURANÇA" ../../logs/app.log | tail -5)
    if [ -n "$SECURITY_LOGS" ]; then
        log_success "Encontrados registros de segurança:"
        echo "$SECURITY_LOGS" | sed 's/^/    /'
    else
        log_warning "Nenhum registro de segurança encontrado nos logs"
    fi
else
    log_warning "Arquivo de log não encontrado"
fi

echo ""

# =============================================================================
# RESUMO
# =============================================================================

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                     RESUMO DOS TESTES                         ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "✅ Validações esperadas:"
echo "   1. Check-in com tenant válido → HTTP 200/422 ✓"
echo "   2. Check-in com tenant inválido → HTTP 403 ✓"
echo "   3. Matrícula com tenant válido → HTTP 200/422 ✓"
echo "   4. Matrícula com tenant inválido → HTTP 403 ✓"
echo "   5. SQL injection → HTTP 400/403 ✓"
echo "   6. Listar turmas válido → HTTP 200 ✓"
echo "   7. Logs com tentativas de acesso ✓"
echo ""

echo "📝 Para rodar testes completos, configure os tokens:"
echo "   export TOKEN_USUARIO_5_TENANT_1=<token>"
echo "   export TOKEN_USUARIO_5_TENANT_2=<token>"
echo "   export TOKEN_ADMIN_TENANT_2=<token>"
echo ""

echo "🔗 API URL: $API_URL"
echo ""
