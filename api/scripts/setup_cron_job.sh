#!/bin/bash

# Script para configurar o CRON job de baixa de pagamentos
# Uso: ./setup_cron_job.sh

echo "=================================================="
echo "   Setup CRON Job - Baixar Pagamentos Valor Zero"
echo "=================================================="
echo ""

# Detectar caminho do PHP
PHP_PATH=$(which php)
if [ -z "$PHP_PATH" ]; then
    echo "❌ Erro: PHP não encontrado no PATH"
    echo "   Instale PHP ou adicione ao PATH"
    exit 1
fi

echo "✅ PHP encontrado: $PHP_PATH"
echo ""

# Obter caminho do script
SCRIPT_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/jobs/BaixarPagamentosValorZero.php"

if [ ! -f "$SCRIPT_PATH" ]; then
    echo "❌ Erro: Script não encontrado em $SCRIPT_PATH"
    exit 1
fi

echo "✅ Script encontrado: $SCRIPT_PATH"
echo ""

# Criar diretório de logs
LOG_DIR="/var/log/appcheckin"
if [ ! -d "$LOG_DIR" ]; then
    echo "📁 Criando diretório de logs: $LOG_DIR"
    sudo mkdir -p "$LOG_DIR" 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "✅ Diretório criado com sucesso"
    else
        echo "⚠️  Não foi possível criar diretório. Usando /tmp para logs"
        LOG_DIR="/tmp"
    fi
else
    echo "✅ Diretório de logs já existe: $LOG_DIR"
fi

LOG_FILE="$LOG_DIR/jobs.log"

echo ""
echo "=================================================="
echo "   Selecione a frequência desejada:"
echo "=================================================="
echo ""
echo "1) Mensal (1º dia do mês às 03:00 AM) - RECOMENDADO"
echo "2) Semanal (todas as segundas às 03:00 AM)"
echo "3) Diário (todos os dias às 03:00 AM)"
echo "4) Customizado (você digita a regra)"
echo ""

read -p "Escolha uma opção [1-4]: " opcao

case $opcao in
    1)
        CRON_RULE="0 3 1 * * $PHP_PATH $SCRIPT_PATH >> $LOG_FILE 2>&1"
        DESCRICAO="Mensal (1º dia do mês às 03:00 AM)"
        ;;
    2)
        CRON_RULE="0 3 * * 1 $PHP_PATH $SCRIPT_PATH >> $LOG_FILE 2>&1"
        DESCRICAO="Semanal (todas as segundas-feiras às 03:00 AM)"
        ;;
    3)
        CRON_RULE="0 3 * * * $PHP_PATH $SCRIPT_PATH >> $LOG_FILE 2>&1"
        DESCRICAO="Diário (todos os dias às 03:00 AM)"
        ;;
    4)
        read -p "Digite a regra CRON (ex: 0 3 1 * *): " regra
        CRON_RULE="$regra $PHP_PATH $SCRIPT_PATH >> $LOG_FILE 2>&1"
        DESCRICAO="Customizado: $regra"
        ;;
    *)
        echo "❌ Opção inválida"
        exit 1
        ;;
esac

echo ""
echo "=================================================="
echo "   Confirmação"
echo "=================================================="
echo ""
echo "Frequência: $DESCRICAO"
echo "Script: $SCRIPT_PATH"
echo "Log: $LOG_FILE"
echo ""
echo "Regra CRON:"
echo "$CRON_RULE"
echo ""

read -p "Deseja prosseguir? (s/n): " confirmacao

if [[ $confirmacao != "s" && $confirmacao != "S" ]]; then
    echo "Operação cancelada"
    exit 0
fi

# Adicionar ao crontab
echo ""
echo "Adicionando job ao crontab..."

# Verificar se já existe
TEMP_CRON=$(mktemp)
crontab -l > "$TEMP_CRON" 2>/dev/null || true

# Verificar se a regra já existe
if grep -q "BaixarPagamentosValorZero" "$TEMP_CRON"; then
    echo "⚠️  Aviso: Uma regra para este job já existe no crontab"
    read -p "Deseja substituir? (s/n): " substituir
    
    if [[ $substituir != "s" && $substituir != "S" ]]; then
        rm "$TEMP_CRON"
        echo "Operação cancelada"
        exit 0
    fi
    
    # Remover linha antiga
    grep -v "BaixarPagamentosValorZero" "$TEMP_CRON" > "${TEMP_CRON}.new"
    mv "${TEMP_CRON}.new" "$TEMP_CRON"
fi

# Adicionar nova regra
echo "$CRON_RULE" >> "$TEMP_CRON"

# Instalar novo crontab
crontab "$TEMP_CRON"

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Job adicionado ao crontab com sucesso!"
    echo ""
    echo "Você pode verificar com:"
    echo "  crontab -l"
    echo ""
    echo "E monitorar os logs com:"
    echo "  tail -f $LOG_FILE"
else
    echo "❌ Erro ao adicionar job ao crontab"
    exit 1
fi

rm "$TEMP_CRON"

echo ""
echo "=================================================="
echo "   Setup Concluído!"
echo "=================================================="
