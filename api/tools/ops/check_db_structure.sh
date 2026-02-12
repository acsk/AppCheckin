#!/bin/bash
# Script para checar a estrutura da tabela assinaturas no servidor

echo "🔍 Procurando pela configuração do banco de dados..."
echo ""

# Encontrar arquivo de configuração
CONFIG_FILE=$(find /var/www/html -name "database.php" 2>/dev/null | head -1)

if [ ! -f "$CONFIG_FILE" ]; then
    echo "❌ Arquivo database.php não encontrado"
    exit 1
fi

echo "📄 Encontrado: $CONFIG_FILE"
echo ""

# Extrair credenciais do arquivo (aproximadamente)
echo "📋 Dados de conexão (do arquivo config):"
grep -E "DB_HOST|DB_NAME|DB_USER|MYSQL_HOST|MYSQL_DATABASE|MYSQL_USER" "$CONFIG_FILE" | head -5

echo ""
echo "🗂️ Estrutura da tabela assinaturas:"

# Executar query para ver estrutura
php -r "
try {
    if (file_exists('/var/www/html/.env')) {
        require '/var/www/html/vendor/autoload.php';
        (new \Dotenv\Dotenv('/var/www/html'))->load();
    }
    
    \$db = require '/var/www/html/config/database.php';
    
    \$result = \$db->query('DESCRIBE assinaturas');
    echo '📊 Colunas em assinaturas:', PHP_EOL;
    while (\$row = \$result->fetch()) {
        echo '  - ' . \$row['Field'] . ' (' . \$row['Type'] . ')' . PHP_EOL;
    }
    
} catch (Exception \$e) {
    echo '❌ Erro: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
" 2>&1

echo ""
echo "🧪 Teste de query:"
php -r "
try {
    if (file_exists('/var/www/html/.env')) {
        require '/var/www/html/vendor/autoload.php';
        \$Dotenv = new \Dotenv\Dotenv('/var/www/html');
        \$Dotenv->load();
    }
    
    \$db = require '/var/www/html/config/database.php';
    
    \$sql = 'SELECT 
        a.id,
        a.status_id,
        a.valor,
        a.data_inicio,
        a.proxima_cobranca,
        a.ultima_cobranca,
        a.gateway_assinatura_id as mp_preapproval_id,
        a.frequencia_id,
        a.gateway_id,
        a.matricula_id,
        a.plano_id,
        s.codigo as status_codigo,
        s.nome as status_nome,
        s.cor as status_cor,
        f.nome as ciclo_nome,
        f.meses as ciclo_meses,
        g.nome as gateway_nome,
        p.nome as plano_nome,
        mo.nome as modalidade_nome
    FROM assinaturas a
    LEFT JOIN assinatura_status s ON s.id = a.status_id
    LEFT JOIN assinatura_frequencias f ON f.id = a.frequencia_id
    LEFT JOIN assinatura_gateways g ON g.id = a.gateway_id
    LEFT JOIN matriculas m ON m.id = a.matricula_id
    LEFT JOIN planos p ON p.id = m.plano_id
    LEFT JOIN modalidades mo ON mo.id = p.modalidade_id
    WHERE a.aluno_id = 1 AND a.tenant_id = 2
    LIMIT 1';
    
    \$stmt = \$db->prepare(\$sql);
    \$stmt->execute();
    \$result = \$stmt->fetch();
    
    if (\$result) {
        echo '✅ Query bem-sucedida!' . PHP_EOL;
        echo '📝 Resultado: ' . json_encode(\$result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        echo '⚠️ Nenhuma assinatura encontrada para aluno 1, tenant 2' . PHP_EOL;
    }
    
} catch (Exception \$e) {
    echo '❌ Erro na query: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
" 2>&1
