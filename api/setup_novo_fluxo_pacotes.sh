#!/bin/bash

# Script de Setup: Novo Fluxo de Webhooks para Pacotes
# =====================================================
# 
# Este script:
# 1. Verifica se a coluna pacote_contrato_id existe na tabela assinaturas
# 2. Se não existir, cria a migração
# 3. Valida o código alterado
# 4. Fornece instruções de deployment

set -e  # Exit on error

echo "🎁 Setup: Novo Fluxo de Webhooks para Pacotes"
echo "=============================================="
echo ""

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ============================================
# 1. Verificar arquivo de migração
# ============================================
echo -e "${BLUE}1️⃣  Verificando arquivo de migração...${NC}"

if [ ! -f "database/migrations/add_pacote_contrato_id_to_assinaturas.php" ]; then
    echo -e "${RED}❌ Arquivo de migração não encontrado!${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Arquivo de migração encontrado${NC}"
echo ""

# ============================================
# 2. Verificar Se PHP está disponível
# ============================================
echo -e "${BLUE}2️⃣  Verificando PHP...${NC}"

if ! command -v php &> /dev/null; then
    echo -e "${YELLOW}⚠️  PHP não encontrado. Você precisará executar a migração no servidor:${NC}"
    echo "   ssh user@server 'cd /home/u304177849/public_html/api && php database/migrations/add_pacote_contrato_id_to_assinaturas.php'"
    echo ""
else
    echo -e "${GREEN}✅ PHP encontrado${NC}"
    echo ""
    
    # ============================================
    # 3. Executar Migração
    # ============================================
    echo -e "${BLUE}3️⃣  Executando migração...${NC}"
    php database/migrations/add_pacote_contrato_id_to_assinaturas.php
    echo ""
fi

# ============================================
# 4. Verificar Alterações no Código
# ============================================
echo -e "${BLUE}4️⃣  Validando mudanças no código...${NC}"

echo "   Verificando se MercadoPagoWebhookController.php tem:"
echo ""

# Verificar novo método
if grep -q "private function criarMatriculaPagantePacote" app/Controllers/MercadoPagoWebhookController.php; then
    echo -e "${GREEN}   ✅ criarMatriculaPagantePacote()${NC}"
else
    echo -e "${RED}   ❌ criarMatriculaPagantePacote() NÃO ENCONTRADO${NC}"
fi

if grep -q "private function processarPagamentoPacote" app/Controllers/MercadoPagoWebhookController.php; then
    echo -e "${GREEN}   ✅ processarPagamentoPacote()${NC}"
else
    echo -e "${RED}   ❌ processarPagamentoPacote() NÃO ENCONTRADO${NC}"
fi

if grep -q "pacote_contrato_id" app/Controllers/MercadoPagoWebhookController.php; then
    echo -e "${GREEN}   ✅ Referências a pacote_contrato_id${NC}"
else
    echo -e "${RED}   ❌ Referências a pacote_contrato_id NÃO ENCONTRADAS${NC}"
fi

echo ""

# ============================================
# 5. Status do Git
# ============================================
echo -e "${BLUE}5️⃣  Status das alterações no Git...${NC}"

if [ -d ".git" ]; then
    echo ""
    echo "Arquivos modificados:"
    git status --short | grep -E "app/Controllers/MercadoPagoWebhookController|docs/NOVO_FLUXO|database/migrations/add_pacote" || echo "   (nenhum arquivo encontrado no git)"
    echo ""
else
    echo -e "${YELLOW}⚠️  Repositório Git não encontrado${NC}"
fi

# ============================================
# 6. Instruções de Deployment
# ============================================
echo -e "${BLUE}6️⃣  Instruções de Deployment${NC}"
echo ""
echo -e "${YELLOW}ANTES DE FAZER DEPLOY:${NC}"
echo ""
echo "1️⃣  Executar migração no servidor:"
echo "   ${YELLOW}ssh user@server${NC}"
echo "   ${YELLOW}cd /home/u304177849/public_html/api${NC}"
echo "   ${YELLOW}php database/migrations/add_pacote_contrato_id_to_assinaturas.php${NC}"
echo ""

echo "2️⃣  Fazer commit das mudanças:"
echo "   ${YELLOW}git add app/Controllers/MercadoPagoWebhookController.php${NC}"
echo "   ${YELLOW}git add docs/NOVO_FLUXO_PACOTES_WEBHOOKS.md${NC}"
echo "   ${YELLOW}git add database/migrations/add_pacote_contrato_id_to_assinaturas.php${NC}"
echo "   ${YELLOW}git commit -m 'feat: novo fluxo de webhooks para pacotes (2-step)'${NC}"
echo ""

echo "3️⃣  Fazer push:"
echo "   ${YELLOW}git push origin main${NC}"
echo ""

echo "4️⃣  Reiniciar PHP-FPM no servidor:"
echo "   ${YELLOW}sudo systemctl restart php8.2-fpm${NC}"
echo "   ${YELLOW}ou${NC}"
echo "   ${YELLOW}sudo systemctl restart php-fpm${NC}"
echo ""

echo "5️⃣  Testar com curl:"
echo "   ${YELLOW}curl -X POST https://api.appcheckin.com.br/api/webhooks/mercadopago \\${NC}"
echo "   ${YELLOW}-H 'Content-Type: application/json' \\${NC}"
echo "   ${YELLOW}-d '{\"type\": \"subscription_preapproval\", \"data\": {\"id\": \"test\"}}\'${NC}"
echo ""

# ============================================
# 7. Checklist
# ============================================
echo -e "${BLUE}7️⃣  Checklist antes de usar em produção:${NC}"
echo ""
echo "  [ ] Migração executada no servidor"
echo "  [ ] Código enviado via git push"
echo "  [ ] PHP-FPM reiniciado"
echo "  [ ] Coluna pacote_contrato_id verificada:"
echo "      ${YELLOW}DESC assinaturas;${NC}"
echo "  [ ] Novo método criarMatriculaPagantePacote() funciona"
echo "  [ ] Novo método processarPagamentoPacote() funciona"
echo "  [ ] Teste com pagamento real (ou sandbox MP)"
echo "  [ ] Matrículas do pagante + beneficiários criadas corretamente"
echo "  [ ] Pagamentos marcados como 'pago'"
echo "  [ ] Contrato marcado como 'ativo'"
echo ""

echo -e "${GREEN}✅ Setup validado! Você está pronto para o novo fluxo.${NC}"
echo ""
echo "📚 Leia mais em: docs/NOVO_FLUXO_PACOTES_WEBHOOKS.md"
