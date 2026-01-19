<?php
/**
 * AppCheckin API - Index Redirect
 * Redireciona requisições para a pasta public/
 */

// Se a requisição não for para arquivos estáticos, redireciona para public/
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']);

// Arquivos que devem ser servidos diretamente (estáticos)
$staticExtensions = ['.css', '.js', '.jpg', '.jpeg', '.png', '.gif', '.svg', '.woff', '.woff2', '.ttf', '.eot'];
$isStatic = false;

foreach ($staticExtensions as $ext) {
    if (substr($requestUri, -strlen($ext)) === $ext) {
        $isStatic = true;
        break;
    }
}

// Se for requisição para /public/algo, remove o /public do caminho
if (strpos($requestUri, '/public/') === 0) {
    $requestUri = substr($requestUri, 7);
}

// Se não for estático, redireciona para public/index.php
if (!$isStatic) {
    $_SERVER['SCRIPT_NAME'] = '/public/index.php';
    require __DIR__ . '/public/index.php';
} else {
    // Para arquivos estáticos, tenta servir da pasta public/
    $file = __DIR__ . '/public' . $requestUri;
    if (file_exists($file)) {
        // Define o mime type correto
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
        
        header('Content-Type: ' . $mimeType);
        readfile($file);
        exit;
    } else {
        header('HTTP/1.0 404 Not Found');
        exit('File not found');
    }
}
