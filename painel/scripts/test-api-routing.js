const { shouldUseApiV2, normalizeApiPath } = require('../src/config/apiRouting');

const cases = [
  ['/auth/login', 'post', null, true, 'login vai para v2'],
  ['/auth/select-tenant', 'post', null, true, 'select-tenant vai para v2'],
  ['/me', 'get', null, true, 'me vai para v2'],
  ['/planos', 'get', null, true, 'listagem de planos vai para v2'],
  ['/admin/modalidades', 'get', null, true, 'modalidades vai para v2'],
  ['/admin/alunos', 'get', null, true, 'alunos vai para v2'],
  ['/admin/alunos/12/creditos', 'get', null, false, 'créditos de aluno ficam na Slim'],
  ['/admin/planos/3', 'put', null, true, 'CRUD de planos vai para v2'],
  ['/admin/matriculas', 'get', null, true, 'listagem de matrículas vai para v2'],
  ['/admin/matriculas/164', 'get', null, true, 'detalhe de matrícula vai para v2'],
  ['/admin/matriculas/164/pagamentos', 'get', null, true, 'pagamentos da matrícula vão para v2'],
  ['/admin/matriculas/contas/9/baixa', 'post', null, true, 'baixa de conta vai para v2'],
  ['/admin/matriculas', 'post', { plano_id: 7 }, true, 'criar matrícula de plano vai para v2'],
  ['/admin/matriculas', 'post', { pacote_id: 2 }, false, 'criar matrícula de pacote fica na Slim'],
  ['/admin/matriculas/164/simular-cancelamento', 'get', null, false, 'simular cancelamento fica na Slim'],
  ['/admin/matriculas/164/cancelar-com-credito', 'post', null, false, 'cancelar com crédito fica na Slim'],
  ['/admin/matriculas/164/assinatura', 'get', null, false, 'assinatura da matrícula fica na Slim'],
  ['/admin/turmas', 'get', null, false, 'turmas ficam na Slim'],
  ['/admin/wods', 'get', null, false, 'wods ficam na Slim'],
  ['/admin/dashboard', 'get', null, false, 'dashboard fica na Slim'],
  ['/superadmin/academias', 'get', null, false, 'superadmin academias fica na Slim'],
  ['/superadmin/logs', 'get', null, true, 'logs Laravel SA vai para v2'],
  ['/cep/57000000', 'get', null, false, 'CEP fica na Slim'],
];

let failed = 0;
for (const [url, method, data, expected, label] of cases) {
  const got = shouldUseApiV2(url, method, data);
  if (got !== expected) {
    console.error(`FAIL: ${label} (${method.toUpperCase()} ${url}) esperado=${expected} obtido=${got}`);
    failed += 1;
  }
}

if (normalizeApiPath('https://api.appcheckin.com.br/admin/alunos?x=1') !== '/admin/alunos') {
  console.error('FAIL: normalizeApiPath com host+query');
  failed += 1;
}

if (failed === 0) {
  console.log(`OK apiRouting (${cases.length} casos)`);
  process.exit(0);
}
process.exit(1);
