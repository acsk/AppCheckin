// ════════════════════════════════════════════════════════════════
// EXEMPLOS PRÁTICOS - DESATIVAÇÃO DE TURMAS E DIAS
// ════════════════════════════════════════════════════════════════

/**
 * Este arquivo contém exemplos de como usar os serviços de
 * desativação de turmas e dias
 */

// ════════════════════════════════════════════════════════════════
// 1️⃣ DESATIVAR TURMAS
// ════════════════════════════════════════════════════════════════

import { turmaService } from '../services/turmaService';
import { diaService } from '../services/diaService';
import { showSuccess, showError } from '../utils/toast';

// Exemplo 1: Desativar apenas esta turma
async function exemplo1_ApenasEsta() {
  try {
    const response = await turmaService.desativar(1);
    console.log('✅ Turma desativada:', response);
    // Response: { type: 'success', message: '...', summary: { total_desativadas: 1 } }
  } catch (error) {
    console.error('❌ Erro:', error);
  }
}

// Exemplo 2: Desativar próxima semana (mesmo horário)
async function exemplo2_ProximaSemana() {
  try {
    const response = await turmaService.desativar(1, 'proxima_semana');
    console.log('✅ Turma desativada próxima semana:', response);
  } catch (error) {
    console.error('❌ Erro:', error);
  }
}

// Exemplo 3: Desativar mês inteiro
async function exemplo3_MesTodo() {
  try {
    const response = await turmaService.desativar(
      1, 
      'mes_todo', 
      '2026-02' // fevereiro
    );
    console.log('✅ Turma desativada o mês inteiro:', response);
  } catch (error) {
    console.error('❌ Erro:', error);
  }
}

// Exemplo 4: Desativar com toast (como está implementado)
async function exemplo4_ComToast() {
  try {
    const turmaId = 5;
    const periodo = 'apenas_esta';
    const mes = '2026-01';

    const response = await turmaService.desativar(turmaId, periodo, mes);
    
    // Mostrar sucesso
    showSuccess(response.message || 'Turma desativada com sucesso!');
    
    // Atualizar lista de turmas
    // carregarDados();
    
  } catch (error) {
    let mensagem = 'Erro ao desativar turma';
    if (error.response?.data?.message) {
      mensagem = error.response.data.message;
    }
    showError(mensagem);
  }
}

// ════════════════════════════════════════════════════════════════
// 2️⃣ DESATIVAR DIAS (FERIADOS, SEM AULA)
// ════════════════════════════════════════════════════════════════

// Exemplo 5: Bloquear um dia específico (feriado)
async function exemplo5_BloqueioFeriado() {
  try {
    const response = await diaService.desativar(17); // 09/01/2026
    console.log('✅ Dia bloqueado (feriado):', response);
  } catch (error) {
    console.error('❌ Erro:', error);
  }
}

// Exemplo 6: Bloquear próxima semana (mesmo dia da semana)
async function exemplo6_ProximaSemanaDay() {
  try {
    const response = await diaService.desativar(17, 'proxima_semana');
    console.log('✅ Dia bloqueado próxima semana:', response);
  } catch (error) {
    console.error('❌ Erro:', error);
  }
}

// Exemplo 7: Bloquear mês inteiro (todos os dias)
async function exemplo7_MesTodoDias() {
  try {
    const response = await diaService.desativar(
      17, 
      'mes_todo', 
      null, 
      '2026-02'
    );
    console.log('✅ Mês inteiro bloqueado:', response);
  } catch (error) {
    console.error('❌ Erro:', error);
  }
}

// Exemplo 8: Bloquear domingos de fevereiro
async function exemplo8_BloqueioDomingos() {
  try {
    const response = await diaService.desativar(
      10, // um domingo qualquer
      'custom', 
      [1], // 1 = domingo
      '2026-02'
    );
    console.log('✅ Domingos bloqueados:', response);
  } catch (error) {
    console.error('❌ Erro:', error);
  }
}

// ════════════════════════════════════════════════════════════════
// 3️⃣ CASOS DE USO REAIS
// ════════════════════════════════════════════════════════════════

// Caso 1: Professor faltou hoje
async function casoUso1_ProfessorFaltou() {
  console.log('📌 Professor faltou, desativando aula de hoje');
  
  const turmaId = 1; // CrossFit 18:00
  
  try {
    await turmaService.desativar(turmaId, 'apenas_esta');
    showSuccess('Aula cancelada e alunos serão notificados');
  } catch (error) {
    showError('Erro ao cancelar aula');
  }
}

// Caso 2: Professor sai de férias (mês inteiro)
async function casoUso2_FeriasProf() {
  console.log('📌 Professor em férias em fevereiro');
  
  const turmaId = 5; // Yoga Segunda 19:00
  
  try {
    await turmaService.desativar(turmaId, 'mes_todo', '2026-02');
    showSuccess('Aulas de fevereiro canceladas');
  } catch (error) {
    showError('Erro ao cancelar aulas');
  }
}

// Caso 3: Feriado municipal (bloqueia o dia inteiro)
async function casoUso3_FeriadoMunicipal() {
  console.log('📌 09/01 é feriado, bloqueando o dia');
  
  const diaId = 17; // 09/01/2026
  
  try {
    await diaService.desativar(diaId, 'apenas_este');
    showSuccess('Dia 09/01 bloqueado - Academia fechada');
  } catch (error) {
    showError('Erro ao bloquear dia');
  }
}

