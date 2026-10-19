import api from './api';

const BASE = '/superadmin/planos-sistema';

export const TIPOS_COBRANCA = [
  { value: 'gratuito', label: 'Gratuito' },
  { value: 'fixo', label: 'Valor fixo' },
  { value: 'por_aluno', label: 'Por aluno ativo' },
];

const formatBRL = (valor) =>
  `R$ ${Number(valor || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/** Planos gravados antes de tipo_cobranca existir: valor 0 = gratuito, senão fixo. */
export const tipoCobrancaDoPlano = (plano) => {
  if (plano?.tipo_cobranca && TIPOS_COBRANCA.some((t) => t.value === plano.tipo_cobranca)) {
    return plano.tipo_cobranca;
  }
  return Number(plano?.valor || 0) > 0 ? 'fixo' : 'gratuito';
};

export const formatarPrecoPlano = (plano) => {
  switch (tipoCobrancaDoPlano(plano)) {
    case 'gratuito':
      return 'Grátis';
    case 'por_aluno':
      return `${formatBRL(plano.valor_por_aluno)} por aluno/mês`;
    default:
      return `${formatBRL(plano.valor)}/mês`;
  }
};

const normalizarErro = (error, fallback) => {
  const data = error.response?.data;
  if (!data) return { error: error.message || fallback, message: error.message || fallback };
  const message = data.message || data.error || (Array.isArray(data.errors) ? data.errors.join(', ') : null) || fallback;
  return { ...data, message, error: data.error || message };
};

const request = async (fn, fallback) => {
  try {
    const response = await fn();
    return response.data;
  } catch (error) {
    console.error(fallback, error);
    throw normalizarErro(error, fallback);
  }
};

class PlanosSistemaService {
  listar(apenasAtivos = false, apenasAtuais = false) {
    const params = {};
    if (apenasAtivos) params.ativos = 'true';
    if (apenasAtuais) params.apenas_atuais = 'true';
    return request(() => api.get(BASE, { params }), 'Erro ao listar planos do sistema');
  }

  listarDisponiveis() {
    return request(() => api.get(`${BASE}/disponiveis`), 'Erro ao listar planos disponíveis');
  }

  buscarPorId(id) {
    return request(() => api.get(`${BASE}/${id}`), 'Erro ao buscar plano');
  }

  criar(planoData) {
    return request(() => api.post(BASE, planoData), 'Erro ao criar plano');
  }

  atualizar(id, planoData) {
    return request(() => api.put(`${BASE}/${id}`, planoData), 'Erro ao atualizar plano');
  }

  marcarHistorico(id) {
    return request(() => api.post(`${BASE}/${id}/marcar-historico`, {}), 'Erro ao marcar plano como histórico');
  }

  desativar(id) {
    return request(() => api.delete(`${BASE}/${id}`), 'Erro ao desativar plano');
  }

  listarAcademias(id) {
    return request(() => api.get(`${BASE}/${id}/academias`), 'Erro ao listar academias do plano');
  }
}

export default new PlanosSistemaService();
