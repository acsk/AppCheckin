import { apiCall } from './api';

// ============ CRUD WODs ============

/**
 * Listar WODs com filtros opcionais
 * @param {Object} filters - Filtros opcionais
 * @param {string} filters.status - published|draft|archived
 * @param {string} filters.data_inicio - Data inicial (YYYY-MM-DD)
 * @param {string} filters.data_fim - Data final (YYYY-MM-DD)
 * @param {string} filters.data - Data específica (YYYY-MM-DD)
 */
export const listarWods = async (filters = {}) => {
  const params = new URLSearchParams();
  
  if (filters.status) params.append('status', filters.status);
  if (filters.data_inicio) params.append('data_inicio', filters.data_inicio);
  if (filters.data_fim) params.append('data_fim', filters.data_fim);
  if (filters.data) params.append('data', filters.data);
  
  const queryString = params.toString();
  const url = `/admin/wods${queryString ? '?' + queryString : ''}`;
  
  return apiCall('GET', url);
};

/**
 * Buscar WOD por data e modalidade
 * @param {string} data - Data no formato YYYY-MM-DD
 * @param {number} modalidadeId - ID da modalidade
 */
export const buscarWodPorDataModalidade = async (data, modalidadeId) => {
  const params = new URLSearchParams();
  if (data) params.append('data', data);
  if (modalidadeId) params.append('modalidade_id', modalidadeId);
  return apiCall('GET', `/admin/wods/buscar?${params.toString()}`);
};

/**
 * Obter detalhes de um WOD
 * @param {number} id - ID do WOD
 */
export const obterWod = async (id) => {
  return apiCall('GET', `/admin/wods/${id}`);
};

/**
 * Criar novo WOD
 * @param {Object} dados - Dados do WOD
 * @param {string} dados.titulo - Título do WOD
 * @param {string} dados.descricao - Descrição do WOD
 * @param {string} dados.data - Data do WOD (YYYY-MM-DD)
 * @param {string} dados.status - Status (draft|published|archived)
 */
export const criarWod = async (dados) => {
  return apiCall('POST', '/admin/wods', dados);
};

/**
 * Criar WOD completo com blocos e variações
 * @param {Object} dados - Dados do WOD completo
 * @param {string} dados.titulo - Título do WOD
 * @param {string} dados.descricao - Descrição do WOD
 * @param {string} dados.data - Data do WOD (YYYY-MM-DD)
 * @param {string} dados.status - Status (draft|published|archived)
 * @param {Array} dados.blocos - Array de blocos do WOD
 * @param {Array} dados.variacoes - Array de variações do WOD
 */
export const criarWodCompleto = async (dados) => {
  return apiCall('POST', '/admin/wods/completo', dados);
};

/**
 * Atualizar WOD
 * @param {number} id - ID do WOD
 * @param {Object} dados - Dados a atualizar
 */
export const atualizarWod = async (id, dados) => {
  return apiCall('PUT', `/admin/wods/${id}`, dados);
};

/**
 * Deletar WOD
 * @param {number} id - ID do WOD
 */
export const deletarWod = async (id) => {
  return apiCall('DELETE', `/admin/wods/${id}`);
};

/**
 * Publicar WOD
 * @param {number} id - ID do WOD
 */
export const publicarWod = async (id) => {
  return apiCall('PATCH', `/admin/wods/${id}/publish`);
};

/**
 * Arquivar WOD
 * @param {number} id - ID do WOD
 */
export const arquivarWod = async (id) => {
  return apiCall('PATCH', `/admin/wods/${id}/archive`);
};

// ============ CRUD Blocos ============

/**
 * Listar blocos de um WOD
 * @param {number} wodId - ID do WOD
 */
export const listarBlocos = async (wodId) => {
  return apiCall('GET', `/admin/wods/${wodId}/blocos`);
};

