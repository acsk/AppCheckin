import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../src/utils/descricaoTurma.js', import.meta.url), 'utf8');
const { descricaoTurmaComInicio } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

test('não repete o início quando ele já está na descrição', () => {
  assert.equal(descricaoTurmaComInicio('Sáb 15:30 às 16:30h', '15:30:00'), 'Sáb 15:30 às 16:30h');
  assert.equal(descricaoTurmaComInicio('Ter 17:30 às 18:15h', '17:30:00'), 'Ter 17:30 às 18:15h');
  assert.equal(descricaoTurmaComInicio('Turma 7h às 8h', '07:00:00'), 'Turma 7h às 8h');
});

test('mantém o início quando ele não está explícito ou não corresponde', () => {
  assert.equal(descricaoTurmaComInicio('Ter 7 às 8h', '07:00:00'), 'Ter 7 às 8h · 07:00');
  assert.equal(descricaoTurmaComInicio('Turma 17:30', '07:30:00'), 'Turma 17:30 · 07:30');
  assert.equal(descricaoTurmaComInicio('Natação', '07:00:00'), 'Natação · 07:00');
  assert.equal(descricaoTurmaComInicio('Natação', null), 'Natação');
  assert.equal(descricaoTurmaComInicio('', '07:00:00'), '07:00');
});
