import api from './api';

const cepService = {
  /**
   * Buscar dados de endereço pelo CEP
   * @param {string} cep - CEP brasileiro (8 dígitos, com ou sem formatação)
   * @returns {Promise<Object>} Dados do endereço
   */
  async buscar(cep) {
    try {
      // Remover caracteres não numéricos
      const cepLimpo = cep.replace(/\D/g, '');
      
      if (cepLimpo.length !== 8) {
        throw { message: 'CEP deve conter 8 dígitos' };
      }

      const response = await api.get(`/cep/${cepLimpo}`);
      
      if (response.data.type === 'success') {
        return response.data.data;
      }
      
      throw response.data;
    } catch (error) {
      const errorMsg = error.response?.data?.message || error.message || 'Erro desconhecido ao buscar CEP';
      console.error('❌ Erro ao buscar CEP:', errorMsg);
      console.error('   Response:', error.response?.data);
      console.error('   Status:', error.response?.status);
      throw error.response?.data || error;
    }
  }
};

export default cepService;
