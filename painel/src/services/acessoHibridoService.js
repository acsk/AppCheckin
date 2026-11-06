import api from './api';

export const acessoHibridoService = {
  async listarTurmas() {
    const response = await api.get('/admin/turmas-fixas');
    return response.data?.turmas || [];
  },
  async criarTurma(dados) { return (await api.post('/admin/turmas-fixas', dados)).data; },
  async atualizarTurma(id, dados) { return (await api.put(`/admin/turmas-fixas/${id}`, dados)).data; },
  async gradeMatricula(id) { return (await api.get(`/admin/matriculas/${id}/grade`)).data; },
  async vincular(id, dados) { return (await api.put(`/admin/matriculas/${id}/tipo-acesso`, dados)).data; },
};
