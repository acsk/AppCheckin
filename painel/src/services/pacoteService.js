import api from './api';
import { prepararErro } from '../utils/errorHandler';

const pacoteService = {
  /**
   * Listar pacotes do tenant
   */
  async listar(filtros = {}) {
    try {
      const response = await api.get('/admin/pacotes', { params: filtros });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar pacotes:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Buscar pacote por ID
   */
  async buscar(id) {
    try {
      const response = await api.get(`/admin/pacotes/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao buscar pacote:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Criar pacote
   */
  async criar(dados) {
    try {
      const response = await api.post('/admin/pacotes', dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao criar pacote:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Atualizar pacote
   */
  async atualizar(id, dados) {
    try {
      const response = await api.put(`/admin/pacotes/${id}`, dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao atualizar pacote:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Excluir pacote
   */
  async excluir(id) {
    try {
      const response = await api.delete(`/admin/pacotes/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao excluir pacote:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Contratar pacote (cria contrato)
   */
  async contratar(pacoteId, dados) {
    try {
      const response = await api.post(`/admin/pacotes/${pacoteId}/contratar`, dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao contratar pacote:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Definir beneficiários do contrato
   */
  async definirBeneficiarios(contratoId, beneficiarios) {
    try {
      const response = await api.post(`/admin/pacotes/contratos/${contratoId}/beneficiarios`, {
        beneficiarios,
      });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao definir beneficiários:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Confirmar pagamento do contrato e ativar matrículas
   */
  async confirmarPagamento(contratoId, pagamentoId) {
    try {
      const response = await api.post(`/admin/pacotes/contratos/${contratoId}/confirmar-pagamento`, {
        pagamento_id: pagamentoId,
      });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao confirmar pagamento:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Listar contratos de pacote por status
   */
  async listarContratos(status = 'pendente') {
    try {
      const response = await api.get('/admin/pacote-contratos', { params: { status } });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar contratos de pacote:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Gerar matrículas do contrato (pagante + dependentes)
   */
  async gerarMatriculas(contratoId) {
    try {
      const response = await api.post(`/admin/pacote-contratos/${contratoId}/gerar-matriculas`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao gerar matrículas do pacote:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Excluir contrato de pacote
   */
  async excluirContrato(contratoId) {
    try {
      const response = await api.delete(`/admin/pacotes/contratos/${contratoId}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao excluir contrato:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },
};

export default pacoteService;
