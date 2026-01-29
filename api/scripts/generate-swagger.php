#!/usr/bin/env php
<?php
/**
 * Script para gerar a documentação OpenAPI a partir das anotações
 * 
 * Uso:
 *   php scripts/generate-swagger.php
 * 
 * Ou via Docker:
 *   docker compose exec php php scripts/generate-swagger.php
 */

require __DIR__ . '/../vendor/autoload.php';

use OpenApi\Generator;
use OpenApi\SourceFinder;
use OpenApi\Analysers\AttributeAnnotationFactory;
use OpenApi\Analysers\DocBlockAnnotationFactory;
use OpenApi\Analysers\ReflectionAnalyser;

$outputDir = __DIR__ . '/../public/swagger';
$outputFile = $outputDir . '/openapi.json';

// Diretórios para escanear
$scanDirs = [
    __DIR__ . '/../app',
];

echo "🔍 Escaneando diretórios:\n";
foreach ($scanDirs as $dir) {
    echo "   - " . realpath($dir) . "\n";
}

try {
    // Cria o diretório se não existir
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
        echo "📁 Diretório criado: {$outputDir}\n";
    }
    
    // Configura o gerador
    $generator = new Generator();
    
    // Configura o analisador para ler atributos PHP 8
    $analyser = new ReflectionAnalyser([
        new AttributeAnnotationFactory(),
        new DocBlockAnnotationFactory(),
    ]);
    $analyser->setGenerator($generator);
    
    // Gera a especificação OpenAPI
    $openapi = $generator
        ->setAnalyser($analyser)
        ->generate(new SourceFinder($scanDirs));
    
    // Salva o arquivo JSON
    $openapi->saveAs($outputFile, 'json');
    
    echo "\n✅ Documentação gerada com sucesso!\n";
    echo "📄 Arquivo: {$outputFile}\n";
    echo "🌐 Acesse: http://localhost:8080/swagger/\n";
    
    // Estatísticas
    $json = file_get_contents($outputFile);
    $spec = json_decode($json, true);
    $pathCount = count($spec['paths'] ?? []);
    $tagCount = count($spec['tags'] ?? []);
    
    echo "\n📊 Estatísticas:\n";
    echo "   - Endpoints documentados: {$pathCount}\n";
    echo "   - Tags: {$tagCount}\n";
    
} catch (Exception $e) {
    echo "\n❌ Erro ao gerar documentação:\n";
    echo "   " . $e->getMessage() . "\n";
    exit(1);
}
