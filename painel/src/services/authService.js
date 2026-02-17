import api from './api';
import AsyncStorage from '@react-native-async-storage/async-storage';

export const authService = {
  normalizeUser(user) {
    if (!user) return user;
    if (user.papel_id == null && user.role_id != null) {
      return { ...user, papel_id: user.role_id };
    }
    return user;
  },
  async login(email, senha) {
    try {
      const response = await api.post('/auth/login', { email, senha });
      const normalizedUser = this.normalizeUser(response.data.user);
      
      // Se tem token, já salva (usuário único ou selecionou um tenant)
      if (response.data.token) {
        await AsyncStorage.setItem('@appcheckin:token', response.data.token);
        await AsyncStorage.setItem('@appcheckin:user', JSON.stringify(normalizedUser));
      } else {
        // Sem token, precisa selecionar tenant - salva dados temporariamente
        await AsyncStorage.setItem('@appcheckin:temp_user', JSON.stringify(normalizedUser));
        await AsyncStorage.setItem('@appcheckin:temp_tenants', JSON.stringify(response.data.tenants));
      }
      
      return { ...response.data, user: normalizedUser };
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  async selectTenant(tenantId) {
    try {
      const token = await AsyncStorage.getItem('@appcheckin:token');
      let response;

      if (token) {
        response = await api.post('/auth/select-tenant', { tenant_id: tenantId });
      } else {
        const tempUserJson = await AsyncStorage.getItem('@appcheckin:temp_user');
        const tempUser = tempUserJson ? JSON.parse(tempUserJson) : null;
        const payload = {
          tenant_id: tenantId,
          user_id: tempUser?.id,
          email: tempUser?.email,
        };
        response = await api.post('/auth/select-tenant-public', payload);
      }
      const normalizedUser = this.normalizeUser(response.data.user);
      
      if (response.data.token) {
        await AsyncStorage.setItem('@appcheckin:token', response.data.token);
        await AsyncStorage.setItem('@appcheckin:user', JSON.stringify(normalizedUser));
        
        // Limpar dados temporários
        await AsyncStorage.removeItem('@appcheckin:temp_user');
        await AsyncStorage.removeItem('@appcheckin:temp_tenants');
      }
      
      return { ...response.data, user: normalizedUser };
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  async logout() {
    await AsyncStorage.removeItem('@appcheckin:token');
    await AsyncStorage.removeItem('@appcheckin:user');
    await AsyncStorage.removeItem('@appcheckin:temp_user');
    await AsyncStorage.removeItem('@appcheckin:temp_tenants');
  },

  async getCurrentUser() {
    const userJson = await AsyncStorage.getItem('@appcheckin:user');
    const user = userJson ? JSON.parse(userJson) : null;
    return this.normalizeUser(user);
  },

  async getToken() {
    return await AsyncStorage.getItem('@appcheckin:token');
  },
};
