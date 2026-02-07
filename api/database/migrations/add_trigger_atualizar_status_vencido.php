<?php

/**
 * Migration: Criar evento MySQL para atualizar status de matrículas vencidas
 * 
 * Este evento roda diariamente às 00:01 e atualiza automaticamente
 * o status das matrículas de "ativa" (id=1) para "vencida" (id=2)
 * quando proxima_data_vencimento < hoje
 */

require_once __DIR__ . '/../../config/database.php';

try {
    echo "🔄 Criando evento para atualizar status de matrículas vencidas...\n\n";
    
    // 1. Garantir que event_scheduler está ativado
    $pdo->exec("SET GLOBAL event_scheduler = ON");
    echo "✅ Event scheduler ativado\n";
    
    // 2. Dropar evento se já existir
    $pdo->exec("DROP EVENT IF EXISTS atualizar_matriculas_vencidas");
    echo "✅ Evento anterior removido (se existia)\n";
    
    // 3. Criar evento que roda diariamente
    $sql = "
    CREATE EVENT atualizar_matriculas_vencidas
    ON SCHEDULE EVERY 1 DAY
    STARTS CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 1 MINUTE
    COMMENT 'Atualiza status de matrículas para vencida quando proxima_data_vencimento expirar'
    DO
    BEGIN
        -- Atualizar matrículas ativas que venceram
        UPDATE matriculas
        SET status_id = 2, -- vencida
            updated_at = NOW()
        WHERE status_id = 1 -- ativa
        AND proxima_data_vencimento IS NOT NULL
        AND proxima_data_vencimento < CURDATE();
        
        -- Log opcional
        -- INSERT INTO logs (mensagem, created_at) 
        -- VALUES (CONCAT('Matrículas atualizadas: ', ROW_COUNT()), NOW());
    END
    ";
    
    $pdo->exec($sql);
    echo "✅ Evento 'atualizar_matriculas_vencidas' criado com sucesso\n";
    echo "   - Roda diariamente às 00:01\n";
    echo "   - Atualiza status_id de 1 (ativa) para 2 (vencida)\n\n";
    
    // 4. Executar a primeira vez manualmente
    echo "🔄 Atualizando matrículas vencidas agora...\n";
    $stmt = $pdo->prepare("
        UPDATE matriculas
        SET status_id = 2,
            updated_at = NOW()
        WHERE status_id = 1
        AND proxima_data_vencimento IS NOT NULL
        AND proxima_data_vencimento < CURDATE()
    ");
    $stmt->execute();
    $affected = $stmt->rowCount();
    
    echo "✅ {$affected} matrícula(s) atualizada(s) para status 'vencida'\n\n";
    
    // 5. Mostrar matrículas vencidas
    $stmt = $pdo->query("
        SELECT m.id, m.proxima_data_vencimento, u.nome as aluno_nome, s.nome as status_nome
        FROM matriculas m
        INNER JOIN alunos a ON a.id = m.aluno_id
        INNER JOIN usuarios u ON u.id = a.usuario_id
        INNER JOIN status_matricula s ON s.id = m.status_id
        WHERE m.proxima_data_vencimento < CURDATE()
        ORDER BY m.proxima_data_vencimento ASC
    ");
    
    echo "📋 Matrículas com data vencida:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-5s %-20s %-20s %-20s\n", "ID", "Aluno", "Vencimento", "Status");
    echo str_repeat("-", 80) . "\n";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf(
            "%-5s %-20s %-20s %-20s\n",
            $row['id'],
            substr($row['aluno_nome'], 0, 18),
            $row['proxima_data_vencimento'],
            $row['status_nome']
        );
    }
    
    echo "\n✅ Migration executada com sucesso!\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
