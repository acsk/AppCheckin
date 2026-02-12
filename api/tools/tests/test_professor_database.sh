#!/bin/bash

# Teste DIRETO NO BANCO - Verificar refatoração
echo "================================================"
echo "🔍 TESTE DIRETO NO BANCO DE DADOS"
echo "================================================"
echo ""

# [1] Verificar estrutura da tabela professores
echo "📋 [1/5] Estrutura da tabela 'professores'..."
docker exec appcheckin_mysql mysql -u root -proot -D appcheckin -e \
    "DESCRIBE professores;" 2>/dev/null
echo ""

# [2] Verificar se tabela tenant_professor ainda existe
echo "🔍 [2/5] Verificando se 'tenant_professor' existe..."
TABLE_CHECK=$(docker exec appcheckin_mysql mysql -u root -proot -D appcheckin -e \
    "SHOW TABLES LIKE 'tenant_professor';" 2>/dev/null | tail -n +2)

if [ -n "$TABLE_CHECK" ]; then
    echo "⚠️  Tabela 'tenant_professor' AINDA EXISTE"
    echo "Contando registros..."
    docker exec appcheckin_mysql mysql -u root -proot -D appcheckin -e \
        "SELECT COUNT(*) as total FROM tenant_professor;" 2>/dev/null
else
    echo "✅ Tabela 'tenant_professor' NÃO EXISTE (arquitetura limpa)"
fi
echo ""

# [3] Verificar tenant_usuario_papel para professores (papel_id=2)
echo "👥 [3/5] Professores em 'tenant_usuario_papel' (papel_id=2)..."
docker exec appcheckin_mysql mysql -u root -proot -D appcheckin -e \
    "SELECT COUNT(*) as total_professores FROM tenant_usuario_papel WHERE papel_id = 2;" 2>/dev/null
echo ""

echo "Detalhes dos vínculos:"
docker exec appcheckin_mysql mysql -u root -proot -D appcheckin -e \
    "SELECT tup.id, tup.tenant_id, tup.usuario_id, tup.papel_id, tup.ativo,
            p.id as professor_id, p.nome as professor_nome, p.cpf, p.email
     FROM tenant_usuario_papel tup
     INNER JOIN professores p ON p.usuario_id = tup.usuario_id
     WHERE tup.papel_id = 2
     LIMIT 5;" 2>/dev/null
echo ""

# [4] Testar query que a API usa
echo "🔍 [4/5] Testando query da API (listarPorTenant)..."
echo "Query:"
echo "SELECT p.id, p.nome, p.cpf, p.email, p.ativo, p.usuario_id,"
echo "       tup.ativo as vinculo_ativo"
echo "FROM professores p"
echo "INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id"
echo "    AND tup.tenant_id = 1"
echo "    AND tup.papel_id = 2"
echo ""

docker exec appcheckin_mysql mysql -u root -proot -D appcheckin -e \
    "SELECT p.id, p.nome, p.cpf, p.email, p.ativo, p.usuario_id, 
            tup.ativo as vinculo_ativo
     FROM professores p 
     INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id
        AND tup.tenant_id = 1
        AND tup.papel_id = 2
     LIMIT 5;" 2>/dev/null
echo ""

# [5] Comparação arquitetura
echo "📊 [5/5] COMPARAÇÃO DE ARQUITETURA"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

echo "❌ ARQUITETURA ANTIGA (redundante):"
echo "   professores → tenant_professor → tenant"
echo "   professores → usuarios → tenant_usuario_papel → tenant"
echo ""

echo "✅ ARQUITETURA NOVA (simplificada):"
echo "   professores → usuarios → tenant_usuario_papel (papel_id=2) → tenant"
echo ""

echo "🔑 CHAVE DE LIGAÇÃO:"
echo "   professores.usuario_id = tenant_usuario_papel.usuario_id"
echo "   tenant_usuario_papel.papel_id = 2 (professor)"
echo ""

# Verificar se o JOIN funciona
TOTAL_PROFS=$(docker exec appcheckin_mysql mysql -u root -proot -D appcheckin -e \
    "SELECT COUNT(*) FROM professores p 
     INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id
     WHERE tup.papel_id = 2;" 2>/dev/null | tail -n 1)

echo "📈 RESULTADO:"
echo "   Total de professores vinculados via tenant_usuario_papel: $TOTAL_PROFS"
echo ""

if [ "$TOTAL_PROFS" -gt 0 ]; then
    echo "✅ JOIN funcionando corretamente!"
    echo "✅ Arquitetura simplificada implementada com sucesso!"
else
    echo "⚠️  Nenhum professor encontrado com papel_id=2"
    echo "   Possíveis causas:"
    echo "   - Migration ainda não executada"
    echo "   - Dados ainda não migrados para tenant_usuario_papel"
fi

echo ""
echo "================================================"
echo "✅ ANÁLISE DO BANCO CONCLUÍDA"
echo "================================================"
