import assert from 'node:assert/strict';
import test from 'node:test';
import { precisaRevisaoMigracao, metodosAssinaturaDisponiveis } from '../src/utils/cicloPagamento.ts';

test('aluno novo segue revisão de compra, sem crédito', () => {
  assert.equal(precisaRevisaoMigracao({}, 2), false);
});
test('troca de plano usa a simulação oficial de crédito', () => {
  assert.equal(precisaRevisaoMigracao({ pode_migrar: true }, 2), true);
});
test('troca de ciclo no plano ativo usa migração; o mesmo ciclo mantém compra/renovação', () => {
  const plano = { is_plano_atual: true, matricula_ativa: { status_codigo: 'ativa', plano_ciclo_id: 1 } };
  assert.equal(precisaRevisaoMigracao(plano, 2), true);
  assert.equal(precisaRevisaoMigracao(plano, 1), false);
});
test('matrícula pendente não gera crédito de migração', () => {
  assert.equal(precisaRevisaoMigracao({ is_plano_atual: true, matricula_ativa: { status_codigo: 'pendente', plano_ciclo_id: 1 } }, 2), false);
});
test('sem flag de PIX, assinatura só oferece cartão, inclusive em ofertas antigas', () => {
  assert.deepEqual(metodosAssinaturaDisponiveis(), ['cartao']);
  assert.deepEqual(metodosAssinaturaDisponiveis([]), ['cartao']);
  assert.deepEqual(metodosAssinaturaDisponiveis(['cartao', 'boleto']), ['cartao']);
});
test('PIX mensal só aparece quando a oferta o habilita; boleto não entra na assinatura', () => {
  assert.deepEqual(metodosAssinaturaDisponiveis(['cartao', 'pix', 'boleto']), ['cartao', 'pix']);
});
