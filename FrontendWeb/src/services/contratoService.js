import api from './api';

/**
 * Serviço para gerenciar contratos de planos das academias
 */

/**
 * Cria um novo contrato para uma academia
 * @param {number} academiaId - ID da academia
 * @param {object} dados - { plano_id, forma_pagamento, data_inicio?, data_vencimento?, observacoes? }
 */
export const criarContrato = async (academiaId, dados) => {
  try {
    const response = await api.post(`/superadmin/academias/${academiaId}/contrato`, dados);
    return { success: true, data: response.data };
  } catch (error) {
    console.error('Erro ao criar contrato:', error);
    return {
      success: false,
      error: error.response?.data?.error || error.response?.data?.errors || 'Erro ao criar contrato'
    };
  }
};

/**
 * Troca o plano de uma academia
 * @param {number} academiaId - ID da academia
 * @param {object} dados - { plano_id, forma_pagamento, observacoes? }
 */
export const trocarPlano = async (academiaId, dados) => {
  try {
    const response = await api.post(`/superadmin/academias/${academiaId}/trocar-plano`, dados);
    return { success: true, data: response.data };
  } catch (error) {
    console.error('Erro ao trocar plano:', error);
    return {
      success: false,
      error: error.response?.data?.error || error.response?.data?.errors || 'Erro ao trocar plano'
    };
  }
};

/**
 * Busca o contrato ativo e histórico de uma academia
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
  criarContrato,
  trocarPlano,
  buscarContratos,
  renovarContrato,
  buscarContratosProximosVencimento,
  buscarContratosVencidos
};
