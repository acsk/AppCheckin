import api from './api';
import { prepararErro } from '../utils/errorHandler';

const descontoMatriculaService = {
  async listar(matriculaId) {
    try {
      const response = await api.get(`/admin/matriculas/${matriculaId}/descontos`);
      return response.data;
    } catch (error) {
      console.error('Erro ao listar descontos:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async buscar(descontoId) {
    try {
      const response = await api.get(`/admin/matricula-descontos/${descontoId}`);
      return response.data;
    } catch (error) {
      console.error('Erro ao buscar desconto:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async criar(matriculaId, dados) {
    try {
      const response = await api.post(`/admin/matriculas/${matriculaId}/descontos`, dados);
      return response.data;
    } catch (error) {
      console.error('Erro ao criar desconto:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async atualizar(descontoId, dados) {
    try {
      const response = await api.put(`/admin/matricula-descontos/${descontoId}`, dados);
      return response.data;
    } catch (error) {
      console.error('Erro ao atualizar desconto:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async desativar(descontoId) {
    try {
      const response = await api.delete(`/admin/matricula-descontos/${descontoId}`);
      return response.data;
    } catch (error) {
      console.error('Erro ao desativar desconto:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async listarAdmins() {
    try {
      const response = await api.get('/admin/admins');
      return response.data;
    } catch (error) {
      console.error('Erro ao listar admins:', error);
      throw prepararErro(error.response?.data || error);
    }
  },
};

export default descontoMatriculaService;
