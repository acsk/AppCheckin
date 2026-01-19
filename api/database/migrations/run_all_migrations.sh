#!/bin/bash

# =====================================================
# SCRIPT: Executar Todas as Migrations em Ordem
# =====================================================
# Este script executa as migrations de forma sequencial
# e garante a ordem correta de execução
# =====================================================

set -e

# Configuração de cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Variáveis de conexão
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3307}"  # Porta padrão do docker-compose
DB_NAME="${DB_NAME:-appcheckin}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-root}"

# Função para imprimir títulos
print_title() {
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo ""
}

# Função para executar migration
run_migration() {
    local migration_file=$1
    local migration_num=$(echo "$migration_file" | grep -oE '^[0-9]+' | head -1)
    
    if [ -z "$migration_num" ]; then
        return
    fi
    
    echo -e "${YELLOW}▶️  Executando: $migration_file${NC}"
    
    if mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$migration_file" 2>&1; then
        echo -e "${GREEN}✅ OK: $migration_file${NC}"
    else
        echo -e "${RED}❌ ERRO em: $migration_file${NC}"
        return 1
    fi
}

print_title "MIGRATIONS - AppCheckin"

echo -e "${YELLOW}Banco: $DB_NAME @ $DB_HOST:$DB_PORT${NC}"
echo ""

# Testar conexão
echo -e "${YELLOW}🔌 Testando conexão com banco...${NC}"
if ! mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1" > /dev/null 2>&1; then
    echo -e "${RED}❌ Erro de conexão com banco de dados!${NC}"
    echo "Verifique:"
    echo "  - MySQL está rodando?"
    echo "  - Credenciais corretas?"
    echo "  - Banco '$DB_NAME' existe?"
    exit 1
fi
echo -e "${GREEN}✅ Conexão OK${NC}"
echo ""

