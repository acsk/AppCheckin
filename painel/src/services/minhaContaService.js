import api from './api';

const erro = (error, fallback) => {
  const d = error?.response?.data || {};
  const errors = Array.isArray(d.errors) ? d.errors : [];
  return { message: errors.length ? errors.join(', ') : d.message || d.error || fallback, errors, status: error?.response?.status, code: d.code };
};

// Minha Conta (painel admin): perfil e senha do usuário logado, dados da escola e do CNPJ.
// Plano e assinatura ficam em meuContratoService.
const minhaContaService = {
  async perfil() {
    try {
      return (await api.get('/admin/minha-conta/perfil')).data.perfil;
    } catch (error) {
      throw erro(error, 'Não foi possível carregar o perfil');
    }
  },

  async salvarPerfil({ nome, telefone, email, senhaAtual }) {
    try {
      return (await api.put('/admin/minha-conta/perfil', { nome, telefone, email, senha_atual: senhaAtual || undefined })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível salvar o perfil');
    }
  },

  async alterarSenha({ senhaAtual, novaSenha, confirmacao }) {
    try {
      return (await api.put('/admin/minha-conta/senha', { senha_atual: senhaAtual, nova_senha: novaSenha, confirmacao_senha: confirmacao })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível alterar a senha');
    }
  },

  async escola() {
    try {
      return (await api.get('/admin/minha-conta/escola')).data.escola;
    } catch (error) {
      throw erro(error, 'Não foi possível carregar os dados da escola');
    }
  },

  async salvarEscola(dados) {
    try {
      return (await api.put('/admin/minha-conta/escola', dados)).data;
    } catch (error) {
      throw erro(error, 'Não foi possível salvar os dados da escola');
    }
  },

  async acessosSuporte() {
    try {
      return (await api.get('/admin/minha-conta/acessos-suporte')).data.acessos;
    } catch (error) {
      throw erro(error, 'Não foi possível carregar os acessos do suporte');
    }
  },

  async cnpj() {
    try {
      return (await api.get('/admin/minha-conta/cnpj')).data.cnpj;
    } catch (error) {
      throw erro(error, 'Não foi possível carregar os dados do CNPJ');
    }
  },

  async salvarCnpj(dados) {
    try {
      return (await api.put('/admin/minha-conta/cnpj', dados)).data;
    } catch (error) {
      throw erro(error, 'Não foi possível salvar os dados do CNPJ');
    }
  },
};

export default minhaContaService;
