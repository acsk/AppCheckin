import { Alert, Platform } from 'react-native';
import AsyncStorage from './storage';

/**
 * Utilitário para tratar erros de autenticação (401)
 * Limpa o storage e mostra mensagem ao usuário
 */
export const handleAuthError = async (message?: string): Promise<void> => {
  console.log('🔑 Tratando erro de autenticação - Token inválido/expirado');
  
  // Limpar dados de autenticação
  await AsyncStorage.removeItem('@appcheckin:token');
  await AsyncStorage.removeItem('@appcheckin:user');
  
  // Mensagem padrão
  const defaultMessage = 'Sua sessão expirou. Por favor, faça login novamente.';
  const displayMessage = message || defaultMessage;
  
  // Mostrar mensagem ao usuário
  if (Platform.OS === 'web') {
    alert(displayMessage);
  } else {
    Alert.alert(
      'Sessão Expirada',
      displayMessage,
      [{ text: 'OK' }]
    );
  }
};

/**
 * Verifica se um erro é um erro de autenticação (401)
 */
export const isAuthError = (error: any): boolean => {
  return (
    error?.response?.status === 401 ||
    error?.status === 401 ||
    (typeof error?.message === 'string' && error.message.includes('401'))
  );
};

/**
 * Extrai mensagem de erro de diferentes formatos de resposta
 */
export const extractErrorMessage = (error: any): string => {
  // Tentar pegar mensagem do erro
  if (error?.response?.data?.error) {
    return error.response.data.error;
  }
  
  if (error?.response?.data?.message) {
    return error.response.data.message;
  }
  
  if (error?.data?.error) {
    return error.data.error;
  }
  
  if (error?.data?.message) {
    return error.data.message;
  }
  
  if (error?.message) {
    return error.message;
  }
  
  return 'Erro desconhecido';
};

/**
 * Trata erros de requisição de forma padronizada
 */
export const handleRequestError = async (error: any, defaultMessage: string = 'Erro ao processar requisição'): Promise<string> => {
  console.error('🔴 Erro na requisição:', error);
  
  // Se for erro 401, tratar especialmente
  if (isAuthError(error)) {
    await handleAuthError();
    return 'Sessão expirada';
  }
  
  // Extrair mensagem de erro
  const errorMessage = extractErrorMessage(error);
  
  // Se não conseguiu extrair mensagem, usar a padrão
  return errorMessage || defaultMessage;
};
