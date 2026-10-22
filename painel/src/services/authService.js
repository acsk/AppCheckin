import api from './api';
import AsyncStorage from '@react-native-async-storage/async-storage';

export const PAPEL_COMERCIAL = 5;

export const authService = {
  normalizeUser(user) {
    if (!user) return user;
    const normalized = user.papel_id == null && user.role_id != null
      ? { ...user, papel_id: user.role_id }
      : { ...user };
    const effectivePapelId = this.getEffectivePapelId(normalized);
    if (effectivePapelId !== normalized.papel_id) {
      return { ...normalized, papel_id: effectivePapelId };
    }
    return normalized;
  },

  // Papel efetivo = maior papel hierárquico (1 aluno … 4 super admin). Comercial (5) é funcional e
  // fica de fora, senão um admin que também é comercial perderia o menu de admin.
  getEffectivePapelId(user) {
    if (!user) return 1;
    const ids = this.papelIds(user);
    const hierarquicos = ids.filter((id) => id !== PAPEL_COMERCIAL);
    if (hierarquicos.length) return Math.max(...hierarquicos);
    return ids.includes(PAPEL_COMERCIAL) ? PAPEL_COMERCIAL : 1;
  },

  papelIds(user) {
    if (!user) return [];
    const ids = [];
    if (user.papel_id != null) ids.push(Number(user.papel_id));
    if (Array.isArray(user.papeis)) {
      user.papeis.forEach((p) => {
        if (p?.id != null) ids.push(Number(p.id));
      });
    }
    return ids;
  },

  hasPapel(user, papelId) {
    return this.papelIds(user).includes(papelId);
  },

  /** Administrador ou equipe comercial: acessa o módulo Prospecção. */
  podeProspeccao(user) {
    return [3, 4].includes(this.getEffectivePapelId(user)) || this.hasPapel(user, PAPEL_COMERCIAL);
  },

  async isSuperAdmin(user = null) {
    const current = user || await this.getCurrentUser();
    if (!current) return false;
    if (this.getEffectivePapelId(current) === 4) return true;

    const token = await this.getToken();
    if (!token) return false;

    try {
      const parts = token.split('.');
      if (parts.length !== 3) return false;
      const payload = JSON.parse(atob(parts[1].replace(/-/g, '+').replace(/_/g, '/')));
      return payload.is_super_admin === true;
    } catch {
      return false;
    }
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
      const tempUserJson = await AsyncStorage.getItem('@appcheckin:temp_user');
      const tempUser = tempUserJson ? JSON.parse(tempUserJson) : null;
      const papeis = response.data.user?.papeis || tempUser?.papeis;
      const normalizedUser = this.normalizeUser({
        ...response.data.user,
        papeis,
      });

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

  async requestPasswordRecovery(email) {
    try {
      const response = await api.post('/auth/password-recovery/request', { email });
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
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

  // Academia do token atual ({ id, nome }) ou null (super admin / sem academia). Cache por token,
  // para o cabeçalho não buscar de novo a cada tela.
  async getTenantAtual() {
    const token = await AsyncStorage.getItem('@appcheckin:token');
    if (!token) return null;
    if (tenantAtualCache.token === token) return tenantAtualCache.tenant;
    const response = await api.get('/auth/tenants');
    const atualId = Number(response.data?.current_tenant_id) || null;
    const vinculo = (response.data?.tenants || []).find((t) => Number(t.tenant?.id) === atualId);
    const tenant = vinculo ? { id: atualId, nome: vinculo.tenant.nome } : null;
    tenantAtualCache = { token, tenant };
    return tenant;
  },
};

let tenantAtualCache = { token: null, tenant: null };
