/**
 * Formata uma data no formato brasileiro
 * @param {string} data - Data no formato ISO ou SQL (YYYY-MM-DD)
 * @returns {string} Data formatada (DD/MM/YYYY)
 */
export function formatarData(data) {
  if (!data) return '-';
  
  try {
    // Se a data vier no formato YYYY-MM-DD, fazer split direto para evitar problemas de timezone
    if (typeof data === 'string' && data.match(/^\d{4}-\d{2}-\d{2}/)) {
      const [dataParte] = data.split('T'); // Remove hora se existir
      const [ano, mes, dia] = dataParte.split('-');
      return `${dia}/${mes}/${ano}`;
    }
    
    // Fallback para outros formatos
    const date = new Date(data);
    const dia = String(date.getDate()).padStart(2, '0');
    const mes = String(date.getMonth() + 1).padStart(2, '0');
    const ano = date.getFullYear();
    
    return `${dia}/${mes}/${ano}`;
  } catch (error) {
    return '-';
  }
}

/**
 * Formata um valor monetário no formato brasileiro
 * @param {number|string} valor - Valor a ser formatado
 * @returns {string} Valor formatado (R$ 1.234,56)
 */
export function formatarValorMonetario(valor) {
  if (!valor && valor !== 0) return 'R$ 0,00';
  
  try {
    const numero = typeof valor === 'string' ? parseFloat(valor) : valor;
    
    return numero.toLocaleString('pt-BR', {
      style: 'currency',
      currency: 'BRL',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  } catch (error) {
    return 'R$ 0,00';
  }
}

/**
 * Formata um CPF
 * @param {string} cpf - CPF sem formatação
 * @returns {string} CPF formatado (123.456.789-00)
 */
export function formatarCPF(cpf) {
  if (!cpf) return '';
  
  const numeros = cpf.replace(/\D/g, '');
  
  if (numeros.length !== 11) return cpf;
  
  return numeros.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
}

/**
 * Formata um telefone
 * @param {string} telefone - Telefone sem formatação
 * @returns {string} Telefone formatado
 */
export function formatarTelefone(telefone) {
  if (!telefone) return '';
  
  const numeros = telefone.replace(/\D/g, '');
  
  if (numeros.length === 11) {
    return numeros.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
  } else if (numeros.length === 10) {
    return numeros.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
  }
  
  return telefone;
}

/**
 * Formata um CEP
 * @param {string} cep - CEP sem formatação
 * @returns {string} CEP formatado (12345-678)
 */
export function formatarCEP(cep) {
  if (!cep) return '';
  
  const numeros = cep.replace(/\D/g, '');
  
  if (numeros.length !== 8) return cep;
  
  return numeros.replace(/(\d{5})(\d{3})/, '$1-$2');
}

/**
 * Calcular dias restantes até vencimento
 * @param {string} dataVencimento - Data de vencimento no formato YYYY-MM-DD
 * @returns {number} Dias restantes (negativo se vencido)
 */
export function calcularDiasRestantes(dataVencimento) {
  if (!dataVencimento) return 0;
  
  const hoje = new Date();
  hoje.setHours(0, 0, 0, 0);
  
  const vencimento = new Date(dataVencimento + 'T00:00:00');
  vencimento.setHours(0, 0, 0, 0);
  
  const diffTime = vencimento - hoje;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  
  return diffDays;
}

/**
 * Verificar se está vencido
 * @param {string} dataVencimento - Data de vencimento no formato YYYY-MM-DD
 * @returns {boolean} True se vencido
 */
export function verificarSeVencido(dataVencimento) {
  return calcularDiasRestantes(dataVencimento) < 0;
}

/**
 * Obter cor baseado em dias restantes
 * @param {number} diasRestantes - Dias restantes até vencimento
 * @returns {string} Cor do badge
 */
export function getCorVencimento(diasRestantes) {
  if (diasRestantes < 0) return 'danger';
  if (diasRestantes === 0) return 'danger';
  if (diasRestantes <= 3) return 'warning';
  if (diasRestantes <= 7) return 'info';
  return 'success';
}

/**
 * Formatar data para input (YYYY-MM-DD)
 * @param {Date|string} data - Data a ser formatada
 * @returns {string} Data formatada para input
 */
export function formatarDataParaInput(data) {
  if (!data) return '';
  
  try {
    const date = data instanceof Date ? data : new Date(data);
    const ano = date.getFullYear();
    const mes = String(date.getMonth() + 1).padStart(2, '0');
    const dia = String(date.getDate()).padStart(2, '0');
    
    return `${ano}-${mes}-${dia}`;
  } catch (error) {
    return '';
  }
}

