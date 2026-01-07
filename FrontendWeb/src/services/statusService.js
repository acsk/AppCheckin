import api from './api';

/**
 * Serviço para gerenciar status do sistema
 * Centraliza acesso aos diferentes tipos de status
 */
const statusService = {
  /**
   * Buscar todos os status de um tipo
   * @param {string} tipo - Tipo do status: conta-receber, matricula, pagamento, checkin, usuario, contrato
   * @returns {Promise<Array>} Lista de status
   */
  listar: async (tipo) => {
    try {
      const response = await api.get(`/status/${tipo}`);
      return response.data.status || [];
    } catch (error) {
      console.error(`Erro ao listar status ${tipo}:`, error);
      throw error.response?.data || error;
    }
  },

  /**
   * Buscar status específico por ID
   * @param {string} tipo - Tipo do status
   * @param {number} id - ID do status
   * @returns {Promise<Object>} Dados do status
   */
  buscar: async (tipo, id) => {
    try {
      const response = await api.get(`/status/${tipo}/${id}`);
      return response.data;
    } catch (error) {
      console.error(`Erro ao buscar status ${tipo}/${id}:`, error);
      throw error.response?.data || error;
    }
  },

  /**
   * Buscar status por código
   * @param {string} tipo - Tipo do status
   * @param {string} codigo - Código do status (ex: 'pendente', 'ativo')
   * @returns {Promise<Object>} Dados do status
   */
  buscarPorCodigo: async (tipo, codigo) => {
    try {
      const response = await api.get(`/status/${tipo}/codigo/${codigo}`);
      return response.data;
    } catch (error) {
      console.error(`Erro ao buscar status ${tipo}/codigo/${codigo}:`, error);
      throw error.response?.data || error;
    }
  },

  // ==========================================
  // Métodos específicos por tipo (atalhos)
  // ==========================================

  /**
   * Listar status de Contas a Receber
   * @returns {Promise<Array>} [{ id, codigo, nome, cor, icone, ... }]
   */
  listarStatusContaReceber: async () => {
    return statusService.listar('conta-receber');
  },

  /**
   * Listar status de Matrículas
   * @returns {Promise<Array>}
   */
  listarStatusMatricula: async () => {
    return statusService.listar('matricula');
  },

  /**
   * Listar status de Pagamentos
   * @returns {Promise<Array>}
   */
  listarStatusPagamento: async () => {
    return statusService.listar('pagamento');
  },

  /**
   * Listar status de Check-ins
   * @returns {Promise<Array>}
   */
  listarStatusCheckin: async () => {
    return statusService.listar('checkin');
  },

  /**
   * Listar status de Usuários
   * @returns {Promise<Array>}
   */
  listarStatusUsuario: async () => {
    return statusService.listar('usuario');
  },

  /**
   * Listar status de Contratos
   * @returns {Promise<Array>}
   */
  listarStatusContrato: async () => {
    return statusService.listar('contrato');
  },

  // ==========================================
  // Helpers / Utilidades
  // ==========================================

  /**
   * Obter objeto de status a partir de um código
   * @param {Array} statusList - Lista de status
   * @param {string} codigo - Código a buscar
   * @returns {Object|null} Status encontrado ou null
   */
  encontrarPorCodigo: (statusList, codigo) => {
    return statusList.find(s => s.codigo === codigo) || null;
  },

  /**
   * Obter cor de um status
   * @param {Object} status - Objeto status
   * @returns {string} Cor hexadecimal (default: #6b7280)
   */
  getCor: (status) => {
    return status?.cor || '#6b7280';
  },

  /**
   * Obter ícone de um status
   * @param {Object} status - Objeto status
   * @returns {string} Nome do ícone Feather
   */
  getIcone: (status) => {
    return status?.icone || 'circle';
  },

  /**
   * Formatar para uso em Picker/Select
   * @param {Array} statusList - Lista de status
   * @returns {Array} [{ label, value }]
   */
  formatarParaPicker: (statusList) => {
    return statusList.map(s => ({
      label: s.nome,
      value: s.id,
      ...s // incluir dados extras
    }));
  },
};

export default statusService;
