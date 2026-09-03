/**
 * Cutover strangler invertido: padrão = apiV2.
 * Só paths ainda sem paridade na Laravel ficam na Slim (api.appcheckin.com.br).
 */

function normalizeApiPath(url) {
  const raw = String(url || '');
  const withoutHost = raw.replace(/^https?:\/\/[^/?#]+/i, '');
  const pathname = withoutHost.split('?')[0].split('#')[0];
  const withSlash = pathname.startsWith('/') ? pathname : `/${pathname}`;
  const collapsed = withSlash.replace(/\/{2,}/g, '/');
  if (collapsed.length > 1 && collapsed.endsWith('/')) {
    return collapsed.slice(0, -1);
  }
  return collapsed || '/';
}

/** Subpaths de matrícula ainda só na Slim. */
const SLIM_MATRICULA_PATHS = [
  /\/simular-cancelamento$/,
  /\/cancelar-com-credito$/,
  /\/pacote-contrato(\/|$)/,
  /\/pagamentos\/\d+\/confirmar$/,
  /\/assinatura$/,
  /\/sincronizar-assinatura$/,
  /\/descontos(\/|$)/,
];

/** Prefixos/módulos inteiros ainda só na Slim. */
const SLIM_PREFIXES = [
  /^\/admin\/dashboard(\/|$)/,
  /^\/admin\/turmas(\/|$)/,
  /^\/admin\/dias(\/|$)/,
  /^\/admin\/wods(\/|$)/,
  /^\/admin\/recordes(\/|$)/,
  /^\/admin\/professores(\/|$)/,
  /^\/admin\/pacotes(\/|$)/,
  /^\/admin\/pacote-contratos(\/|$)/,
  /^\/admin\/assinaturas(\/|$)/,
  /^\/admin\/pagamentos-plano(\/|$)/,
  /^\/admin\/pagamentos(\/|$)/,
  /^\/admin\/payment-credentials(\/|$)/,
  /^\/admin\/formas-pagamento(\/|$)/,
  /^\/admin\/configuracoes(\/|$)/,
  /^\/admin\/matricula-descontos(\/|$)/,
  /^\/admin\/relatorios(\/|$)/,
  /^\/admin\/admins(\/|$)/,
  /^\/admin\/creditos(\/|$)/,
  /^\/admin\/alunos\/\d+\/creditos(\/|$)/,
  /^\/superadmin\/(?!logs(\/|$))/,
  /^\/tenant(\/|$)/,
  /^\/cep(\/|$)/,
  /^\/status(\/|$)/,
  /^\/dias(\/|$)/,
  /^\/turmas(\/|$)/,
  /^\/papeis(\/|$)/,
  /^\/api\/webhooks(\/|$)/,
];

function hasPacoteId(data) {
  if (!data || typeof data !== 'object') {
    return false;
  }
  if (typeof FormData !== 'undefined' && data instanceof FormData) {
    const value = data.get('pacote_id');
    return value !== null && value !== '' && value !== undefined;
  }
  const pacoteId = data.pacote_id;
  return pacoteId !== null && pacoteId !== undefined && pacoteId !== '';
}

function shouldUseSlim(path, verb, data) {
  if (SLIM_PREFIXES.some((re) => re.test(path))) {
    return true;
  }

  if (/^\/admin\/matriculas(\/|$)/.test(path)) {
    if (SLIM_MATRICULA_PATHS.some((re) => re.test(path))) {
      return true;
    }
    if (verb === 'post' && path === '/admin/matriculas' && hasPacoteId(data)) {
      return true;
    }
  }

  return false;
}

function shouldUseApiV2(url, method, data) {
  const path = normalizeApiPath(url);
  const verb = String(method || 'get').toLowerCase();
  return !shouldUseSlim(path, verb, data);
}

module.exports = {
  normalizeApiPath,
  shouldUseApiV2,
  shouldUseSlim,
};
