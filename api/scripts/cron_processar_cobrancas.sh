#!/bin/bash
# =========================================================
# CRON para processar início de cobranças
# Executar diariamente às 00:05
# Crontab: 5 0 * * * /path/to/cron_processar_cobrancas.sh
# =========================================================

LOG_FILE="/var/log/appcheckin_cobrancas.log"
API_URL="http://localhost:8080"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Iniciando processamento de cobranças..." >> "$LOG_FILE"

# TODO: Adicionar token de autenticação do sistema
# Para cada tenant (aqui exemplo com tenant_id = 1)
response=$(curl -s -X POST "${API_URL}/admin/matriculas/processar-cobranca" \
  -H "Content-Type: application/json" \
  2>&1)

if [ $? -eq 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Sucesso: $response" >> "$LOG_FILE"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Erro: $response" >> "$LOG_FILE"
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Processamento finalizado." >> "$LOG_FILE"
echo "---" >> "$LOG_FILE"
