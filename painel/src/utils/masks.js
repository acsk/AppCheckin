/**
 * Utilitários de Máscaras e Formatação
 * Funções reutilizáveis para aplicar máscaras em campos de formulário
 */

/**
 * Remove todos os caracteres não numéricos de uma string
 * @param {string} value - Valor a ser limpo
 * @returns {string} - Apenas os números
 */
export const apenasNumeros = (value) => {
  return value ? value.replace(/\D/g, '') : '';
};

/**
 * Aplica máscara de CNPJ (XX.XXX.XXX/XXXX-XX)
 * @param {string} value - Valor a ser formatado
 * @returns {string} - CNPJ formatado
 */
export const mascaraCNPJ = (value) => {
  const numeros = apenasNumeros(value);
  
  if (numeros.length <= 2) {
    return numeros;
  } else if (numeros.length <= 5) {
    return numeros.replace(/(\d{2})(\d{0,3})/, '$1.$2');
  } else if (numeros.length <= 8) {
    return numeros.replace(/(\d{2})(\d{3})(\d{0,3})/, '$1.$2.$3');
  } else if (numeros.length <= 12) {
    return numeros.replace(/(\d{2})(\d{3})(\d{3})(\d{0,4})/, '$1.$2.$3/$4');
  } else {
    return numeros.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{0,2})/, '$1.$2.$3/$4-$5');
  }
};

/**
 * Aplica máscara de CPF (XXX.XXX.XXX-XX)
 * @param {string} value - Valor a ser formatado
 * @returns {string} - CPF formatado
 */
export const mascaraCPF = (value) => {
  const numeros = apenasNumeros(value);
  
  if (numeros.length <= 3) {
    return numeros;
  } else if (numeros.length <= 6) {
    return numeros.replace(/(\d{3})(\d{0,3})/, '$1.$2');
  } else if (numeros.length <= 9) {
    return numeros.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
  } else {
    return numeros.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4');
  }
};

/**
 * Aplica máscara de telefone (XX) XXXXX-XXXX ou (XX) XXXX-XXXX
 * @param {string} value - Valor a ser formatado
 * @returns {string} - Telefone formatado
 */
export const mascaraTelefone = (value) => {
  let numeros = apenasNumeros(value);
  
  // Limitar a 11 dígitos (celular)
  if (numeros.length > 11) {
    numeros = numeros.substring(0, 11);
  }
  
  if (numeros.length <= 2) {
    return numeros;
  } else if (numeros.length <= 6) {
    return numeros.replace(/(\d{2})(\d{0,4})/, '($1) $2');
  } else if (numeros.length <= 10) {
    // Telefone fixo: (XX) XXXX-XXXX
    return numeros.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
  } else {
    // Celular: (XX) XXXXX-XXXX
    return numeros.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
  }
};

/**
 * Aplica máscara de CEP (XXXXX-XXX)
 * @param {string} value - Valor a ser formatado
 * @returns {string} - CEP formatado
 */
export const mascaraCEP = (value) => {
  const numeros = apenasNumeros(value);
  
  if (numeros.length <= 5) {
    return numeros;
  } else {
    return numeros.replace(/(\d{5})(\d{0,3})/, '$1-$2');
  }
};

/**
 * Aplica máscara de valor monetário (R$ X.XXX,XX)
 * @param {string} value - Valor a ser formatado
 * @returns {string} - Valor formatado
 */
export const mascaraDinheiro = (value) => {
  const numeros = apenasNumeros(value);
  
  if (!numeros) return 'R$ 0,00';
  
  const valorFormatado = (parseFloat(numeros) / 100).toFixed(2)
    .replace('.', ',')
    .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  
  return `R$ ${valorFormatado}`;
};

/**
 * Remove a máscara de valor monetário
 * @param {string} value - Valor formatado
 * @returns {string} - Apenas os números
 */
export const removerMascaraDinheiro = (value) => {
  return apenasNumeros(value);
};

/**
 * Valida CNPJ
 * @param {string} cnpj - CNPJ a ser validado (com ou sem máscara)
 * @returns {boolean} - true se válido, false se inválido
 */
