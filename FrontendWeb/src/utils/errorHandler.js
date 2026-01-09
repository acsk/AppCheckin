/**
 * Utilitários para tratamento e formatação de mensagens de erro
 */

/**
 * Extrai a mensagem limpa de erro do backend
 * Remove SQLSTATE e códigos de erro, mantendo apenas a mensagem relevante
 * 
 * @param {any} error - Objeto de erro ou string com mensagem
 * @returns {string} - Mensagem de erro limpa e formatada
 * 
 * @example
 * // Input: "SQLSTATE[45000]: <<Unknown error>>: 1644 Ja existe uma matricula ativa para este usuario e plano"
 * // Output: "Ja existe uma matricula ativa para este usuario e plano"
 */
export const extrairMensagemErro = (error) => {
  if (!error) return 'Erro desconhecido';
  
  let mensagem = error.message || error.error || error || 'Erro desconhecido';
  
  // Se a mensagem contém SQLSTATE, extrair apenas a mensagem após o código
  // Formato: "SQLSTATE[45000]: <<Unknown error>>: 1644 Mensagem real aqui"
  const sqlstateMatch = mensagem.match(/SQLSTATE\[\d+\]:\s*<<[^>]+>>:\s*\d+\s+(.+)/);
  if (sqlstateMatch && sqlstateMatch[1]) {
    return sqlstateMatch[1].trim();
  }
  
  // Se é apenas um texto simples, retornar como está
  return typeof mensagem === 'string' ? mensagem : 'Erro desconhecido';
};

/**
 * Prepara um objeto de erro adicionando mensagem limpa
 * Útil para ser usado em tratadores de erro em serviços
 * 
 * @param {any} errorData - Dados de erro do response
 * @returns {any} - Objeto com mensagemLimpa adicionada
 */
export const prepararErro = (errorData) => {
  if (!errorData) {
    return { mensagemLimpa: 'Erro desconhecido' };
  }
  
  const erro = typeof errorData === 'string' 
    ? { message: errorData } 
    : { ...errorData };
    
  erro.mensagemLimpa = extrairMensagemErro(erro);
  return erro;
};

/**
 * Obtém a melhor mensagem de erro disponível, priorizando:
 * 1. mensagemLimpa (adicionada por prepararErro)
 * 2. error.error
 * 3. error.message
 * 4. fallback padrão
 * 
 * @param {any} error - Objeto de erro
 * @param {string} fallback - Mensagem padrão se nenhuma for encontrada
 * @returns {string} - Mensagem de erro mais apropriada
 */
export const obterMensagemErro = (error, fallback = 'Ocorreu um erro inesperado') => {
  if (!error) return fallback;
  
  return error.mensagemLimpa || 
         error.error || 
         error.message || 
         fallback;
};
