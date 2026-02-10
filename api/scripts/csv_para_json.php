<?php
/**
 * Converte CSV para JSON para importação de matrículas
 * 
 * Uso: php scripts/csv_para_json.php alunos.csv alunos.json
 * 
 * O CSV deve ter cabeçalho com as colunas:
 * nome,email,cpf,telefone,plano_nome,ciclo_meses,data_inicio
 */

if ($argc < 3) {
    echo "❌ Uso: php scripts/csv_para_json.php alunos.csv alunos.json\n";
    exit(1);
}

$csvFile = $argv[1];
$jsonFile = $argv[2];

if (!file_exists($csvFile)) {
    echo "❌ Arquivo CSV não encontrado: {$csvFile}\n";
    exit(1);
}

$alunos = [];
$handle = fopen($csvFile, 'r');

if ($handle === false) {
    echo "❌ Erro ao abrir arquivo CSV\n";
    exit(1);
}

// Ler cabeçalho
$header = fgetcsv($handle, 1000, ',');

if (!$header) {
    echo "❌ CSV vazio ou inválido\n";
    exit(1);
}

// Normalizar nomes das colunas
$header = array_map('trim', $header);
$header = array_map('strtolower', $header);

echo "📋 Colunas encontradas: " . implode(', ', $header) . "\n\n";

$linha = 1;
while (($data = fgetcsv($handle, 1000, ',')) !== false) {
    $linha++;
    
    if (count($data) !== count($header)) {
        echo "⚠️  Linha {$linha}: número de colunas diferente do cabeçalho, pulando...\n";
        continue;
    }
    
    $aluno = array_combine($header, $data);
    
    // Validar campos obrigatórios
    if (empty(trim($aluno['nome'] ?? ''))) {
        echo "⚠️  Linha {$linha}: nome vazio, pulando...\n";
        continue;
    }
    
    if (empty(trim($aluno['email'] ?? ''))) {
        echo "⚠️  Linha {$linha}: email vazio, pulando...\n";
        continue;
    }
    
    $alunoFormatado = [
        'nome' => trim($aluno['nome']),
        'email' => trim(strtolower($aluno['email'])),
        'cpf' => preg_replace('/[^0-9]/', '', $aluno['cpf'] ?? ''),
        'telefone' => preg_replace('/[^0-9]/', '', $aluno['telefone'] ?? ''),
        'plano_nome' => trim($aluno['plano_nome'] ?? ''),
        'ciclo_meses' => (int) ($aluno['ciclo_meses'] ?? 1),
        'data_inicio' => trim($aluno['data_inicio'] ?? date('Y-m-d'))
    ];
    
    $alunos[] = $alunoFormatado;
    echo "✅ Linha {$linha}: {$alunoFormatado['nome']}\n";
}

fclose($handle);

// Salvar JSON
$json = json_encode($alunos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents($jsonFile, $json);

echo "\n";
echo "==========================================\n";
echo "✅ Arquivo JSON criado: {$jsonFile}\n";
echo "📋 Total de alunos: " . count($alunos) . "\n";
echo "==========================================\n";
echo "\nAgora execute:\n";
echo "php scripts/importar_matriculas.php {$jsonFile}\n";
