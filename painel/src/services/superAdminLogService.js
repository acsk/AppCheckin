import api from './api';
import { prepararErro } from '../utils/errorHandler';

export const superAdminLogService = {
  async listar(filtros = {}) {
    try {
      const params = {};
      if (filtros.arquivo) params.arquivo = filtros.arquivo;
      if (filtros.linhas) params.linhas = filtros.linhas;
      if (filtros.busca) params.busca = filtros.busca;
      if (filtros.nivel) params.nivel = filtros.nivel;

      const response = await api.get('/admin/logs', { params });
      return response.data;
    } catch (error) {
      console.error('Erro ao buscar logs Laravel:', error);
      throw prepararErro(error.response?.data || error);
    }
  },
};