// Caso 4: Academia não funciona nos domingos
async function casoUso4_DomingosSemAula() {
  console.log('📌 Bloqueando todos os domingos de janeiro');
  
  const qualquerDomingo = 10; // um domingo qualquer
  
  try {
    await diaService.desativar(
      qualquerDomingo, 
      'custom', 
      [1], // 1 = domingo
      '2026-01'
    );
    showSuccess('Todos os domingos de janeiro bloqueados');
  } catch (error) {
    showError('Erro ao bloquear domingos');
  }
}

// Caso 5: Manutenção da academia (semana inteira)
async function casoUso5_ManutencaoSemana() {
  console.log('📌 Semana de manutenção, bloqueando próxima semana');
  
  const turmaId = 1;
  
  try {
    await turmaService.desativar(turmaId, 'proxima_semana');
    showSuccess('Aulas da próxima semana canceladas para manutenção');
  } catch (error) {
    showError('Erro ao desativar aulas');
  }
}

// ════════════════════════════════════════════════════════════════
// 4️⃣ MAPEAMENTO DE DIAS DA SEMANA
// ════════════════════════════════════════════════════════════════

const DIAS_SEMANA_MAPEAMENTO = {
  1: 'Domingo',
  2: 'Segunda',
  3: 'Terça',
  4: 'Quarta',
  5: 'Quinta',
  6: 'Sexta',
  7: 'Sábado'
};

// Exemplos de bloquear dias específicos:
async function exemploBlockDias() {
  // Apenas sextas
  await diaService.desativar(diaId, 'custom', [6], '2026-01');
  
  // Segunda a sexta
  await diaService.desativar(diaId, 'custom', [2, 3, 4, 5, 6], '2026-01');
  
  // Fim de semana
  await diaService.desativar(diaId, 'custom', [1, 7], '2026-01');
  
  // Apenas terça e quinta
  await diaService.desativar(diaId, 'custom', [3, 5], '2026-01');
}

// ════════════════════════════════════════════════════════════════
// 5️⃣ FUNÇÃO HELPER PARA UI
// ════════════════════════════════════════════════════════════════

/**
 * Hook para desativar turma com modal
 * Uso: const { desativar, loading } = useDesativarTurma();
 */
export function useDesativarTurma() {
  const [loading, setLoading] = React.useState(false);

  const desativar = async (turmaId, periodo = 'apenas_esta', mes = null) => {
    setLoading(true);
    try {
      const response = await turmaService.desativar(turmaId, periodo, mes);
      showSuccess(response.message || 'Turma desativada com sucesso!');
      return response;
    } catch (error) {
      const msg = error.response?.data?.message || 'Erro ao desativar turma';
      showError(msg);
      throw error;
    } finally {
      setLoading(false);
    }
  };

  return { desativar, loading };
}

// ════════════════════════════════════════════════════════════════
// 6️⃣ TESTES
// ════════════════════════════════════════════════════════════════

/**
 * Para testar no console:
 */

/*
// Test 1: Desativar turma
await turmaService.desativar(1);

// Test 2: Desativar próxima semana
await turmaService.desativar(1, 'proxima_semana');

// Test 3: Desativar mês
await turmaService.desativar(1, 'mes_todo', '2026-02');

// Test 4: Bloquear dia
await diaService.desativar(17);

// Test 5: Bloquear domingos
await diaService.desativar(10, 'custom', [1], '2026-01');
*/

// ════════════════════════════════════════════════════════════════
// 7️⃣ CURL PARA TESTES (Backend)
// ════════════════════════════════════════════════════════════════

/*
# Desativar turma (apenas esta)
curl -X POST http://localhost:8080/admin/turmas/desativar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"turma_id": 1}'

# Desativar turma (próxima semana)
curl -X POST http://localhost:8080/admin/turmas/desativar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "turma_id": 1,
    "periodo": "proxima_semana"
  }'

# Desativar turma (mês inteiro)
curl -X POST http://localhost:8080/admin/turmas/desativar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "turma_id": 1,
    "periodo": "mes_todo",
    "mes": "2026-02"
  }'

# Bloquear dia (feriado)
curl -X POST http://localhost:8080/admin/dias/desativar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"dia_id": 17}'

# Bloquear domingos de fevereiro
curl -X POST http://localhost:8080/admin/dias/desativar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "dia_id": 10,
    "periodo": "custom",
    "dias_semana": [1],
    "mes": "2026-02"
  }'
*/

export {
  exemplo1_ApenasEsta,
  exemplo2_ProximaSemana,
  exemplo3_MesTodo,
  exemplo4_ComToast,
  exemplo5_BloqueioFeriado,
  exemplo6_ProximaSemanaDay,
  exemplo7_MesTodoDias,
  exemplo8_BloqueioDomingos,
  casoUso1_ProfessorFaltou,
  casoUso2_FeriasProf,
  casoUso3_FeriadoMunicipal,
  casoUso4_DomingosSemAula,
  casoUso5_ManutencaoSemana,
  exemploBlockDias
};
