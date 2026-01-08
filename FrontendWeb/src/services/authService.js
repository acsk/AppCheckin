import api from './api';
import AsyncStorage from '@react-native-async-storage/async-storage';

export const authService = {
  async login(email, senha) {
    try {
      const response = await api.post('/auth/login', { email, senha });
      
      if (response.data.token) {
        await AsyncStorage.setItem('@appcheckin:token', response.data.token);
        await AsyncStorage.setItem('@appcheckin:user', JSON.stringify(response.data.user));
      }
      
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  async logout() {
    await AsyncStorage.removeItem('@appcheckin:token');
    await AsyncStorage.removeItem('@appcheckin:user');
  },

  async getCurrentUser() {
    const userJson = await AsyncStorage.getItem('@appcheckin:user');
    return userJson ? JSON.parse(userJson) : null;
  },

  async getToken() {
    return await AsyncStorage.getItem('@appcheckin:token');
  },
};
