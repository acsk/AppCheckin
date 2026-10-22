import api from './api';
import AsyncStorage from '@react-native-async-storage/async-storage';

export const PAPEL_COMERCIAL = 5;
const SUPORTE_BACKUP = '@appcheckin:suporte_backup';

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

  // "Acessar como academia" (super admin): guarda a sessão do super admin e entra com o token do acesso,
  // como admin daquela academia. A API confere a sessão a cada requisição.
  async iniciarAcessoSuporte(tenantId, motivo) {
    const atualToken = await AsyncStorage.getItem('@appcheckin:token');
    const atualUser = await AsyncStorage.getItem('@appcheckin:user');
    const { data } = await api.post(`/superadmin/academias/${tenantId}/acessar`, { motivo });
    const sa = atualUser ? JSON.parse(atualUser) : {};
    await AsyncStorage.setItem(SUPORTE_BACKUP, JSON.stringify({ token: atualToken, user: atualUser }));
    await AsyncStorage.setItem('@appcheckin:token', data.token);
    await AsyncStorage.setItem('@appcheckin:user', JSON.stringify({
      ...sa,
      papel_id: 3,
      papeis: [{ id: 3, nome: 'admin' }],
      tenant_id: data.academia.id,
      acesso_suporte: { id: data.acesso.id, tenant_id: data.academia.id, tenant_nome: data.academia.nome, expira_em: data.acesso.expira_em },
    }));
    return data;
  },

  // Encerra o acesso na API (se ainda válido) e volta para a sessão do super admin.
  async encerrarAcessoSuporte() {
    try {
      await api.post('/suporte/acesso/encerrar');
    } catch {
      // acesso já expirado/encerrado: só restaura a sessão
    }
    return this.restaurarSessaoSuperAdmin();
  },

  async restaurarSessaoSuperAdmin() {
    const backup = await AsyncStorage.getItem(SUPORTE_BACKUP);
    await AsyncStorage.removeItem(SUPORTE_BACKUP);
    const { token, user } = backup ? JSON.parse(backup) : {};
    if (!token || !user) return false;
    await AsyncStorage.setItem('@appcheckin:token', token);
    await AsyncStorage.setItem('@appcheckin:user', user);
    return true;
  },

  async logout() {
    await AsyncStorage.removeItem(SUPORTE_BACKUP);
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

  // Permissões pontuais do usuário na academia do token (ex.: 'financeiro_avancado'), por token.
  // A API confere de novo em cada rota protegida; aqui é só para mostrar ou esconder ações.
  async getPermissoes() {
    const token = await AsyncStorage.getItem('@appcheckin:token');
    if (!token) return [];
    if (permissoesCache.token !== token) {
      permissoesCache = {
        token,
        promessa: api.get('/admin/minha-conta/permissoes').then((r) => r.data?.permissoes || []).catch(() => {
          permissoesCache = { token: null, promessa: null };
          return [];
        }),
      };
    }
    return permissoesCache.promessa;
  },

  async temPermissao(codigo) {
    return (await this.getPermissoes()).includes(codigo);
  },

  // Academia do token atual ({ id, nome }) ou null (super admin / sem academia). Cache por token,
  // para o cabeçalho não buscar de novo a cada tela.
  async getTenantAtual() {
    const token = await AsyncStorage.getItem('@appcheckin:token');
    if (!token) return null;
    const acesso = (await this.getCurrentUser())?.acesso_suporte;
    if (acesso) return { id: acesso.tenant_id, nome: acesso.tenant_nome };
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
let permissoesCache = { token: null, promessa: null };
