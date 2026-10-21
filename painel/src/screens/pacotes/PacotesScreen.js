import React, { useCallback, useEffect, useRef, useState } from 'react';
import LayoutBase from '../../components/LayoutBase';
import { PacotesLista, PacoteModal, ContratarPacoteModal, PacoteBeneficiariosModal, ConfirmarPagamentoPacoteModal } from '../../components/ck/pacotes';
import { Button, Callout, Modal } from '../../components/ck';
import api from '../../services/api';
import pacoteService from '../../services/pacoteService';
import planoService from '../../services/planoService';
import alunoService from '../../services/alunoService';
import { showError, showSuccess } from '../../utils/toast';

const msg = (err, fallback) => err?.mensagemLimpa || err?.message || err?.error || fallback;

/* pacotes.qtd_beneficiarios conta só os dependentes: o pagante sempre ocupa mais uma vaga. */
function toPackage(p) {
  return {
    id: p.id,
    name: p.nome,
    description: p.descricao || '',
    total: +p.valor_total || 0,
    people: (+p.qtd_beneficiarios || 0) + 1,
    plan: p.plano_id ? { id: p.plano_id, name: p.plano_nome || '—', price: +p.plano_valor || 0, modality: p.modalidade_nome || '' } : null,
    cycle: p.plano_ciclo_id ? { id: p.plano_ciclo_id, months: +p.ciclo_meses || 1, value: +p.ciclo_valor || 0 } : null,
    contracts: +p.contratos_abertos || 0,
    active: !!+p.ativo,
  };
}

function toPlan(p) {
  return {
    id: p.id,
    name: p.nome,
    price: +p.valor || 0,
    modality: p.modalidade_nome || '',
    cycles: (p.ciclos || []).filter(c => c.ativo === undefined || !!+c.ativo)
      .map(c => ({ id: c.id, months: +c.meses || 1, value: +c.valor || 0 }))
      .sort((a, b) => a.months - b.months),
  };
}

const member = b => ({ id: b.aluno_id, name: b.nome, usuarioId: b.usuario_id });

function toPending(c, byId) {
  const pkg = byId[c.pacote_id];
  const members = (c.beneficiarios || []).map(member);
  const payer = members.find(m => String(m.usuarioId) === String(c.pagante_usuario_id));
  return {
    id: c.contrato_id,
    status: c.status || 'pendente',
    pkg: { id: c.pacote_id, name: c.pacote_nome, months: pkg?.cycle?.months },
    total: +c.valor_total || 0,
    people: (+c.qtd_beneficiarios || 0) + 1,
    createdAt: c.created_at,
    start: c.data_inicio,
    end: c.data_fim,
    payer: { id: payer?.id ?? null, usuarioId: c.pagante_usuario_id, name: c.pagante_nome || payer?.name || '—' },
    members,
  };
}

function toActive(item) {
  const c = item.contrato || {};
  const members = (item.beneficiarios || []).map(member);
  if (item.pagante && !members.some(m => String(m.id) === String(item.pagante.id))) {
    members.unshift({ id: item.pagante.id, name: item.pagante.nome, usuarioId: c.pagante_usuario_id });
  }
  return {
    id: c.contrato_id,
    status: c.status || 'ativo',
    pkg: { id: c.pacote_id, name: c.pacote_nome },
    total: +c.valor_total || 0,
    people: (+c.qtd_beneficiarios || 0) + 1,
    createdAt: c.created_at,
    start: c.data_inicio,
    end: c.data_fim,
    payer: { id: item.pagante?.id ?? null, usuarioId: c.pagante_usuario_id, name: c.pagante_nome || item.pagante?.nome || '—' },
    members,
    missingEnrollments: +item.qtd_matriculas_faltando || 0,
  };
}

function toPayload(d) {
  return {
    nome: d.name,
    descricao: d.description,
    valor_total: d.total,
    qtd_beneficiarios: Math.max(1, d.people - 1),
    plano_id: d.planId,
    plano_ciclo_id: d.cycleId,
    ativo: d.active ? 1 : 0,
  };
}

