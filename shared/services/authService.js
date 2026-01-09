/**
 * Serviço de Autenticação Compartilhado
 * Funciona tanto em Web (localStorage) quanto em Mobile (AsyncStorage)
 * 
 * Uso:
 * - Web: authService.setStorage(localStorage)
 * - Mobile: authService.setStorage(AsyncStorage)
 */

let storage = null;

export const authService = {
  /**
   * Define o tipo de storage a ser usado
   * @param {Object} storageAdapter - localStorage (Web) ou AsyncStorage (Mobile)
   */
  setStorage(storageAdapter) {
    storage = storageAdapter;
  },

  /**
   * Salva o token
   */
  async saveToken(token) {
    if (!storage) throw new Error('Storage não configurado');
    
    // Detectar se é AsyncStorage (Mobile) ou localStorage (Web)
    if (storage.setItem) {
      // AsyncStorage (Mobile) - async
      return await storage.setItem('@appcheckin:token', token);
    } else {
      // localStorage (Web) - sync
      return storage.setItem('@appcheckin:token', token);
    }
  },

  /**
   * Obter o token
   */
  async getToken() {
    if (!storage) throw new Error('Storage não configurado');
    
    if (storage.getItem && storage.getItem.constructor.name === 'AsyncFunction') {
      // AsyncStorage (Mobile)
      return await storage.getItem('@appcheckin:token');
    } else {
      // localStorage (Web)
      return storage.getItem('@appcheckin:token');
    }
  },

  /**
   * Salva dados do usuário
   */
  async saveUser(userData) {
    if (!storage) throw new Error('Storage não configurado');
    
    const isAsync = storage.setItem && storage.setItem.constructor.name === 'AsyncFunction';
    
    if (isAsync) {
      // AsyncStorage (Mobile)
      return await storage.setItem('@appcheckin:user', JSON.stringify(userData));
    } else {
      // localStorage (Web)
      return storage.setItem('@appcheckin:user', JSON.stringify(userData));
    }
  },

  /**
   * Obter usuário salvo
   */
  async getUser() {
    if (!storage) throw new Error('Storage não configurado');
    
    try {
      let userJson;
      
      // Verificar tipo de storage
      if (storage.getItem && storage.getItem.constructor.name === 'AsyncFunction') {
        // AsyncStorage (Mobile)
        userJson = await storage.getItem('@appcheckin:user');
      } else {
        // localStorage (Web)
        userJson = storage.getItem('@appcheckin:user');
      }
      
      return userJson ? JSON.parse(userJson) : null;
    } catch (error) {
      console.error('Erro ao buscar usuário:', error);
      return null;
    }
  },

  /**
   * Fazer login
   */
  async login(email, senha, baseUrl) {
    try {
      const response = await fetch(`${baseUrl}/auth/login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email, senha }),
      });

      if (!response.ok) {
        throw new Error('Credenciais inválidas');
      }

      const data = await response.json();
      
      // Salvar token e usuário
      await this.saveToken(data.token);
      await this.saveUser(data.user);

      return {
        token: data.token,
        user: data.user,
        tenants: data.tenants,
      };
    } catch (error) {
      console.error('Erro ao fazer login:', error);
      throw error;
    }
  },

  /**
   * Fetch dados completos do usuário
   */
  async fetchCompleteUser(baseUrl) {
    try {
      const token = await this.getToken();
      if (!token) {
        throw new Error('Token não encontrado');
      }

      const response = await fetch(`${baseUrl}/me`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        throw new Error(`Erro ao buscar dados: ${response.status}`);
      }

      const userData = await response.json();
      
      // Salvar dados completos
      await this.saveUser(userData);
      
      return userData;
    } catch (error) {
      console.error('Erro ao buscar dados completos:', error);
      // Tentar retornar dados parciais do storage
      return await this.getUser();
    }
  },

  /**
   * Atualizar perfil
   */
  async updateProfile(data, baseUrl) {
    try {
      const token = await this.getToken();
      if (!token) {
        throw new Error('Token não encontrado');
      }

      const response = await fetch(`${baseUrl}/me`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify(data),
      });

      if (!response.ok) {
        throw new Error('Erro ao atualizar perfil');
      }

      const result = await response.json();
      const updatedUser = result.usuario || result.user || result;
      
      await this.saveUser(updatedUser);
      
      return updatedUser;
    } catch (error) {
      console.error('Erro ao atualizar perfil:', error);
      throw error;
    }
  },

  /**
   * Fazer logout
   */
  async logout() {
    try {
      // Limpar storage
      if (storage.removeItem) {
        const isAsync = storage.removeItem.constructor.name === 'AsyncFunction';
        
        if (isAsync) {
          // AsyncStorage (Mobile)
          await storage.removeItem('@appcheckin:token');
          await storage.removeItem('@appcheckin:user');
        } else {
          // localStorage (Web)
          storage.removeItem('@appcheckin:token');
          storage.removeItem('@appcheckin:user');
        }
      }
      
      console.log('Logout realizado com sucesso');
    } catch (error) {
      console.error('Erro ao fazer logout:', error);
      throw error;
    }
  },

  /**
   * Verificar se está autenticado
   */
  async isAuthenticated() {
    const token = await this.getToken();
    return !!token;
  },
};

export default authService;
