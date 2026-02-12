#!/bin/bash

# Script para testar o endpoint /mobile/assinaturas
# Criar um JWT token válido seria necessário primeiro

echo "🔍 Verificando se o arquivo AssinaturaController.php existe..."
if [ -f "app/Controllers/AssinaturaController.php" ]; then
    echo "✅ Arquivo encontrado"
    
    echo ""
    echo "📝 Procurando por 'minhasAssinaturas' método..."
    grep -n "function minhasAssinaturas" app/Controllers/AssinaturaController.php
    
    echo ""
    echo "📝 Procurando por SELECT statements na query..."
    grep -n "SELECT.*FROM assinaturas" app/Controllers/AssinaturaController.php | head -5
    
    echo ""
    echo "📝 Procurando por error_log statements..."
    grep -n "error_log" app/Controllers/AssinaturaController.php | grep minhasAssinaturas
    
    echo ""
    echo "📝 Procurando por json_encode na resposta..."
    grep -n "json_encode" app/Controllers/AssinaturaController.php | grep -A2 "minhasAssinaturas"
    
else
    echo "❌ Arquivo NÃO encontrado!"
fi

echo ""
echo "🔍 Verificando logs do PHP..."
if [ -f "logs/php_errors.log" ]; then
    echo "📋 Últimas 20 linhas dos logs:"
    tail -20 logs/php_errors.log
else
    echo "⚠️ Arquivo de log não encontrado em logs/php_errors.log"
fi
