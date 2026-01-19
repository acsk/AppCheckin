import api from './api';

/**
 * Serviço para gerenciar contratos de planos do sistema das academias
 */

/**
 * Associar plano do sistema a uma academia (criar contrato)
 * @param {number} academiaId - ID da academia
 * @param {object} dados - { plano_sistema_id, forma_pagamento, data_inicio?, data_vencimento?, observacoes? }
 */
export const associarPlano = async (academiaId, dados) => {
  try {
    const response = await api.post(`/superadmin/academias/${academiaId}/contratos`, dados);
    return { success: true, data: response.data };
  } catch (error) {
    console.error('Erro ao associar plano:', error);
    return {
      success: false,
      type: error.response?.data?.type || 'error',
      message: error.response?.data?.message || 'Erro ao associar plano',
      contratoAtivo: error.response?.data?.contrato_ativo || null
    };
  }
};

/**
 * Buscar contrato ativo de uma academia
 * @param {number} academiaId - ID da academia
 */
export const buscarContratoAtivo = async (academiaId) => {
  try {
    const response = await api.get(`/superadmin/academias/${academiaId}/contrato-ativo`);
    const data = response.data;
    
    // Se veio com message e type, é um aviso (sem contrato)
    if (data.message && data.type && !data.id) {
      return { 
        success: true, 
        data: null,
        message: data.message,
        type: data.type
      };
    }
    
    // Se veio com id, é um contrato ativo
    return { success: true, data: data };
  } catch (error) {
    console.error('Erro ao buscar contrato ativo:', error);
    return {
      success: false,
      error: error.response?.data?.message || error.response?.data?.error || 'Erro ao buscar contrato ativo'
    };
  }
};

/**
 * Troca o plano de uma academia (desativa o atual e cria novo)
 * @param {number} academiaId - ID da academia
 * @param {object} dados - { plano_sistema_id, forma_pagamento, observacoes? }
 */
export const trocarPlano = async (academiaId, dados) => {
  try {
    const response = await api.post(`/superadmin/academias/${academiaId}/trocar-plano`, dados);
    return { success: true, data: response.data };
  } catch (error) {
    console.error('Erro ao trocar plano:', error);
    return {
      success: false,
      error: error.response?.data?.error || error.response?.data?.errors?.join(', ') || 'Erro ao trocar plano'
    };
  }
};

/**
 * Busca todos os contratos de uma academia
 * @param {number} academiaId - ID da academia
 */
export const buscarContratos = async (academiaId) => {
  try {
    const response = await api.get(`/superadmin/academias/${academiaId}/contratos`);
    return { success: true, data: response.data };
  } catch (error) {
    console.error('Erro ao buscar contratos:', error);
    return {
      success: false,
      error: error.response?.data?.error || 'Erro ao buscar contratos'
    };
  }
};

/**
 * Renova um contrato existente
 * @param {number} contratoId - ID do contrato
 * @param {string} observacoes - Observações da renovação
 */
export const renovarContrato = async (contratoId, observacoes = null) => {
  try {
    const response = await api.post(`/superadmin/contratos/${contratoId}/renovar`, {
      observacoes
    });
    return { success: true, data: response.data };
  } catch (error) {
    console.error('Erro ao renovar contrato:', error);
    return {
      success: false,
      error: error.response?.data?.error || 'Erro ao renovar contrato'
    };
  }
};

/**
 * Busca contratos próximos do vencimento
 * @param {number} dias - Dias antes do vencimento (default: 7)
 */
export const buscarContratosProximosVencimento = async (dias = 7) => {
  try {
    const response = await api.get(`/superadmin/contratos/proximos-vencimento?dias=${dias}`);
    return { success: true, data: response.data };
  } catch (error) {
    console.error('Erro ao buscar contratos próximos do vencimento:', error);
    return {
      success: false,
      error: error.response?.data?.error || 'Erro ao buscar contratos'
    };
  }
};

/**
 * Cancela um contrato
 * @param {number} contratoId - ID do contrato
 */
export const cancelarContrato = async (contratoId) => {
  try {
    const response = await api.delete(`/superadmin/contratos/${contratoId}`);
    return { success: true, data: response.data };
  } catch (error) {
    console.error('Erro ao cancelar contrato:', error);
    return {
      success: false,
      error: error.response?.data?.error || 'Erro ao cancelar contrato'
    };
  }
};

/**
 * Lista todos os contratos do sistema
 */
export const listarTodosContratos = async () => {
  try {
    const response = await api.get('/superadmin/contratos');
    return { success: true, data: response.data };
  } catch (error) {
    console.error('Erro ao listar contratos:', error);
    return {
      success: false,
      error: error.response?.data?.error || 'Erro ao listar contratos'
    };
  }
};

/**
 * Busca contratos vencidos
 */
export const buscarContratosVencidos = async () => {
  try {
    const response = await api.get('/superadmin/contratos/vencidos');
    return { success: true, data: response.data };
  } catch (error) {
    console.error('Erro ao buscar contratos vencidos:', error);
    return {
      success: false,
      error: error.response?.data?.error || 'Erro ao buscar contratos vencidos'
    };
  }
};

export default {
  associarPlano,
  buscarContratoAtivo,
  trocarPlano,
  buscarContratos,
  renovarContrato,
  buscarContratosProximosVencimento,
  cancelarContrato,
  listarTodosContratos,
  buscarContratosVencidos
};