# Criar tabela de tracking
echo -e "${YELLOW}📝 Criando tabela de tracking de migrations...${NC}"
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
CREATE TABLE IF NOT EXISTS _migration_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) UNIQUE NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_migration_name (migration_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF
echo -e "${GREEN}✅ Tabela de tracking criada${NC}"
echo ""

# Lista de migrações em ordem
MIGRATIONS=(
    "001_create_tables.sql"
    "002_adjust_horarios_for_classes.sql"
    "003_add_tolerancia_antes.sql"
    "004_add_tenancy.sql"
    "005_add_foto_usuarios.sql"
    "006_add_role_usuarios.sql"
    "007_create_planos_table.sql"
    "008_refactor_roles_table.sql"
    "009_create_planejamento_semanal.sql"
    "010_add_registrado_por_admin.sql"
    "010_create_historico_planos.sql"
    "011_create_contas_receber.sql"
    "012_add_max_alunos_planos.sql"
    "012_create_matriculas.sql"
    "013_create_auxiliar_tables.sql"
    "014_multi_tenant_usuarios.sql"
    "015_reset_hierarquia.sql"
    "016_add_plano_to_tenants.sql"
    "017_add_ativo_to_usuarios.sql"
    "018_add_cnpj_and_address_to_tenants.sql"
    "019_create_tenant_planos.sql"
    "020_add_atual_to_planos.sql"
    "021_create_planos_sistema.sql"
    "022_rename_tenant_planos_to_tenant_planos_sistema.sql"
    "024_add_unique_constraint_tenant_plano_ativo.sql"
    "025_fix_plano_id_foreign_key.sql"
    "026_remove_plano_id_from_tenants.sql"
    "027_add_responsavel_to_tenants.sql"
    "028_add_address_cpf_to_usuarios.sql"
    "028_create_status_contrato_table.sql"
    "029_migrate_status_to_status_id.sql"
    "030_fix_status_migration.sql"
    "031_create_status_pagamento_table.sql"
    "032_add_bloqueado_status.sql"
    "033_create_pagamentos_contrato_table.sql"
    "034_create_forma_pagamento_table.sql"
    "035_remove_colunas_contrato.sql"
    "036_fix_pagamentos_forma_pagamento.sql"
    "036_remove_plano_from_usuarios.sql"
    "037_create_status_tables.sql"
    "037_fix_forma_pagamento_charset.sql"
    "038_add_status_id_columns.sql"
    "038_fix_forma_pagamento_data_encoding.sql"
    "039_create_modalidades_table.sql"
    "039_remove_enum_columns.sql"
    "040_add_modalidade_to_planos.sql"
    "040_fix_checkin_constraint.sql"
    "041_rename_contrato_id.sql"
    "041_unify_forma_pagamento_tables.sql"
    "042_create_tenant_formas_pagamento.sql"
    "042_padronizar_collation.sql"
    "043_adicionar_constraints_unicidade.sql"
    "043_create_feature_flags.sql"
    "044_otimizar_indices_tenant_first.sql"
    "044b_checkins_tenant_progressivo.sql"
    "045_planos_checkins_semanais.sql"
    "046_remove_valor_mensalidade_modalidades.sql"
    "047_remove_legacy_fields_planos.sql"
    "050_create_pagamentos_plano.sql"
    "051_create_tipos_baixa_table.sql"
    "052_add_tipo_baixa_to_pagamentos_plano.sql"
    "053_add_dias_tolerancia_status_pagamento.sql"
    "054_create_professores_table.sql"
    "055_create_turmas_table.sql"
    "056_remove_tenant_id_from_dias.sql"
    "057_create_inscricoes_turmas_table.sql"
    "058_ajustar_checkins_constraint_turma_id.sql"
    "059_documentar_constraint_matriculas_unica_ativa.sql"
    "060_create_wods_table.sql"
    "061_create_wod_blocos_table.sql"
    "062_create_wod_variacoes_table.sql"
    "063_create_wod_resultados_table.sql"
    "064_add_modalidade_id_to_wods.sql"
    "065_fix_wods_unique_constraint.sql"
)

echo -e "${BLUE}📋 EXECUTANDO MIGRATIONS${NC}"
echo ""

TOTAL=${#MIGRATIONS[@]}
COUNT=0
FAILED=0

for migration in "${MIGRATIONS[@]}"; do
    COUNT=$((COUNT + 1))
    echo -ne "${YELLOW}[$COUNT/$TOTAL]${NC} "
    
    if [ -f "$migration" ]; then
        if run_migration "$migration"; then
            echo ""
        else
            echo ""
            FAILED=$((FAILED + 1))
        fi
    else
        echo -e "⏭️  PULANDO: $migration (não encontrado)"
    fi
done

echo ""
print_title "RESUMO DAS MIGRATIONS"

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ TODAS as migrations executadas com sucesso!${NC}"
    echo ""
    echo -e "${GREEN}📊 Resumo:${NC}"
    echo "   Total de migrations: $TOTAL"
    echo "   Executadas: $TOTAL"
    echo "   Falhadas: 0"
else
    echo -e "${RED}❌ $FAILED migration(s) falharam${NC}"
    echo ""
    echo -e "${YELLOW}⚠️  Verifique os erros acima e tente novamente${NC}"
    exit 1
fi

echo ""
print_title "VERIFICAÇÃO FINAL"

# Verificar tabelas críticas
TABLES_TO_CHECK=(
    "status_matricula"
    "status_pagamento"
    "status_checkin"
    "status_usuario"
    "status_contrato"
    "matriculas"
    "checkins"
    "pagamentos_plano"
)

echo -e "${YELLOW}Verificando tabelas críticas:${NC}"
echo ""

ALL_OK=true
for table in "${TABLES_TO_CHECK[@]}"; do
    if mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLES LIKE '$table'" 2>/dev/null | grep -q "$table"; then
        echo -e "${GREEN}✅ $table${NC}"
    else
        echo -e "${RED}❌ $table NÃO ENCONTRADA${NC}"
        ALL_OK=false
    fi
done

echo ""

if [ "$ALL_OK" = true ]; then
    echo -e "${GREEN}✅ TODAS as tabelas existem!${NC}"
    echo ""
    echo -e "${GREEN}🎉 Banco de dados está pronto para uso!${NC}"
    exit 0
else
    echo -e "${RED}❌ ERRO: Algumas tabelas estão faltando${NC}"
    exit 1
fi
