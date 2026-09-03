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
  // Catálogo global formas-pagamento (admin CRUD; config tenant já na v2)
  /^\/admin\/formas-pagamento(\/|$)/,

  // Mercado Pago webhooks
  /^\/api\/webhooks(\/|$)/,
];

/** Subpaths de /admin/matriculas ainda só na Slim. */
const SLIM_MATRICULA_PATHS = [];

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
