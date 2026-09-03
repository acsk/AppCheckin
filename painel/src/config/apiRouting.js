/**
 * Cutover strangler: só rotas já portadas na v2 vão para apiv2.
 * O restante permanece em api.appcheckin.com.br (Slim).
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

const SLIM_MATRICULA_PATHS = [
  /\/simular-cancelamento$/,
  /\/cancelar-com-credito$/,
  /\/pacote-contrato(\/|$)/,
  /\/pagamentos\/\d+\/confirmar$/,
  /\/assinatura$/,
  /\/sincronizar-assinatura$/,
  /\/descontos(\/|$)/,
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

function shouldUseApiV2(url, method, data) {
  const path = normalizeApiPath(url);
  const verb = String(method || 'get').toLowerCase();

  if (/^\/auth(\/|$)/.test(path)) return true;
  if (path === '/me' || path.startsWith('/me/')) return true;
  if (/^\/planos(\/|$)/.test(path)) return true;
  if (/^\/admin\/modalidades(\/|$)/.test(path)) return true;
  if (/^\/admin\/assinatura-frequencias(\/|$)/.test(path)) return true;
  if (/^\/admin\/planos(\/|$)/.test(path)) return true;

  if (/^\/admin\/alunos(\/|$)/.test(path)) {
    return !/\/creditos(\/|$)/.test(path);
  }

  if (/^\/admin\/matriculas(\/|$)/.test(path)) {
    if (SLIM_MATRICULA_PATHS.some((re) => re.test(path))) return false;
    if (verb === 'post' && path === '/admin/matriculas' && hasPacoteId(data)) {
      return false;
    }
    return true;
  }

  if (/^\/superadmin\/logs(\/|$)/.test(path)) return true;

  return false;
}

module.exports = {
  normalizeApiPath,
  shouldUseApiV2,
};
