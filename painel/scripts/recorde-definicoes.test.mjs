import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source = await readFile(new URL('../src/utils/recordeDefinicoes.js', import.meta.url), 'utf8');
const { definitionErrors, definitionFromApi, definitionPayload, emptyRecordDefinition, filterDefinitions, recordActive } =
  await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

const rows = [
  { id: 1, nome: '100m Crawl', categoria: 'prova', modalidade_id: 2, modalidade_nome: 'Natação', ativo: '1', metricas: [{ nome: 'Tempo', codigo: 'tempo_ms', unidade: 'ms' }] },
  { id: 2, nome: 'Deadlift', categoria: 'movimento', modalidade_id: null, ativo: '0', metricas: [{ nome: 'Carga', codigo: 'peso_kg', unidade: 'kg' }] },
  { id: 3, nome: 'AMRAP', categoria: 'workout', modalidade_id: 3, modalidade_nome: 'Crossfit', ativo: true, metricas: [{ nome: 'Repetições', codigo: 'reps' }] },
];

test('busca por nome, modalidade e métricas ignora acentos e espaços externos', () => {
  assert.deepEqual(filterDefinitions(rows, { query: ' natacao ' }).map((row) => row.id), [1]);
  assert.deepEqual(filterDefinitions(rows, { query: 'PESO_KG' }).map((row) => row.id), [2]);
  assert.deepEqual(filterDefinitions(rows, { query: 'repeticoes' }).map((row) => row.id), [3]);
  assert.equal(filterDefinitions(rows, { query: 'inexistente' }).length, 0);
});

test('combina categoria, modalidade e situação, incluindo definições inativas', () => {
  assert.equal(filterDefinitions(rows).length, 3);
  assert.deepEqual(filterDefinitions(rows, { category: 'prova', modality: '2', status: 'active' }).map((row) => row.id), [1]);
  assert.deepEqual(filterDefinitions(rows, { modality: 'none', status: 'inactive' }).map((row) => row.id), [2]);
  assert.equal(filterDefinitions(rows, { category: 'prova', modality: '3' }).length, 0);
  assert.equal(recordActive('0'), false);
});

test('normaliza todas as métricas sem modificar os dados de origem nem perder ordem zero', () => {
  const original = { id: 1, nome: 'Teste', ativo: '0', ordem: 0, modalidade_id: 2, metricas: [
    { id: 8, nome: 'Reps', codigo: 'reps', ordem_comparacao: 2, obrigatoria: '0' },
    { id: 7, nome: 'Carga', codigo: 'peso', ordem_comparacao: 1, obrigatoria: '1' },
  ] };
  const form = definitionFromApi(original);
  assert.equal(form.ativo, false);
  assert.equal(form.ordem, '0');
  assert.equal(form.modalidade_id, '2');
  assert.deepEqual(form.metricas.map((metric) => metric.id), [7, 8]);
  assert.equal(form.metricas[1].obrigatoria, 0);
  assert.equal(original.metricas[0].id, 8);
});

test('valida nome, ordem, métricas obrigatórias e códigos duplicados antes da gravação', () => {
  const form = emptyRecordDefinition();
  assert.ok(definitionErrors(form).nome);
  form.nome = 'Teste';
  form.ordem = '-1';
  form.metricas = [{ codigo: 'x', nome: 'A', tipo_valor: 'decimal', direcao: 'maior_melhor' }, { codigo: 'x', nome: 'B', tipo_valor: 'decimal', direcao: 'menor_melhor' }];
  const errors = definitionErrors(form);
  assert.ok(errors.ordem);
  assert.ok(errors.metric_0_codigo);
  assert.ok(errors.metric_1_codigo);
  form.metricas = [];
  assert.ok(definitionErrors(form).metricas);
});

test('payload de edição preserva status inativo e todas as métricas e direções', () => {
  const form = { ...emptyRecordDefinition(), nome: ' Teste ', ordem: '0', ativo: false, modalidade_id: '2', metricas: [
    { id: 7, codigo: ' peso ', nome: 'Carga', tipo_valor: 'decimal', unidade: ' kg ', direcao: 'maior_melhor', obrigatoria: 1 },
    { id: 8, codigo: 'tempo_ms', nome: 'Tempo', tipo_valor: 'tempo_ms', unidade: 'ms', direcao: 'menor_melhor', obrigatoria: 0 },
  ] };
  assert.deepEqual(definitionErrors(form), {});
  const payload = definitionPayload(form, true);
  assert.equal(payload.nome, 'Teste');
  assert.equal(payload.ativo, 0);
  assert.equal(payload.ordem, 0);
  assert.equal(payload.modalidade_id, 2);
  assert.equal(payload.metricas[0].codigo, 'peso');
  assert.equal(payload.metricas[0].unidade, 'kg');
  assert.equal(payload.metricas[1].id, 8);
  assert.equal(payload.metricas[1].ordem_comparacao, 2);
  assert.equal(payload.metricas[1].direcao, 'menor_melhor');
  assert.equal(payload.metricas[1].obrigatoria, 0);
});

test('cadastro não envia status que a API de criação ignora', () => {
  const form = emptyRecordDefinition();
  form.nome = 'Novo';
  form.metricas[0].codigo = 'peso_kg';
  form.metricas[0].nome = 'Carga';
  const payload = definitionPayload(form);
  assert.equal('ativo' in payload, false);
  assert.equal(payload.modalidade_id, null);
  assert.equal(payload.descricao, null);
  assert.equal(payload.metricas[0].unidade, null);
});
