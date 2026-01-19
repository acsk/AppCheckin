/**
 * Serviço de Estados Brasileiros
 * Lista completa dos estados do Brasil
 */

const ESTADOS_BRASILEIROS = [
  { sigla: 'AC', nome: 'Acre' },
  { sigla: 'AL', nome: 'Alagoas' },
  { sigla: 'AP', nome: 'Amapá' },
  { sigla: 'AM', nome: 'Amazonas' },
  { sigla: 'BA', nome: 'Bahia' },
  { sigla: 'CE', nome: 'Ceará' },
  { sigla: 'DF', nome: 'Distrito Federal' },
  { sigla: 'ES', nome: 'Espírito Santo' },
  { sigla: 'GO', nome: 'Goiás' },
  { sigla: 'MA', nome: 'Maranhão' },
  { sigla: 'MT', nome: 'Mato Grosso' },
  { sigla: 'MS', nome: 'Mato Grosso do Sul' },
  { sigla: 'MG', nome: 'Minas Gerais' },
  { sigla: 'PA', nome: 'Pará' },
  { sigla: 'PB', nome: 'Paraíba' },
  { sigla: 'PR', nome: 'Paraná' },
  { sigla: 'PE', nome: 'Pernambuco' },
  { sigla: 'PI', nome: 'Piauí' },
  { sigla: 'RJ', nome: 'Rio de Janeiro' },
  { sigla: 'RN', nome: 'Rio Grande do Norte' },
  { sigla: 'RS', nome: 'Rio Grande do Sul' },
  { sigla: 'RO', nome: 'Rondônia' },
  { sigla: 'RR', nome: 'Roraima' },
  { sigla: 'SC', nome: 'Santa Catarina' },
  { sigla: 'SP', nome: 'São Paulo' },
  { sigla: 'SE', nome: 'Sergipe' },
  { sigla: 'TO', nome: 'Tocantins' },
];

/**
 * Retorna lista de todos os estados brasileiros
 * @returns {Array} Lista de estados com sigla e nome
 */
export const listarEstados = () => {
  return ESTADOS_BRASILEIROS;
};

/**
 * Busca um estado pela sigla
 * @param {string} sigla - Sigla do estado (ex: 'SP', 'RJ')
 * @returns {Object|null} Objeto do estado ou null se não encontrado
 */
export const buscarPorSigla = (sigla) => {
  if (!sigla) return null;
  return ESTADOS_BRASILEIROS.find(
    estado => estado.sigla.toLowerCase() === sigla.toLowerCase()
  ) || null;
};

/**
 * Valida se a sigla é um estado válido
 * @param {string} sigla - Sigla do estado
 * @returns {boolean} true se válido, false caso contrário
 */
export const validarEstado = (sigla) => {
  return buscarPorSigla(sigla) !== null;
};

export default {
  listarEstados,
  buscarPorSigla,
  validarEstado,
};
