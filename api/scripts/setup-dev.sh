#!/bin/bash
# 
# Setup Initial - Script para preparar ambiente de desenvolvimento
# 
# Uso:
#   chmod +x scripts/setup-dev.sh
#   ./scripts/setup-dev.sh
#
# O que faz:
#   1. Verifica conexão com servidor
#   2. Verifica saúde da API
#   3. Valida banco de dados
#   4. Oferece opção de limpar banco
#   5. Cria SuperAdmin se não existir
#   6. Mostra resumo final
#

set -e

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configurações
API_URL="${API_URL:-https://api.appcheckin.com.br}"
TIMEOUT=5

echo -e "${CYAN}╔════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║  Setup do Ambiente de Desenvolvimento             ║${NC}"
echo -e "${CYAN}║  AppCheckin API                                    ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════════════╝${NC}\n"

# Função para printing com timestamp
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

# 1. Verificar se está em ambiente de desenvolvimento
log_info "Verificando ambiente..."

if [ -f ".env.production" ]; then
    APP_ENV=$(grep "^APP_ENV=" .env.production | cut -d'=' -f2)
    if [ "$APP_ENV" = "production" ]; then
        log_error "Este script NÃO pode ser executado em PRODUÇÃO!"
        echo -e "${RED}APP_ENV=production detectado em .env.production${NC}"
        exit 1
    fi
    log_success "Ambiente: $APP_ENV"
else
    log_warning ".env.production não encontrado - assumindo desenvolvimento"
fi

# 2. Testar conectividade com API
log_info "\nTestando conectividade com a API..."
if curl -s --max-time $TIMEOUT "$API_URL/health" > /dev/null 2>&1; then
    log_success "API respondendo em $API_URL"
else
    log_warning "API pode não estar respondendo - verifique a conexão"
fi

# 3. Verificar saúde da API
log_info "\nVerificando saúde da API..."
HEALTH_CHECK=$(curl -s --max-time $TIMEOUT "$API_URL/health" 2>/dev/null || echo '{}')

if echo "$HEALTH_CHECK" | grep -q '"status".*"ok"'; then
    log_success "API está OK"
    echo "$HEALTH_CHECK" | grep -o '"database".*"[^"]*"' && true || true
else
    log_warning "API pode estar com problemas"
fi

# 4. Verificar estado do banco local
log_info "\nVerificando estado do banco de dados..."
if [ -f "database/check_database_state.php" ]; then
    log_success "Script de verificação encontrado"
    
    # Tentar executar se PHP estiver disponível
    if command -v php &> /dev/null; then
        echo ""
        php database/check_database_state.php || true
    else
        log_warning "PHP não encontrado - pulando verificação detalhada"
    fi
else
    log_warning "Script de verificação não encontrado"
fi

# 5. Ofertar opção de limpeza
echo ""
log_info "Deseja limpar o banco de dados?"
echo -e "   ${YELLOW}⚠️  AVISO: Todos os dados serão apagados (exceto SuperAdmin)${NC}"
read -p "Continuar? (s/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Ss]$ ]]; then
    if [ -f "database/cleanup.php" ]; then
        log_info "Executando limpeza..."
        php database/cleanup.php
    else
        log_error "Script de limpeza não encontrado"
    fi
else
    log_info "Limpeza cancelada"
fi

# 6. Verificar SuperAdmin
echo ""
log_info "Verificando SuperAdmin..."
if [ -f "database/check_database_state.php" ] && command -v php &> /dev/null; then
    # Tentar verificar se tem SuperAdmin (assumindo output do script)
    SUPERADMIN_COUNT=$(php database/check_database_state.php 2>/dev/null | grep -o "SuperAdmin: [0-9]*" | grep -o "[0-9]*" || echo "0")
    
    if [ "$SUPERADMIN_COUNT" -eq 0 ]; then
        log_warning "Nenhum SuperAdmin encontrado"
        read -p "Deseja criar um SuperAdmin? (s/n) " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Ss]$ ]]; then
            if [ -f "database/create_superadmin.php" ]; then
                log_info "Criando SuperAdmin..."
                php database/create_superadmin.php
            fi
        fi
    else
        log_success "SuperAdmin encontrado ($SUPERADMIN_COUNT)"
    fi
else
    log_warning "Não foi possível verificar SuperAdmin"
fi

# 7. Resumo final
echo ""
echo -e "${CYAN}╔════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║  Setup Concluído!                                  ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════════════╝${NC}\n"

log_success "Próximos passos:"
echo ""
echo "  1. Testar endpoints:"
echo "     curl $API_URL/status"
echo ""
echo "  2. Fazer login:"
echo "     curl -X POST $API_URL/auth/login \\"
echo "       -H 'Content-Type: application/json' \\"
echo "       -d '{\"email\":\"admin@app.com\",\"password\":\"senha\"}'"
echo ""
echo "  3. Consultar documentação:"
echo "     → docs/LIMPEZA_BANCO_DADOS.md"
echo "     → docs/GUIA_MANUTENCAO.md"
echo "     → docs/API_QUICK_REFERENCE.md"
echo ""

log_success "Ambiente pronto para desenvolvimento!"
echo ""
