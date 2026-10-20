import api from './api';

const erro = (error, fallback) => {
  const d = error?.response?.data || {};
  return { message: d.message || d.error || fallback, status: error?.response?.status, code: d.code };
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

  // trocarForma: cancela no Asaas a cobrança já gerada (se não foi paga) e gera outra na forma escolhida
  async pagar(pagamentoId, formaPagamento, documento, trocarForma = false) {
    try {
      return (await api.post(`/admin/meu-contrato/pagamentos/${pagamentoId}/pagar`, { forma_pagamento: formaPagamento, documento: documento || undefined, trocar_forma: trocarForma || undefined })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível gerar a cobrança');
    }
  },

  async trocarPlano({ planoId, formaPagamento, motivo, documento }) {
    try {
      return (await api.post('/admin/meu-contrato/trocar-plano', { plano_sistema_id: planoId, forma_pagamento: formaPagamento, motivo, documento: documento || undefined })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível trocar o plano');
    }
  },
};

export default meuContratoService;
