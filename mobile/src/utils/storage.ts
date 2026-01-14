/**
 * Abstração de Storage compatível com web e mobile
 * Usa AsyncStorage no mobile e localStorage no web
 */

import { Platform } from 'react-native';

// Detectar se está rodando no web
const isWeb = Platform.OS === 'web';

// Implementação do storage baseada na plataforma
const storageAdapter = isWeb ? 
  {
    getItem: async (key: string) => localStorage.getItem(key),
    setItem: async (key: string, value: string) => {
      localStorage.setItem(key, value);
      return value;
    },
    removeItem: async (key: string) => {
      localStorage.removeItem(key);
    },
    getAllKeys: async () => Object.keys(localStorage),
    multiGet: async (keys: string[]) => {
      return keys.map(key => [key, localStorage.getItem(key)]);
    },
    multiSet: async (keyValuePairs: [string, string][]) => {
      keyValuePairs.forEach(([key, value]) => {
        localStorage.setItem(key, value);
      });
    },
    multiRemove: async (keys: string[]) => {
      keys.forEach(key => {
        localStorage.removeItem(key);
      });
    },
    clear: async () => {
      localStorage.clear();
    },
  }
  : null; // Será importado do react-native-async-storage em mobile

// Importação condicional do AsyncStorage
let AsyncStorage: any;

if (!isWeb) {
  try {
    AsyncStorage = require('@react-native-async-storage/async-storage').default;
  } catch (e) {
    console.warn('AsyncStorage não disponível, usando fallback');
    AsyncStorage = storageAdapter;
  }
} else {
  AsyncStorage = storageAdapter;
}

export default AsyncStorage;
export { isWeb };
