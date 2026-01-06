/**
 * Formata uma data no formato brasileiro
 * @param {string} data - Data no formato ISO ou SQL (YYYY-MM-DD)
 * @returns {string} Data formatada (DD/MM/YYYY)
 */
export function formatarData(data) {
  if (!data) return '-';
  
  try {
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
