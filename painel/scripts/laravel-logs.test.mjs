import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

// O painel usa módulos ES via Metro. Carrega este utilitário puro sem alterar
// o tipo de módulo do projeto nem adicionar dependências para os testes.
const source = await readFile(new URL('../src/utils/laravelLogs.js', import.meta.url), 'utf8');
const { parseLaravelLines, filterLogEntries, groupLogEntries, logLevel, rawLogText } =
  await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const rows = (text) => text.split('\n').map((texto) => ({ texto, nivel: null, timestamp: null }));

const multiline = [
  '[2026-10-20 09:00:00] production.ERROR: Falha no pagamento',
  '[stacktrace]',
  '#0 /srv/apiV2/app/Services/Pagamento.php(42): App\\Services\\Pagamento->cobrar()',
  '#1 /srv/apiV2/vendor/laravel/Framework.php(15): Illuminate\\Router->run()',
  '#2 {main}',
  '[2026-10-20 09:00:01] production.INFO: Finalizado {"tenant_id":7}',
].join('\n');

test('reúne linhas de contexto e stack trace sem perder o texto original', () => {
  const entries = parseLaravelLines(rows(multiline));
  assert.equal(entries.length, 2);
  assert.equal(entries[0].trace.length, 3);
  assert.equal(entries[0].trace[0].line, 42);
  assert.equal(entries[0].trace[1].vendor, true);
  assert.equal(entries[0].trace[2].call, '{main}');
  assert.equal(entries[1].context.tenant_id, 7);
  assert.equal(rawLogText(entries), multiline);
});

test('extrai contexto JSON, exceção e trace serializado por Monolog', () => {
  const exception = '[object] (App\\PaymentException(code: 0): Falhou at /srv/apiV2/app/Payment.php:44)\n[stacktrace]\n#0 /srv/apiV2/app/Payment.php(44): App\\Payment->run()\n#1 /srv/apiV2/vendor/Router.php(9): Router->run()';
  const raw = `[2026-10-20 10:00:00] production.ERROR: Cobrança falhou ${JSON.stringify({ userId: 3, exception })}`;
  const [entry] = parseLaravelLines(rows(raw));
  assert.equal(entry.exception, 'App\\PaymentException');
  assert.equal(entry.file, '/srv/apiV2/app/Payment.php');
  assert.equal(entry.line, 44);
  assert.equal(entry.trace.length, 2);
  assert.deepEqual(entry.context, { userId: 3 });
  assert.equal(entry.raw, raw);
});

test('aceita quebras de linha literais no JSON da exceção, sem duplicar frames', () => {
  const raw = '[2026-10-20 10:00:00] production.ERROR: Falhou {"exception":"[object] (RuntimeException(code: 0): Falhou at /srv/app/Test.php:1)\n[stacktrace]\n#0 /srv/app/Test.php(1): run()"}';
  const [entry] = parseLaravelLines(rows(raw));
  assert.equal(entry.exception, 'RuntimeException');
  assert.equal(entry.trace.length, 1);
  assert.equal(entry.raw, raw);
});

test('normaliza severidades críticas e notices, mantendo níveis desconhecidos separados', () => {
  ['error', 'critical', 'alert', 'emergency'].forEach((level) => assert.equal(logLevel(level), 'error'));
  assert.equal(logLevel('notice'), 'info');
  assert.equal(logLevel('WARNING'), 'warning');
  assert.equal(logLevel(null), 'other');
  assert.equal(logLevel('constructor'), 'other');
});

test('não atribui data ou severidade inventadas ao início truncado de um arquivo', () => {
  const entries = parseLaravelLines(rows('#12 /srv/vendor/Router.php(1): run()\n[2026-10-20 11:00:00] local.DEBUG: Teste'));
  assert.equal(entries.length, 2);
  assert.equal(entries[0].at, '');
  assert.equal(logLevel(entries[0].level), 'other');
  assert.equal(entries[1].env, 'local');
});

test('busca no trace preserva a mensagem, o contexto e os demais frames', () => {
  const entries = parseLaravelLines(rows(multiline));
  const result = filterLogEntries(entries, 'error', 'Pagamento.php');
  assert.equal(result.length, 1);
  assert.equal(result[0].message, 'Falha no pagamento');
  assert.equal(result[0].trace.length, 3);
  assert.equal(filterLogEntries(entries, 'warning').length, 0);
  assert.equal(filterLogEntries(entries, 'info', 'TENANT_ID').length, 1);
});

test('agrupa mensagens iguais com contextos diferentes e mantém a ocorrência mais recente', () => {
  const entries = parseLaravelLines(rows('[2026-10-20 11:00:00] production.ERROR: Falhou {"userId":1}\n[2026-10-20 11:01:00] production.ERROR: Falhou {"userId":2}\n[2026-10-20 11:02:00] production.WARNING: Atenção'));
  const groups = groupLogEntries(entries);
  assert.equal(groups.length, 2);
  assert.equal(groups[0].count, 2);
  assert.equal(groups[0].latest.context.userId, 2);
  assert.equal(groups[0].first, '2026-10-20T11:00:00');
  assert.equal(entries.length, 3);
});

test('não funde mensagens com códigos de erro diferentes ou mesmo prefixo longo', () => {
  const prefix = 'x'.repeat(200);
  const entries = parseLaravelLines(rows(`[2026-10-20 11:00:00] production.ERROR: HTTP 404\n[2026-10-20 11:01:00] production.ERROR: HTTP 500\n[2026-10-20 11:02:00] production.ERROR: ${prefix}A\n[2026-10-20 11:03:00] production.ERROR: ${prefix}B`));
  assert.equal(groupLogEntries(entries).length, 4);
});

test('texto sem JSON válido e trechos vazios continuam legíveis', () => {
  const raw = '[2026-10-20 11:00:00] local.INFO: Resposta {não é JSON} <script>alert(1)</script>';
  const [entry] = parseLaravelLines(rows(raw));
  assert.deepEqual(entry.context, {});
  assert.equal(entry.message, 'Resposta {não é JSON} <script>alert(1)</script>');
  assert.equal(entry.raw, raw);
  assert.deepEqual(parseLaravelLines([]), []);
  assert.deepEqual(groupLogEntries([]), []);
});