export default function PacotesScreen() {
  const [packages, setPackages] = useState([]);
  const [contracts, setContracts] = useState([]);
  const [plans, setPlans] = useState([]);
  const [students, setStudents] = useState([]);
  const [methods, setMethods] = useState([]);
  const [tab, setTab] = useState('pacotes');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [modal, setModal] = useState(null);
  const [busy, setBusy] = useState(false);
  const mounted = useRef(true);
  useEffect(() => () => { mounted.current = false; }, []);

  const load = useCallback(async () => {
    setLoading(true); setError('');
    try {
      const [pk, pend, act] = await Promise.all([
        pacoteService.listar(),
        pacoteService.listarContratos('pendente'),
        pacoteService.listarContratos('ativo'),
      ]);
      if (!mounted.current) return;
      const list = (pk?.pacotes || []).map(toPackage);
      const byId = Object.fromEntries(list.map(p => [p.id, p]));
      setPackages(list);
      setContracts([
        ...(pend?.contratos || []).map(c => toPending(c, byId)),
        ...(act?.contratos || []).map(toActive),
      ]);
    } catch (err) {
      console.error('Erro ao carregar pacotes:', err);
      if (mounted.current) setError(msg(err, 'Não foi possível carregar os pacotes.'));
    } finally {
      if (mounted.current) setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
    // Listas de apoio dos modais: falhas aqui não impedem ver os pacotes.
    planoService.listar(true).then(r => mounted.current && setPlans((r?.planos || []).map(toPlan))).catch(e => console.error('Erro ao carregar planos:', e));
    alunoService.listarBasico().then(r => mounted.current && setStudents((r?.alunos || []).map(a => ({ id: a.id, name: a.nome, email: a.email, usuarioId: a.usuario_id }))))
      .catch(e => console.error('Erro ao carregar alunos:', e));
    api.get('/formas-pagamento').then(({ data }) => mounted.current && setMethods(data?.formas || [])).catch(e => console.error('Erro ao carregar formas de pagamento:', e));
  }, [load]);

  const close = () => { if (!busy) setModal(null); };

  async function run(action, success, nextTab) {
    setBusy(true);
    try {
      await action();
      if (!mounted.current) return;
      setModal(null);
      showSuccess(success);
      if (nextTab) setTab(nextTab);
      load();
    } catch (err) {
      console.error(err);
      showError(msg(err, 'Não foi possível concluir.'));
      throw err;
    } finally {
      if (mounted.current) setBusy(false);
    }
  }
  const quiet = promise => promise.catch(() => {});

  const savePackage = d => quiet(run(
    () => d.id ? pacoteService.atualizar(d.id, toPayload(d)) : pacoteService.criar(toPayload(d)),
    d.id ? 'Pacote salvo.' : 'Pacote criado.',
  ));

  const toggleActive = k => quiet(run(
    () => pacoteService.atualizar(k.id, toPayload({ ...k, planId: k.plan?.id, cycleId: k.cycle?.id, active: !k.active })),
    k.active ? 'Pacote desativado.' : 'Pacote ativado.',
  ));

  // Plano desativado não vem em planos ativos: mantém o do pacote para a edição não perder o vínculo.
  const withPlanOf = k => (!k?.plan || plans.some(p => String(p.id) === String(k.plan.id)) ? plans
    : [...plans, { ...k.plan, cycles: k.cycle ? [k.cycle] : [] }]);

  const duplicate = k => setModal({ type: 'package', pkg: { ...k, id: undefined, name: k.name + ' (cópia)', contracts: 0 } });

  return <LayoutBase title="Pacotes" pageHeader={false}>
    {error ? <Callout tone="danger" title="Não foi possível carregar" action={<Button size="sm" onClick={load}>Tentar novamente</Button>}>{error}</Callout> : null}
    <PacotesLista packages={packages} contracts={contracts} loading={loading && !packages.length} tab={tab} onTabChange={setTab}
      onNew={() => setModal({ type: 'package', pkg: null })}
      onEdit={k => setModal({ type: 'package', pkg: k })}
      onDuplicate={duplicate}
      onToggleActive={toggleActive}
      onContract={k => setModal({ type: 'contract', pkg: k })}
      onConfirmPayment={c => setModal({ type: 'pay', contract: c })}
      onBeneficiaries={c => setModal({ type: 'members', contract: c })}
      onGenerateEnrollments={c => quiet(run(() => pacoteService.gerarMatriculas(c.id), 'Matrículas geradas.'))}
      onCancelContract={c => setModal({ type: 'cancel', contract: c })} />

    {modal?.type === 'package' ? <PacoteModal open package={modal.pkg} plans={withPlanOf(modal.pkg)} saving={busy} onCancel={close} onSave={savePackage} /> : null}

    {modal?.type === 'contract' ? <ContratarPacoteModal open package={modal.pkg} packages={packages} students={students} onCancel={close}
      onConfirm={d => run(
        () => pacoteService.contratar(d.packageId, { pagante_usuario_id: d.payerUsuarioId, beneficiarios: d.beneficiaryIds }),
        'Contrato criado. Falta confirmar o pagamento.', 'pendentes',
      )} /> : null}

    {modal?.type === 'members' ? <PacoteBeneficiariosModal open contract={modal.contract} students={students} onCancel={close}
      onSave={d => run(() => pacoteService.definirBeneficiarios(d.contractId, d.beneficiaryIds), 'Beneficiários salvos.')} /> : null}

    {modal?.type === 'pay' ? <ConfirmarPagamentoPacoteModal open contract={modal.contract} methods={methods} onCancel={close}
      onConfirm={d => run(
        () => pacoteService.darBaixa(d.contractId, { forma_pagamento_id: d.methodId, data_pagamento: d.paidAt, observacoes: d.reference }),
        'Pagamento confirmado. Matrículas ativadas.', 'ativos',
      )} /> : null}

    {modal?.type === 'cancel' ? <Modal open title="Cancelar contrato" description={`Contrato #${modal.contract.id}, ${modal.contract.pkg.name}`} dismissable={!busy} onClose={close}
      footer={<><Button disabled={busy} onClick={close}>Voltar</Button><Button variant="danger" loading={busy}
        onClick={() => quiet(run(() => pacoteService.excluirContrato(modal.contract.id), 'Contrato cancelado.'))}>Cancelar contrato</Button></>}>
      <p>O contrato ainda não foi pago. Cancelar apaga as matrículas e as cobranças pendentes de {modal.contract.members.length === 1 ? '1 pessoa' : `${modal.contract.members.length} pessoas`}.</p>
    </Modal> : null}
  </LayoutBase>;
}
