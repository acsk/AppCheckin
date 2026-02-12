<?php
/**
 * Script para resetar rate limit de um IP específico
 * USO: php reset_rate_limit.php <IP>
 * Exemplo: php reset_rate_limit.php 192.168.1.1
 */

require __DIR__ . '/config/database.php';

if ($argc < 2) {
    echo "Uso: php reset_rate_limit.php <IP>\n";
    echo "Exemplo: php reset_rate_limit.php 192.168.1.1\n";
    echo "\nOu para resetar TODOS os rate limits:\n";
    echo "php reset_rate_limit.php --all\n";
    exit(1);
}

$ip = $argv[1];

try {
    $db = require __DIR__ . '/config/database.php';
    
    if ($ip === '--all') {
        $stmt = $db->prepare("DELETE FROM rate_limits");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        echo "✓ Todos os rate limits resetados ($deleted registros removidos)\n";
    } else {
        $stmt = $db->prepare("DELETE FROM rate_limits WHERE ip = :ip AND action = 'register-mobile'");
        $stmt->execute(['ip' => $ip]);
        $deleted = $stmt->rowCount();
        
        if ($deleted > 0) {
            echo "✓ Rate limit resetado para IP: $ip ($deleted registro(s) removido(s))\n";
        } else {
            echo "ℹ Nenhum rate limit encontrado para IP: $ip\n";
        }
    }
    
    // Mostrar rate limits ativos
    echo "\nRate limits ativos:\n";
    $stmt = $db->query("SELECT ip, action, attempts, expires_at FROM rate_limits ORDER BY expires_at DESC");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "  (nenhum)\n";
    } else {
        foreach ($results as $row) {
            $remaining = strtotime($row['expires_at']) - time();
            echo sprintf("  - IP: %-15s | Ação: %-20s | Tentativas: %d | Expira em: %d segundos\n", 
                $row['ip'], 
                $row['action'], 
                $row['attempts'],
                max(0, $remaining)
            );
        }
    }
    
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
