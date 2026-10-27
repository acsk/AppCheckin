import api from './api';

// Central de Comunicação (API v2: /admin/comunicacoes). Erros sobem como o corpo da API ({ error }).
const erro = (e, fallback) => e?.response?.data || { error: fallback };

async function chamar(fn, fallback) {
  try {
    return (await fn()).data;
  } catch (e) {
    console.error('❌ Comunicação:', e?.response?.data || e?.message);
    throw erro(e, fallback);
  }
}

const comunicacaoService = {
  listar: params => chamar(() => api.get('/admin/comunicacoes', { params }), 'Erro ao listar comunicações'),
  obter: id => chamar(() => api.get(`/admin/comunicacoes/${id}`), 'Erro ao carregar a comunicação'),
  opcoes: () => chamar(() => api.get('/admin/comunicacoes/opcoes'), 'Erro ao carregar as opções'),
  criar: dados => chamar(() => api.post('/admin/comunicacoes', dados), 'Erro ao salvar'),
  atualizar: (id, dados) => chamar(() => api.put(`/admin/comunicacoes/${id}`, dados), 'Erro ao salvar'),
  excluir: id => chamar(() => api.delete(`/admin/comunicacoes/${id}`), 'Erro ao excluir'),
  duplicar: id => chamar(() => api.post(`/admin/comunicacoes/${id}/duplicar`), 'Erro ao duplicar'),
  publicar: id => chamar(() => api.post(`/admin/comunicacoes/${id}/publicar`), 'Erro ao publicar'),
  incluirDestinatarios: id => chamar(() => api.post(`/admin/comunicacoes/${id}/incluir-destinatarios`), 'Erro ao incluir alunos'),
  encerrar: id => chamar(() => api.post(`/admin/comunicacoes/${id}/encerrar`), 'Erro ao encerrar'),
  cancelar: id => chamar(() => api.post(`/admin/comunicacoes/${id}/cancelar`), 'Erro ao cancelar'),
  previaPublico: publico => chamar(() => api.post('/admin/comunicacoes/previa-publico', { publico }), 'Erro ao calcular o público'),
  indicadores: params => chamar(() => api.get('/admin/comunicacoes/indicadores', { params }), 'Erro ao carregar os indicadores'),
  destinatarios: (id, params) => chamar(() => api.get(`/admin/comunicacoes/${id}/destinatarios`, { params }), 'Erro ao carregar os destinatários'),
  resultados: id => chamar(() => api.get(`/admin/comunicacoes/${id}/resultados`), 'Erro ao carregar os resultados'),
  respostas: id => chamar(() => api.get(`/admin/comunicacoes/${id}/respostas`), 'Erro ao carregar as respostas'),
  enviarAnexo: async arquivo => {
    const form = new FormData();
    form.append('arquivo', arquivo);
    return chamar(() => api.post('/admin/comunicacoes/anexos', form, { headers: { 'Content-Type': 'multipart/form-data' } }), 'Erro ao enviar o arquivo');
  },
};

export default comunicacaoService;
