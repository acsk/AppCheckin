import api from './api';

// Módulo Prospecção → Recuperação de Alunos (apiV2 /admin/prospeccao). Erros: lança o corpo da resposta.
const BASE = '/admin/prospeccao';

async function call(promise) {
  try {
    const response = await promise;
    return response.data;
  } catch (error) {
    throw error.response?.data || { error: error.message || 'Falha de conexão' };
  }
}

const prospeccaoService = {
  contexto: () => call(api.get(`${BASE}/contexto`)),
  dashboard: (params = {}) => call(api.get(`${BASE}/dashboard`, { params })),
  relatorio: (params = {}) => call(api.get(`${BASE}/relatorios/recuperacao`, { params })),
  sincronizar: () => call(api.post(`${BASE}/sincronizar`)),

  listar: (params = {}) => call(api.get(`${BASE}/oportunidades`, { params })),
  detalhar: (id) => call(api.get(`${BASE}/oportunidades/${id}`)),
  atualizar: (id, dados) => call(api.patch(`${BASE}/oportunidades/${id}`, dados)),
  alterarStatus: (id, dados) => call(api.patch(`${BASE}/oportunidades/${id}/status`, dados)),
  registrarInteracao: (id, dados) => call(api.post(`${BASE}/oportunidades/${id}/interacoes`, dados)),
  removerRestricao: (id, motivo) => call(api.post(`${BASE}/oportunidades/${id}/remover-restricao`, { motivo })),
  verificarRecuperacao: (id) => call(api.get(`${BASE}/oportunidades/${id}/recuperacao`)),
  recalcularScore: (id) => call(api.post(`${BASE}/oportunidades/${id}/score`)),

  agenda: (params = {}) => call(api.get(`${BASE}/agendamentos`, { params })),
  agendar: (dados) => call(api.post(`${BASE}/agendamentos`, dados)),
  atualizarAgendamento: (id, dados) => call(api.patch(`${BASE}/agendamentos/${id}`, dados)),

  modelos: (params = {}) => call(api.get(`${BASE}/modelos-mensagem`, { params })),
  criarModelo: (dados) => call(api.post(`${BASE}/modelos-mensagem`, dados)),
  atualizarModelo: (id, dados) => call(api.put(`${BASE}/modelos-mensagem/${id}`, dados)),
  excluirModelo: (id) => call(api.delete(`${BASE}/modelos-mensagem/${id}`)),

  scoreConfig: () => call(api.get(`${BASE}/score/config`)),
  salvarScoreConfig: (dados) => call(api.put(`${BASE}/score/config`, dados)),
  recalcularTodos: () => call(api.post(`${BASE}/score/recalcular`)),
  equipe: () => call(api.get(`${BASE}/equipe`)),
  adicionarEquipe: (dados) => call(api.post(`${BASE}/equipe`, dados)),
  removerEquipe: (usuarioId) => call(api.delete(`${BASE}/equipe/${usuarioId}`)),
};

export default prospeccaoService;
