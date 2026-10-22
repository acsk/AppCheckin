import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source = await readFile(new URL('../src/utils/usuarios.js', import.meta.url), 'utf8');
const { usuarioAtivo, usuarioParaLayout, usuarioParaApi } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
test('status booleano, inteiro e string são interpretados sem ativar a string zero', () => {
  for (const ativo of [true, 1, '1']) assert.equal(usuarioAtivo({ ativo }), true);
  for (const ativo of [false, 0, '0']) assert.equal(usuarioAtivo({ ativo }), false);
  assert.equal(usuarioAtivo({ status: 'inativo', ativo: 1 }), false);
});
test('preserva os múltiplos papéis e não inventa último acesso', () => {
  const u = usuarioParaLayout({ id: 10, nome: 'ANA SILVA', papeis: [1, '2', 1, 6], ativo: 1, tenant: { nome: 'Academia' } });
  assert.deepEqual(u.types, ['aluno', 'professor', 'financeiro']);
  assert.equal(u.lastAccess, null);
  assert.equal(u.academy, 'Academia');
});
test('edição sem nova senha não envia senha nem altera status pelo endpoint de perfil', () => {
  const p = usuarioParaApi({ name: 'Ana Silva', email: 'ana@example.com', password: null, active: false }, 2);
  assert.equal('senha' in p, false);
  assert.equal('ativo' in p, false);
  assert.equal(p.papel_id, 2);
});
test('mapeia os campos de endereço e nova senha para a API', () => {
  const p = usuarioParaApi({ name: 'Ana', email: 'ana@example.com', password: 'teste123', address: { cep: '57000000', street: 'Rua A', number: '10', district: 'Centro', city: 'Maceió', uf: 'AL' } });
  assert.equal(p.senha, 'teste123'); assert.equal(p.logradouro, 'Rua A'); assert.equal(p.cidade, 'Maceió'); assert.equal(p.estado, 'AL');
  assert.equal(p.complemento, '');
});
