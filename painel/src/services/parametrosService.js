import api from './api';

class ParametrosService {
  async listar() {
    try {
      const response = await api.get('/admin/parametros');
      return response.data;
    } catch (error) {
      console.error('Erro ao listar parâmetros:', error);
      throw error;
    }
  }

  async atualizarMultiplos(parametros) {
    try {
      const response = await api.put('/admin/parametros', { parametros });
      return response.data;
    } catch (error) {
      console.error('Erro ao atualizar parâmetros:', error);
      throw error;
    }
  }

  async atualizarParametro(codigo, valor) {
    try {
      const response = await api.patch(`/admin/parametros/${codigo}`, { valor });
      return response.data;
    } catch (error) {
      console.error('Erro ao atualizar parâmetro:', error);
      throw error;
    }
  }

  async toggleParametro(codigo) {
    try {
      const response = await api.patch(`/admin/parametros/${codigo}/toggle`);
      return response.data;
    } catch (error) {
      console.error('Erro ao alternar parâmetro:', error);
      throw error;
    }
  }
}

export default new ParametrosService();
