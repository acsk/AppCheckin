import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source = await readFile(new URL('../src/utils/excluirMatricula.js', import.meta.url), 'utf8');
const { resumoExclusao, confirmaExclusao } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

test('separa registros apagados de assinaturas que apenas serão desvinculadas', () => {
  const r = resumoExclusao({ resumo: { total_pagamentos_plano: 2, total_pagamentos_provedor: 1, total_assinaturas: 3, total_assinaturas_mercadopago: 1, valor_total_pagamentos_plano: 150 } });
  assert.equal(r.excluidos, 4);
  assert.equal(r.assinaturas, 3);
  assert.equal(r.exigeNumero, true);
});

test('pagamentos do provedor também exigem confirmação pelo número da matrícula', () => {
  const r = resumoExclusao({ pagamentos_mercadopago: [{ transaction_amount: '120.50' }] });
  assert.equal(r.valorProvedor, 120.5);
  assert.equal(r.exigeNumero, true);
  assert.equal(confirmaExclusao(208, '209', r.exigeNumero), false);
  assert.equal(confirmaExclusao(208, '', r.exigeNumero), false);
  assert.equal(confirmaExclusao(208, '208', r.exigeNumero), true);
  assert.equal(confirmaExclusao(208, ' #208 ', r.exigeNumero), true);
  assert.equal(confirmaExclusao(208, '208abc', r.exigeNumero), false);
});

test('calcula os totais pelos registros quando o resumo não está disponível', () => {
  const r = resumoExclusao({ pagamentos_plano: [{ valor: '150' }, { valor: '50' }], assinaturas: [{ id: 1 }] });
  assert.equal(r.pagamentos, 2);
  assert.equal(r.valorPlano, 200);
  assert.equal(r.assinaturas, 1);
  assert.equal(r.excluidos, 2);
});

test('usa os totais explícitos da prévia mesmo quando são zero', () => {
  const r = resumoExclusao({ resumo: { total_pagamentos_plano: 0, valor_total_pagamentos_plano: 0 }, pagamentos_plano: [{ valor: 150 }] });
  assert.equal(r.pagamentos, 0);
  assert.equal(r.exigeNumero, false);
});

test('matrícula sem valores não exige digitação, mas ainda precisa ter um ID', () => {
  const r = resumoExclusao();
  assert.equal(r.excluidos, 0);
  assert.equal(r.exigeNumero, false);
  assert.equal(confirmaExclusao(208, '', r.exigeNumero), true);
  assert.equal(confirmaExclusao(undefined, '', r.exigeNumero), false);
});
