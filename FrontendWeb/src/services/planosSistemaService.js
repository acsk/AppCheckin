import axios from 'axios';
import { authService } from './authService';

const API_URL = 'http://localhost:8080/superadmin/planos-sistema';

class PlanosSistemaService {
  async listar(apenasAtivos = false, apenasAtuais = false) {
    try {
      const token = await authService.getToken();
      const params = {};
      
      if (apenasAtivos) {
        params.ativos = 'true';
      }
      
      if (apenasAtuais) {
        params.apenas_atuais = 'true';
      }
      
      const response = await axios.get(API_URL, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        params
      });
      
      return response.data;
    } catch (error) {
      console.error('Erro ao listar planos do sistema:', error);
      throw error.response?.data || { error: 'Erro ao listar planos do sistema' };
    }
  }

  async listarDisponiveis() {
    try {
      const token = await authService.getToken();
      
      const response = await axios.get(`${API_URL}/disponiveis`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      return response.data;
    } catch (error) {
      console.error('Erro ao listar planos disponíveis:', error);
      throw error.response?.data || { error: 'Erro ao listar planos disponíveis' };
    }
  }

  async buscarPorId(id) {
    try {
      const token = await authService.getToken();
      
      const response = await axios.get(`${API_URL}/${id}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      return response.data;
    } catch (error) {
      console.error('Erro ao buscar plano:', error);
      throw error.response?.data || { error: 'Erro ao buscar plano' };
    }
  }

  async criar(planoData) {
    try {
      const token = await authService.getToken();
      
      const response = await axios.post(API_URL, planoData, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      return response.data;
    } catch (error) {
      console.error('Erro ao criar plano:', error);
      throw error.response?.data || { error: 'Erro ao criar plano' };
    }
  }

  async atualizar(id, planoData) {
    try {
      const token = await authService.getToken();
      
      const response = await axios.put(`${API_URL}/${id}`, planoData, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      return response.data;
    } catch (error) {
      console.error('Erro ao atualizar plano:', error);
      throw error.response?.data || { error: 'Erro ao atualizar plano' };
    }
  }

  async marcarHistorico(id) {
    try {
      const token = await authService.getToken();
      
      const response = await axios.post(`${API_URL}/${id}/marcar-historico`, {}, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      return response.data;
    } catch (error) {
      console.error('Erro ao marcar plano como histórico:', error);
      throw error.response?.data || { error: 'Erro ao marcar plano como histórico' };
    }
  }

  async desativar(id) {
    try {
      const token = await authService.getToken();
      
      const response = await axios.delete(`${API_URL}/${id}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      return response.data;
    } catch (error) {
      console.error('Erro ao desativar plano:', error);
      throw error.response?.data || { error: 'Erro ao desativar plano' };
    }
  }

  async listarAcademias(id) {
    try {
      const token = await authService.getToken();
      
      const response = await axios.get(`${API_URL}/${id}/academias`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      return response.data;
    } catch (error) {
      console.error('Erro ao listar academias do plano:', error);
      throw error.response?.data || { error: 'Erro ao listar academias do plano' };
    }
  }
}

export default new PlanosSistemaService();
