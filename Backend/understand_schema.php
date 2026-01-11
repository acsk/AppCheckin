<?php
$db = new PDO('mysql:host=mysql;dbname=appcheckin', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Buscar alunos cadastrados que podem estar em uma turma
// Verifiquemos se há relação em checkins ou em um outras tabela

echo "Estrutura do DB - Buscando relação entre turmas e usuários:\n\n";

// Ver todos os usuários que fizeram check-in em uma turma
echo "Usuários com check-in:\n";
$sql = "SELECT DISTINCT usuario_id FROM checkins WHERE turma_id = 187";
$result = $db->query($sql);
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
echo "Total: " . count($rows) . "\n\n";

// Se não há matriculas diretas de turma, vamos usar os usuarios com checkins
if (count($rows) === 0) {
    echo "⚠️  Não há alunos matriculados na turma 187\n";
    echo "Isso pode estar correto pois turmas podem estar vinculadas a planos\n";
}
?>
