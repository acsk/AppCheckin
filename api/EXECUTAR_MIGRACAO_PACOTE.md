# 🔧 Executar Migração: Adicionar Colunas de Pacote

## ✅ Opção 1: Via PHP (Recomendado)

```bash
cd /Users/andrecabral/Projetos/AppCheckin/api
php database/2026_02_19_add_pacote_columns.php
```

## ✅ Opção 2: Via Docker

Se estiver usando Docker:

```bash
docker exec {seu_container_php} php /app/database/2026_02_19_add_pacote_columns.php
```

## ✅ Opção 3: Manual via MySQL

Se tiver MySQL instalado:

```bash
mysql -h localhost -u seu_usuario -p < database/migrations/2026_02_19_add_pacote_columns.sql
```

## 📋 Colunas que serão Adicionadas/Verificadas

### Tabela: `matriculas`
- ✅ `pacote_contrato_id` (INT NULL) - Vínculo com pacote_contratos
- ✅ `valor_rateado` (DECIMAL 10,2 NULL) - Valor pago de forma rateada

### Tabela: `pagamentos_plano`
- ✅ `pacote_contrato_id` (INT NULL) - Rastreamento de pagamento do pacote

### Tabela: `pacote_beneficiarios`
- ✅ `matricula_id` (INT NULL) - Vínculo com matrícula criada
- ✅ `status` (VARCHAR 20) - Status do beneficiário (pendente/ativo)
- ✅ `valor_rateado` (DECIMAL 10,2 NULL) - Valor rateado efetivo

## 🔍 Verificação Pós-Migração

Após executar, verifique se as colunas foram criadas:

```sql
-- Verificar matriculas
DESC matriculas;
SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'matriculas' AND COLUMN_NAME IN ('pacote_contrato_id', 'valor_rateado');

-- Verificar pagamentos_plano
DESC pagamentos_plano;
SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'pagamentos_plano' AND COLUMN_NAME = 'pacote_contrato_id';

-- Verificar pacote_beneficiarios
DESC pacote_beneficiarios;
SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'pacote_beneficiarios' AND COLUMN_NAME IN ('matricula_id', 'status', 'valor_rateado');
```

## 📝 Status da Migração

- ✅ Arquivo SQL criado: `database/migrations/2026_02_19_add_pacote_columns.sql`
- ✅ Script PHP criado: `database/2026_02_19_add_pacote_columns.php`
- ⏳ **Pendente**: Executar a migração no seu ambiente

## 🚀 Próximo Passo

Execute a migração e confirme que todas as colunas foram adicionadas com sucesso!