export const validarCNPJ = (cnpj) => {
  const numeros = apenasNumeros(cnpj);
  
  if (numeros.length !== 14) return false;
  
  // Verifica se todos os dígitos são iguais
  if (/^(\d)\1+$/.test(numeros)) return false;
  
  // Validação dos dígitos verificadores
  let tamanho = numeros.length - 2;
  let numeros_calc = numeros.substring(0, tamanho);
  const digitos = numeros.substring(tamanho);
  let soma = 0;
  let pos = tamanho - 7;
  
  for (let i = tamanho; i >= 1; i--) {
    soma += numeros_calc.charAt(tamanho - i) * pos--;
    if (pos < 2) pos = 9;
  }
  
  let resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
  if (resultado != digitos.charAt(0)) return false;
  
  tamanho = tamanho + 1;
  numeros_calc = numeros.substring(0, tamanho);
  soma = 0;
  pos = tamanho - 7;
  
  for (let i = tamanho; i >= 1; i--) {
    soma += numeros_calc.charAt(tamanho - i) * pos--;
    if (pos < 2) pos = 9;
  }
  
  resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
  if (resultado != digitos.charAt(1)) return false;
  
  return true;
};

/**
 * Valida CPF
 * @param {string} cpf - CPF a ser validado (com ou sem máscara)
 * @returns {boolean} - true se válido, false se inválido
 */
export const validarCPF = (cpf) => {
  const numeros = apenasNumeros(cpf);
  
  if (numeros.length !== 11) return false;
  
  // Verifica se todos os dígitos são iguais
  if (/^(\d)\1+$/.test(numeros)) return false;
  
  // Validação dos dígitos verificadores
  let soma = 0;
  let resto;
  
  for (let i = 1; i <= 9; i++) {
    soma += parseInt(numeros.substring(i - 1, i)) * (11 - i);
  }
  
  resto = (soma * 10) % 11;
  if (resto === 10 || resto === 11) resto = 0;
  if (resto !== parseInt(numeros.substring(9, 10))) return false;
  
  soma = 0;
  for (let i = 1; i <= 10; i++) {
    soma += parseInt(numeros.substring(i - 1, i)) * (12 - i);
  }
  
  resto = (soma * 10) % 11;
  if (resto === 10 || resto === 11) resto = 0;
  if (resto !== parseInt(numeros.substring(10, 11))) return false;
  
  return true;
};

/**
 * Aplica máscara de hora (HH:MM)
 * @param {string} value - Valor a ser formatado
 * @returns {string} - Hora formatada (HH:MM)
 */
export const mascaraHora = (value) => {
  const numeros = apenasNumeros(value);
  
  if (numeros.length <= 2) {
    return numeros;
  } else if (numeros.length <= 4) {
    return numeros.replace(/(\d{2})(\d{0,2})/, '$1:$2');
  } else {
    // Limita a 4 dígitos (HH:MM)
    return numeros.substring(0, 4).replace(/(\d{2})(\d{0,2})/, '$1:$2');
  }
};

/**
 * Aplica máscara de data (DD/MM/YYYY)
 * Aceita entrada em formato YYYYMMDD ou DDMMYYYY e retorna formatado
 * @param {string} value - Valor a ser formatado
 * @returns {string} - Data formatada (DD/MM/YYYY)
 */
export const mascaraData = (value) => {
  if (!value) return '';
  
  // Se vier no formato YYYY-MM-DD (input type date), converter para DD/MM/YYYY
  if (value.includes('-')) {
    const [ano, mes, dia] = value.split('-');
    if (ano && mes && dia) {
      return `${dia}/${mes}/${ano}`;
    }
  }
  
  // Se vier apenas números, formatar como DD/MM/YYYY
  const numeros = apenasNumeros(value);
  
  if (numeros.length <= 2) {
    return numeros;
  } else if (numeros.length <= 4) {
    return numeros.replace(/(\d{2})(\d{0,2})/, '$1/$2');
  } else if (numeros.length <= 8) {
    return numeros.replace(/(\d{2})(\d{2})(\d{0,4})/, '$1/$2/$3');
  } else {
    // Limita a 8 dígitos
    return numeros.substring(0, 8).replace(/(\d{2})(\d{2})(\d{0,4})/, '$1/$2/$3');
  }
};
