/**
 * Strangler fig: padrão = apiV2 (apiv2.appcheckin.com.br/v2).
 * Rotas sem paridade na Laravel ficam na Slim (api.appcheckin.com.br).
 *
 * Denylist baseada nos services do painel — atualizar conforme portar módulos.
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

/**
 * Paths/prefixos completos ainda só na Slim.
 * Para portar: remova a entrada correspondente desta lista.
 */
const SLIM_ONLY = [
  // WOD / Recordes
  /^\/admin\/wods(\/|$)/,
  /^\/admin\/recordes(\/|$)/,

  // Financeiro / pagamentos (formas ainda na Slim)
  /^\/admin\/formas-pagamento(\/|$)/,
  /^\/admin\/configuracoes(\/|$)/,

  // Parâmetros tenant
  /^\/admin\/parametros(\/|$)/,

  // Superadmin (tudo, exceto logs e assinaturas migradas)
  /^\/superadmin\/academias(\/|$)/,
  /^\/superadmin\/usuarios(\/|$)/,
  /^\/superadmin\/papeis(\/|$)/,
  /^\/superadmin\/contratos(\/|$)/,
  /^\/superadmin\/pagamentos-contrato(\/|$)/,
  /^\/superadmin\/planos(\/|$)/,

  // Papéis raiz
  /^\/papeis(\/|$)/,

  // Mercado Pago webhooks
  /^\/api\/webhooks(\/|$)/,

  // CEP / status auxiliar
  /^\/cep(\/|$)/,
  /^\/status(\/|$)/,
];

/** Subpaths de /admin/matriculas ainda só na Slim. */
const SLIM_MATRICULA_PATHS = [];

function hasPacoteId(data) {
  if (!data || typeof data !== 'object') return false;
  if (typeof FormData !== 'undefined' && data instanceof FormData) {
    const v = data.get('pacote_id');
    return v !== null && v !== '' && v !== undefined;
  }
  const v = data.pacote_id;
  return v !== null && v !== undefined && v !== '';
}

function shouldUseApiV2(url, method, data) {
  const path = normalizeApiPath(url);
  const verb = String(method || 'get').toLowerCase();

  if (SLIM_ONLY.some((re) => re.test(path))) return false;

  if (/^\/admin\/matriculas(\/|$)/.test(path)) {
    if (SLIM_MATRICULA_PATHS.some((re) => re.test(path))) return false;
  }

  return true;
}

module.exports = { normalizeApiPath, shouldUseApiV2 };
