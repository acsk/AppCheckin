#!/bin/bash

# Script para executar o seed de dias dentro do Docker

set -e

echo "🐳 Verificando Docker..."

# Verificar se Docker está rodando
if ! docker ps &> /dev/null; then
    echo "❌ Docker não está rodando!"
    echo ""
    echo "Para macOS: Abra Docker Desktop (Applications > Docker)"
    echo "Ou execute: open -a Docker"
    echo ""
    exit 1
fi

echo "✅ Docker está rodando"
echo ""

# Verificar se docker-compose está disponível
if ! command -v docker-compose &> /dev/null; then
    echo "❌ docker-compose não encontrado!"
    exit 1
fi

echo "🚀 Iniciando containers..."
docker-compose up -d

echo ""
echo "⏳ Aguardando banco de dados ficar pronto..."
sleep 5

echo ""
echo "🌱 Executando seed de dias..."
docker-compose exec -T app php Backend/jobs/gerar_dias_anuais.php

echo ""
echo "📊 Verificando status..."
docker-compose exec -T app php Backend/jobs/gerar_dias_anuais.php --status

echo ""
echo "✅ Seed executado com sucesso!"
