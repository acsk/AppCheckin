import api from './api';

const erro = (error, fallback) => {
  const d = error?.response?.data || {};
  return { message: d.message || d.error || fallback, status: error?.response?.status };
};

// Contrato da academia com o Check-in (plano do sistema), pelo admin da própria academia
const meuContratoService = {
  async buscar() {
    try {
      return (await api.get('/admin/meu-contrato')).data;
    } catch (error) {
      throw erro(error, 'Não foi possível carregar o plano da academia');
    }
  },

  async pagar(pagamentoId, formaPagamento) {
    try {
      return (await api.post(`/admin/meu-contrato/pagamentos/${pagamentoId}/pagar`, { forma_pagamento: formaPagamento })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível gerar a cobrança');
    }
  },

  async trocarPlano({ planoId, formaPagamento, motivo }) {
    try {
      return (await api.post('/admin/meu-contrato/trocar-plano', { plano_sistema_id: planoId, forma_pagamento: formaPagamento, motivo })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível trocar o plano');
    }
  },
};

export default meuContratoService;