/**
 * Criar bloco em um WOD
 * @param {number} wodId - ID do WOD
 * @param {Object} dados - Dados do bloco
 * @param {number} dados.ordem - Ordem do bloco
 * @param {string} dados.tipo - warmup|strength|metcon|accessory|cooldown|note
 * @param {string} dados.titulo - Título do bloco
 * @param {string} dados.conteudo - Conteúdo do bloco
 * @param {string} dados.tempo_cap - Tempo cap
 */
export const criarBloco = async (wodId, dados) => {
  return apiCall('POST', `/admin/wods/${wodId}/blocos`, dados);
};

/**
 * Atualizar bloco
 * @param {number} wodId - ID do WOD
 * @param {number} blocoId - ID do bloco
 * @param {Object} dados - Dados a atualizar
 */
export const atualizarBloco = async (wodId, blocoId, dados) => {
  return apiCall('PUT', `/admin/wods/${wodId}/blocos/${blocoId}`, dados);
};

/**
 * Deletar bloco
 * @param {number} wodId - ID do WOD
 * @param {number} blocoId - ID do bloco
 */
export const deletarBloco = async (wodId, blocoId) => {
  return apiCall('DELETE', `/admin/wods/${wodId}/blocos/${blocoId}`);
};

// ============ CRUD Variações ============

/**
 * Listar variações de um WOD
 * @param {number} wodId - ID do WOD
 */
export const listarVariacoes = async (wodId) => {
  return apiCall('GET', `/admin/wods/${wodId}/variacoes`);
};

/**
 * Criar variação em um WOD
 * @param {number} wodId - ID do WOD
 * @param {Object} dados - Dados da variação
 * @param {string} dados.nome - Nome da variação (RX|Scaled|Beginner)
 * @param {string} dados.descricao - Descrição da variação
 */
export const criarVariacao = async (wodId, dados) => {
  return apiCall('POST', `/admin/wods/${wodId}/variacoes`, dados);
};

/**
 * Atualizar variação
 * @param {number} wodId - ID do WOD
 * @param {number} variacaoId - ID da variação
 * @param {Object} dados - Dados a atualizar
 */
export const atualizarVariacao = async (wodId, variacaoId, dados) => {
  return apiCall('PUT', `/admin/wods/${wodId}/variacoes/${variacaoId}`, dados);
};

/**
 * Deletar variação
 * @param {number} wodId - ID do WOD
 * @param {number} variacaoId - ID da variação
 */
export const deletarVariacao = async (wodId, variacaoId) => {
  return apiCall('DELETE', `/admin/wods/${wodId}/variacoes/${variacaoId}`);
};

// ============ CRUD Resultados/Leaderboard ============

/**
 * Listar resultados (leaderboard) de um WOD
 * @param {number} wodId - ID do WOD
 */
export const listarResultados = async (wodId) => {
  return apiCall('GET', `/admin/wods/${wodId}/resultados`);
};

/**
 * Registrar resultado para um usuário em um WOD
 * @param {number} wodId - ID do WOD
 * @param {Object} dados - Dados do resultado
 * @param {number} dados.usuario_id - ID do usuário
 * @param {number} dados.variacao_id - ID da variação
 * @param {string} dados.tipo_score - time|reps|weight|rounds_reps|distance|calories|points
 * @param {number} dados.valor_num - Valor numérico
 * @param {string} dados.valor_texto - Valor em texto (opcional)
 * @param {string} dados.observacao - Observação (opcional)
 */
export const registrarResultado = async (wodId, dados) => {
  return apiCall('POST', `/admin/wods/${wodId}/resultados`, dados);
};

/**
 * Atualizar resultado
 * @param {number} wodId - ID do WOD
 * @param {number} resultadoId - ID do resultado
 * @param {Object} dados - Dados a atualizar
 */
export const atualizarResultado = async (wodId, resultadoId, dados) => {
  return apiCall('PUT', `/admin/wods/${wodId}/resultados/${resultadoId}`, dados);
};

/**
 * Deletar resultado
 * @param {number} wodId - ID do WOD
 * @param {number} resultadoId - ID do resultado
 */
export const deletarResultado = async (wodId, resultadoId) => {
  return apiCall('DELETE', `/admin/wods/${wodId}/resultados/${resultadoId}`);
};
