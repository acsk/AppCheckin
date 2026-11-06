import api from './api';

export const acessoHibridoService = {
  async listarTurmas() {
    const response = await api.get('/admin/turmas-fixas');
    if (!Array.isArray(response.data?.turmas)) throw new Error('Resposta inválida ao carregar as turmas.');
    return response.data.turmas;
  },
  async criarTurma(dados) { return (await api.post('/admin/turmas-fixas', dados)).data; },
  async atualizarTurma(id, dados) { return (await api.put(`/admin/turmas-fixas/${id}`, dados)).data; },
  async detalheTurma(id, desde) { return (await api.get(`/admin/turmas-fixas/${id}`, { params: desde ? { desde } : {} })).data; },
  async executarAcao(acao, ids, dados) { return (await api.post('/admin/turmas-fixas/acoes', { acao, ids, dados })).data; },
  async gradeMatricula(id) { return (await api.get(`/admin/matriculas/${id}/grade`)).data; },
  async vincular(id, dados) { return (await api.put(`/admin/matriculas/${id}/tipo-acesso`, dados)).data; },
};
