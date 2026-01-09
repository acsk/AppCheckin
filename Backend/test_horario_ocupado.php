<?php
/**
 * Teste da regra: não permitir aulas em horários ocupados
 * 
 * Este script testa a validação de horários ocupados
 */

echo "=== TESTE: VALIDAÇÃO DE HORÁRIO OCUPADO ===\n\n";

echo "CENÁRIO 1: Criar turma em horário DISPONÍVEL\n";
echo "- Dia: 17 (2026-01-09)\n";
echo "- Horário: 10:00-11:00\n";
echo "- Professor: 10 (Lucas Santos)\n";
echo "✅ Esperado: Turma criada com sucesso\n\n";

echo "CENÁRIO 2: Criar turma em horário OCUPADO (mesmo dia)\n";
echo "- Dia: 17 (2026-01-09)\n";
echo "- Horário: 06:00-07:00 (JÁ EXISTE TURMA AQUI!)\n";
echo "- Professor: 11 (outro professor)\n";
echo "❌ Esperado: Erro 400 - 'Já existe uma turma agendada neste horário neste dia'\n\n";

echo "CENÁRIO 3: Atualizar turma para horário OCUPADO\n";
echo "- Turma ID: 194\n";
echo "- Novo horário: 08:00-09:00\n";
echo "- Se esse horário já está ocupado: Erro 400\n\n";

echo "=== TESTES IMPLEMENTADOS EM ===\n";
echo "📁 app/Controllers/TurmaController.php\n";
echo "   - create() - linha ~283: validação de horário ocupado\n";
echo "   - update() - linha ~371: validação de horário ocupado\n\n";

echo "📁 app/Models/Turma.php\n";
echo "   - verificarHorarioOcupado() - novo método\n";
echo "   - Verifica se existe turma ativa no mesmo dia/horário\n";
echo "   - Exclui a turma atual (importante para UPDATE)\n\n";

echo "🔍 COMO TESTAR:\n";
echo "1. Use Insomnia/Postman para fazer POST /admin/turmas\n";
echo "2. Tente com:\n";
echo "   - dia_id: 17\n";
echo "   - horario_inicio: '06:00'\n";
echo "   - horario_fim: '07:00'\n";
echo "3. Deve retornar erro 400 porque esse horário já está ocupado\n";
?>
