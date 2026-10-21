import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source = await readFile(new URL('../src/utils/prospeccao.js', import.meta.url), 'utf8');
const { aplicarModelo, primeiroNome, tempoInativo, dataBR, dataHoraBR, paraApiDataHora, fotoUrl, mesCurto } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const vars = { nome: 'MARIA OLIVEIRA', modalidade: 'Natação', plano_anterior: 'Mensal 3x', estabelecimento: 'Aqua Masters' };
test('preenche as variáveis do modelo com o primeiro nome formatado', () => {
  const { texto, faltando } = aplicarModelo('Olá, {nome}! Aqui é da equipe do {estabelecimento}. Volte às aulas de {modalidade} ({plano_anterior}).', vars);
  assert.equal(texto, 'Olá, Maria! Aqui é da equipe do Aqua Masters. Volte às aulas de Natação (Mensal 3x).');
  assert.deepEqual(faltando, []);
  assert.equal(primeiroNome('  joão  da silva'), 'João');
});
test('variável sem valor fica vazia e é informada, sem inventar dado', () => {
  const { texto, faltando } = aplicarModelo('Olá, {nome}! Aulas de {modalidade}.', { nome: '', modalidade: null });
  assert.equal(texto, 'Olá! Aulas de.');
  assert.deepEqual(faltando.sort(), ['modalidade', 'nome']);
});
test('variável desconhecida é mantida como está', () => {
  assert.equal(aplicarModelo('Oi {apelido}', vars).texto, 'Oi {apelido}');
});
test('formata tempo de inatividade e datas', () => {
  assert.equal(tempoInativo(1), '1 dia'); assert.equal(tempoInativo(45), '45 dias'); assert.equal(tempoInativo(208), '6 meses');
  assert.equal(tempoInativo(800), '2 anos e 2 meses'); assert.equal(tempoInativo(-3), '0 dias');
  assert.equal(dataBR('2026-03-27'), '27/03/2026'); assert.equal(dataBR(null), '—');
  assert.equal(dataHoraBR('2026-10-21 15:30:00'), '21/10/2026 às 15:30');
  assert.equal(paraApiDataHora('2026-10-22T09:15'), '2026-10-22 09:15:00'); assert.equal(paraApiDataHora(''), '');
  assert.equal(mesCurto('2026-03'), 'mar/26');
});
test('monta a URL da foto a partir do caminho salvo', () => {
  assert.equal(fotoUrl('/uploads/fotos/a.jpg', 'http://localhost:9090/v2/'), 'http://localhost:9090/v2/uploads/fotos/a.jpg');
  assert.equal(fotoUrl('https://cdn/x.jpg', 'http://b'), 'https://cdn/x.jpg');
  assert.equal(fotoUrl(null, 'http://b'), undefined);
});
