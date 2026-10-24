import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../src/utils/appConfigExcecoes.js', import.meta.url), 'utf8');
const { extrairExcecoes, prepararExcecoes } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const booleano = { tipo: 'booleano' };

test('mantém exceções desligadas, zero e texto vazio ao abrir o modal', () => {
  assert.deepEqual(extrairExcecoes({ 1: { chave: false }, 2: { chave: 0 }, 3: { chave: '' }, 4: { outra: true } }, 'chave'), { 1: false, 2: 0, 3: '' });
});

test('prepara adição, edição e remoção sem modificar o rascunho original', () => {
  const original = Object.freeze({ 1: true, 2: false });
  const local = { ...original };
  local[1] = false;
  delete local[2];
  local[3] = true;
  assert.deepEqual(original, { 1: true, 2: false });
  assert.deepEqual(prepararExcecoes(booleano, original, local), [
    { tenantId: 1, valor: false }, { tenantId: 2, valor: null }, { tenantId: 3, valor: true },
  ]);
});

test('não envia exceções inalteradas nem remove automaticamente valores iguais ao geral', () => {
  assert.deepEqual(prepararExcecoes(booleano, { 1: false }, { 1: false }), []);
  assert.deepEqual(prepararExcecoes(booleano, {}, { 1: true }), [{ tenantId: 1, valor: true }]);
});

test('normaliza inteiros e preserva zero; vazio não é remoção', () => {
  const def = { tipo: 'inteiro', min: 0, max: 100 };
  assert.deepEqual(prepararExcecoes(def, { 1: 10 }, { 1: '10', 2: '0' }), [{ tenantId: 2, valor: 0 }]);
  for (const valor of ['', '-1', '101', '1.5', 'abc']) assert.throws(() => prepararExcecoes(def, {}, { 1: valor }), /entre 0 e 100/);
});

test('valida todos os valores antes de devolver as alterações e aceita texto vazio', () => {
  const def = { tipo: 'texto', max: 3 };
  assert.throws(() => prepararExcecoes(def, {}, { 1: 'ok', 2: 'longo' }), /máximo 3/);
  assert.deepEqual(prepararExcecoes(def, { 1: 'abc' }, { 1: '' }), [{ tenantId: 1, valor: '' }]);
});
