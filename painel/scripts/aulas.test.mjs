import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source = await readFile(new URL('../src/utils/aulas.js', import.meta.url), 'utf8');
const { officialAula, aulaPayload, localISO, weekStart, replicationPayload, isEnabled } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const raw = { id: 1, dia_id: 5, dia_data: '2026-10-20', professor_id: 7, professor_nome: 'Maria', modalidade_id: 2, modalidade_nome: 'CrossFit', horario_inicio: '06:00:00', horario_fim: '07:00:00', limite_alunos: '20', alunos_count: '8', total_checkins: 3, ativo: '1', checkin_bloqueado: '0', tolerancia_antes_minutos: 480, tolerancia_minutos: 10 };
const values = { id: 1, modalityId: '2', teacherId: '7', date: '2026-10-20', start: '06:00', end: '07:00', capacity: 20, openBefore: 480, lateAfter: 10, closeBefore: null, cancelBefore: null, active: true };
const payload = input => aulaPayload(input, [{ id: 2, nome: 'CrossFit' }], [{ id: 7, nome: 'Maria' }], 5);
test('usa check-ins em vez de inscrições fixas e normaliza os status da API', () => {
  const aula = officialAula(raw);
  assert.equal(aula.booked, 3); assert.equal(aula.active, true); assert.equal(aula.checkinLocked, false);
  assert.equal(aula.start, '06:00'); assert.equal(aula.date, '2026-10-20'); assert.equal(aula.teacherId, '7');
  for (const value of [false, 0, '0', null, undefined]) assert.equal(isEnabled(value), false);
  for (const value of [true, 1, '1']) assert.equal(isEnabled(value), true);
});
test('check-ins não são mascarados por zero inscrições nem substituídos por inscrições', () => {
  assert.equal(officialAula({ ...raw, alunos_count: 0, total_checkins: '9' }).booked, 9);
  assert.equal(officialAula({ ...raw, alunos_count: 8, total_checkins: 0 }).booked, 0);
  assert.equal(officialAula({ ...raw, alunos_count: 8, total_checkins: undefined }).booked, 0);
});
test('preserva regras opcionais nulas e defaults de abertura/atraso', () => {
  const aula = officialAula({ ...raw, tolerancia_antes_minutos: null, tolerancia_minutos: null });
  assert.equal(aula.openBefore, 480); assert.equal(aula.lateAfter, 10); assert.equal(aula.closeBefore, '');
  assert.equal(payload(values).tolerancia_antes_checkin_minutos, null);
  assert.equal(payload(values).tolerancia_cancelamento_minutos, null);
});
test('gera contrato completo de criação sem depender de UTC', () => {
  assert.deepEqual(payload({ ...values, id: undefined }), {
    nome: 'CrossFit - 06:00 - Maria', modalidade_id: 2, professor_id: 7, dia_id: 5,
    horario_inicio: '06:00:00', horario_fim: '07:00:00', limite_alunos: 20, ativo: 1,
    tolerancia_antes_minutos: 480, tolerancia_minutos: 10, tolerancia_antes_checkin_minutos: null, tolerancia_cancelamento_minutos: null,
  });
  assert.equal(localISO(new Date(2026, 9, 20, 23, 59)), '2026-10-20');
});
test('edição mantém zeros explícitos e não move a aula de dia', () => {
  const data = payload({ ...values, active: false, closeBefore: 0, cancelBefore: 0 });
  assert.equal(data.ativo, 0); assert.equal(data.tolerancia_antes_checkin_minutos, 0); assert.equal(data.tolerancia_cancelamento_minutos, 0);
  assert.equal('dia_id' in data, false);
});
test('valida horários, capacidade e relação abertura/fechamento', () => {
  for (const change of [{ start: '25:00' }, { end: '05:00' }, { capacity: 1.5 }, { capacity: 0 }, { openBefore: -1 }, { lateAfter: 1441 }, { closeBefore: 480 }, { teacherId: '999' }]) {
    assert.throws(() => payload({ ...values, ...change }));
  }
  assert.throws(() => aulaPayload({ ...values, id: undefined }, [{ id: 2 }], [{ id: 7 }], null), /dia selecionado/);
});
test('semana começa no domingo inclusive na virada do mês e ano', () => {
  assert.equal(weekStart('2026-10-20'), '2026-10-18');
  assert.equal(weekStart('2027-01-01'), '2026-12-27');
  assert.equal(weekStart('2026-10-18'), '2026-10-18');
});
test('replicação diária traduz domingo zero para contrato um a sete', () => {
  assert.deepEqual(replicationPayload({ mode: 'dia', modalityId: '2', month: '2026-11', weekdays: [0, 2, 6] }, 5), {
    dia_id: 5, periodo: 'custom', mes: '2026-11', dias_semana: [1, 3, 7], modalidade_id: 2,
  });
});
test('replicação semanal usa endpoint próprio e múltiplos meses', () => {
  assert.deepEqual(replicationPayload({ mode: 'semana', modalityId: '2', sourceWeek: '2026-10-20', months: ['2026-11', '2026-12'] }), {
    semana_data: '2026-10-18', meses_destino: ['2026-11', '2026-12'], modalidade_id: 2,
  });
  assert.throws(() => replicationPayload({ mode: 'dia', modalityId: '2', month: '2026-11', weekdays: [] }, 5));
  assert.throws(() => replicationPayload({ mode: 'semana', modalityId: '2', sourceWeek: '2026-10-20', months: [] }));
});
