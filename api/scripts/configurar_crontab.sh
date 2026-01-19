#!/bin/bash

# Script para configurar o job de limpeza de matrículas no crontab

echo "=== CONFIGURAR JOB: Limpeza de Matrículas Duplicadas ==="
echo ""

# Verificar se já existe
if crontab -l 2>/dev/null | grep -q "limpar_matriculas_duplicadas"; then
    echo "⚠️  Job já existe no crontab"
    echo ""
    echo "Cron atual:"
    crontab -l | grep "limpar_matriculas_duplicadas"
    echo ""
else
    echo "Adicionando job ao crontab..."
    echo ""
    
    # Criar arquivo de log
    mkdir -p /var/log/appcheck/
    touch /var/log/appcheck/limpar_matriculas.log
    
    # Adicionar ao crontab
    (crontab -l 2>/dev/null; echo "# Job: Limpeza de Matrículas Duplicadas - Executar diariamente às 5:00") | crontab -
    (crontab -l 2>/dev/null; echo "0 5 * * * docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php >> /var/log/appcheck/limpar_matriculas.log 2>&1") | crontab -
    
    echo "✅ Job adicionado ao crontab com sucesso!"
    echo ""
    echo "Configuração:"
    echo "  • Execução: Diariamente às 05:00"
    echo "  • Comando: docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php"
    echo "  • Log: /var/log/appcheck/limpar_matriculas.log"
    echo ""
    echo "Cron atual:"
    crontab -l | grep "limpar_matriculas_duplicadas"
fi

echo ""
echo "=== TESTE DO JOB ==="
echo ""
echo "Para testar o job em modo dry-run (sem fazer alterações):"
echo "  docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php --dry-run"
echo ""
echo "Para executar de verdade:"
echo "  docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php"
echo ""
echo "Para ver logs:"
echo "  tail -f /var/log/appcheck/limpar_matriculas.log"
