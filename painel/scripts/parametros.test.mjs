import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source = await readFile(new URL('../src/utils/parametros.js', import.meta.url), 'utf8');
const { booleanoParametro, tipoParametro, validarParametro, opcoesParametro, textoParametro, normalizarBusca } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

test('valores 0 e 1 continuam numéricos quando o tipo é inteiro', () => {
  assert.equal(tipoParametro({ tipo_valor: 'integer', valor: 1 }), 'integer');
  assert.equal(booleanoParametro('false'), false);
  assert.equal(booleanoParametro('sim'), true);
  assert.equal(booleanoParametro('mensal'), null);
});
test('decimais preservam a fração e aceitam vírgula', () => {
  assert.deepEqual(validarParametro({ tipo_valor: 'decimal' }, '12,50'), { valor: 12.5 });
  assert.ok(validarParametro({ tipo_valor: 'integer' }, '12.5').erro);
});
test('valores inválidos não viram zero ou números com dígitos removidos', () => {
  for (const valor of ['', 'abc', '12abc', '1e3', 'Infinity', '-2']) assert.ok(validarParametro({ tipo_valor: 'integer' }, valor).erro);
  assert.deepEqual(validarParametro({ tipo_valor: 'integer' }, '0'), { valor: 0 });
  assert.ok(validarParametro({ tipo_valor: 'integer', max: 24 }, '25').erro);
});
test('seleções mantêm os valores técnicos das opções enviadas pela API', () => {
  assert.deepEqual(opcoesParametro({ opcoes_select: { avulso: 'Avulsa', recorrente: 'Recorrente' } }), [{ value: 'avulso', label: 'Avulsa' }, { value: 'recorrente', label: 'Recorrente' }]);
  assert.deepEqual(opcoesParametro({ opcoes_select: '["pix","boleto"]' }), [{ value: 'pix', label: 'pix' }, { value: 'boleto', label: 'boleto' }]);
  assert.equal(tipoParametro({ tipo_valor: 'string', valor: 'pix', opcoes_select: ['pix'] }), 'select');
});
test('JSON preserva a estrutura e exige conteúdo válido antes do envio', () => {
  const param = { tipo_valor: 'json', valor: { peso: 25 } };
  assert.deepEqual(JSON.parse(textoParametro(param)), { peso: 25 });
  assert.ok(validarParametro(param, '{peso: 25}').erro);
  assert.equal(validarParametro(param, '{"peso":25}').valor, '{"peso":25}');
});
test('busca ignora acentos, maiúsculas e espaços nas extremidades', () => {
  assert.equal(normalizarBusca('  Prospecção  '), 'prospeccao');
});
