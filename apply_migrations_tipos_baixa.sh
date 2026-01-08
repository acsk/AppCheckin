#!/bin/bash

# Script para aplicar as migrations de tipos de baixa

echo "========================================="
echo "  Aplicando Migrations de Tipos de Baixa"
echo "========================================="
echo ""

# Diretório do backend
BACKEND_DIR="/Users/andrecabral/Projetos/AppCheckin/Backend"
MIGRATIONS_DIR="$BACKEND_DIR/database/migrations"

# Pedir credenciais do banco
read -p "Host do banco de dados [localhost]: " DB_HOST
DB_HOST=${DB_HOST:-localhost}

read -p "Porta do banco de dados [3306]: " DB_PORT
DB_PORT=${DB_PORT:-3306}

read -p "Nome do banco de dados: " DB_NAME

read -p "Usuário do banco de dados: " DB_USER

read -sp "Senha do banco de dados: " DB_PASS
echo ""
echo ""

# Verificar conexão
echo "Testando conexão com o banco de dados..."
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1;" > /dev/null 2>&1

if [ $? -ne 0 ]; then
    echo "❌ Erro: Não foi possível conectar ao banco de dados."
    exit 1
fi

echo "✅ Conexão estabelecida com sucesso!"
echo ""

# Aplicar migration 051 - Criar tabela tipos_baixa
echo "Aplicando migration 051_create_tipos_baixa_table.sql..."
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$MIGRATIONS_DIR/051_create_tipos_baixa_table.sql"

if [ $? -eq 0 ]; then
    echo "✅ Migration 051 aplicada com sucesso!"
else
    echo "❌ Erro ao aplicar migration 051"
    exit 1
fi

echo ""

# Aplicar migration 052 - Adicionar tipo_baixa_id em pagamentos_plano
echo "Aplicando migration 052_add_tipo_baixa_to_pagamentos_plano.sql..."
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$MIGRATIONS_DIR/052_add_tipo_baixa_to_pagamentos_plano.sql"

if [ $? -eq 0 ]; then
    echo "✅ Migration 052 aplicada com sucesso!"
else
    echo "❌ Erro ao aplicar migration 052"
    exit 1
fi

echo ""
echo "========================================="
echo "  Migrations aplicadas com sucesso! ✅"
echo "========================================="
echo ""
echo "Tabela 'tipos_baixa' criada com os seguintes registros:"
echo "  1 - Manual"
echo "  2 - Automática"
echo "  3 - Importação"
echo "  4 - Integração"
echo ""
echo "Campo 'tipo_baixa_id' adicionado à tabela 'pagamentos_plano'"
echo ""
