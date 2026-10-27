import api from './api';

// Financeiro → Recorrências (planos com fidelização). Erros sobem como o corpo da API ({ error }).
async function chamar(fn, fallback) {
  try {
    return (await fn()).data;
  } catch (e) {
    console.error('❌ Recorrências:', e?.response?.data || e?.message);
    throw e?.response?.data || { error: fallback };
  }
}

const recorrenciaService = {
  listar: params => chamar(() => api.get('/admin/recorrencias', { params }), 'Erro ao listar as recorrências'),
  indicadores: () => chamar(() => api.get('/admin/recorrencias/indicadores'), 'Erro ao carregar os indicadores'),
  obter: (id, gateway = false) => chamar(() => api.get(`/admin/recorrencias/${id}`, { params: gateway ? { gateway: 1 } : undefined }), 'Erro ao carregar o contrato'),
  simular: (id, params) => chamar(() => api.get(`/admin/recorrencias/${id}/simular-cancelamento`, { params }), 'Erro ao simular o cancelamento'),
  cancelar: (id, dados) => chamar(() => api.post(`/admin/recorrencias/${id}/cancelar`, dados), 'Erro ao cancelar'),
  confirmarCancelamento: (cancelamentoId, dados) => chamar(() => api.post(`/admin/recorrencias/cancelamentos/${cancelamentoId}/confirmar`, dados), 'Erro ao confirmar o cancelamento'),
  sincronizar: id => chamar(() => api.post(`/admin/recorrencias/${id}/sincronizar`), 'Erro ao consultar o Asaas'),
};

export default recorrenciaService;
