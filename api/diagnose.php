<?php
/**
 * DIAGNOSE.PHP - Ferramenta de diagnóstico para AppCheckin API
 * Acesse: https://api.appcheckin.com.br/diagnose.php
 */

// Desabilitar tudo, só mostrar erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>AppCheckin API - Diagnóstico</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; margin: 10px 0; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; }
        .error { border-left-color: #dc3545; color: #dc3545; }
        .success { border-left-color: #28a745; color: #28a745; }
        .warning { border-left-color: #ffc107; color: #333; }
        h2 { margin-top: 0; }
        pre { background: #f9f9f9; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>

<h1>AppCheckin API - Diagnóstico</h1>
<p>Gerado em: <?php echo date('Y-m-d H:i:s'); ?></p>

<?php
// ============================================
// 1. TESTE PHP BÁSICO
// ============================================
?>
<div class="section success">
    <h2>✓ PHP Funcional</h2>
    <p>Versão: <?php echo phpversion(); ?></p>
</div>

<?php
// ============================================
// 2. VERIFICAR ARQUIVOS
// ============================================
$files = [
    'vendor/autoload.php' => __DIR__ . '/vendor/autoload.php',
    '.env' => __DIR__ . '/.env',
    'public/index.php' => __DIR__ . '/public/index.php',
    '.htaccess' => __DIR__ . '/.htaccess'
];

echo '<div class="section"><h2>Arquivos</h2>';
foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $class = $exists ? 'success' : 'error';
    echo "<div class='$class'>";
    echo $exists ? '✓' : '✗';
    echo " $name: " . ($exists ? 'OK' : 'FALTANDO') . "</div>";
}
echo '</div>';

?>

<?php
// ============================================
// 3. CARREGAR AUTOLOAD
// ============================================
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    try {
        require $autoloadPath;
        echo '<div class="section success"><h2>✓ Autoload Carregado</h2></div>';
    } catch (\Exception $e) {
        echo '<div class="section error"><h2>✗ Erro ao carregar Autoload</h2>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '</div>';
    }
} else {
    echo '<div class="section error"><h2>✗ Autoload não encontrado</h2></div>';
}

?>

<?php
// ============================================
// 4. CARREGAR .ENV
// ============================================
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        echo '<div class="section success"><h2>✓ .ENV Carregado</h2></div>';
    } catch (\Exception $e) {
        echo '<div class="section error"><h2>✗ Erro ao carregar .ENV</h2>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '</div>';
    }
} else {
    echo '<div class="section error"><h2>✗ .ENV não encontrado</h2></div>';
}

?>

<?php
// ============================================
// 5. CRIAR SLIM APP
// ============================================
try {
    $app = \Slim\Factory\AppFactory::create();
    echo '<div class="section success"><h2>✓ Slim App Criado</h2></div>';
} catch (\Exception $e) {
    echo '<div class="section error"><h2>✗ Erro ao criar Slim App</h2>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '</div>';
}

?>

<?php
// ============================================
// 6. INFORMAÇÕES EXTRAS
// ============================================
?>
<div class="section">
    <h2>Informações do Servidor</h2>
    <p><strong>Método HTTP:</strong> <?php echo $_SERVER['REQUEST_METHOD']; ?></p>
    <p><strong>URI:</strong> <?php echo $_SERVER['REQUEST_URI']; ?></p>
    <p><strong>PHP SAPI:</strong> <?php echo php_sapi_name(); ?></p>
    <p><strong>Sistema de Arquivos:</strong> <?php echo PHP_OS; ?></p>
</div>

<?php
// ============================================
// 7. .HTACCESS
// ============================================
$htaccessPath = __DIR__ . '/.htaccess';
if (file_exists($htaccessPath)) {
    echo '<div class="section"><h2>.HTACCESS Conteúdo</h2>';
    echo '<pre>' . htmlspecialchars(file_get_contents($htaccessPath)) . '</pre>';
    echo '</div>';
}

?>

</body>
</html>
