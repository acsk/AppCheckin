import React, { useEffect, useState } from 'react';
import { Badge, Button, Callout, Modal, SelectField, Tabs, TextAreaField, TextField } from '../../components/ck';
import { Meter } from '../../components/ck/graficos';
import recorrenciaService from '../../services/recorrenciaService';
import { showError, showSuccess } from '../../utils/toast';
import { COBRANCA, STATUS, RENOVACAO, brl, dataBr, msg } from './recorrenciaUtil';

/**
 * Contrato de recorrência: dados, cronograma da fidelização, pagamentos, eventos, situação no Asaas e o
 * simulador de cancelamento (com isenção e data efetiva). Props: open, contratoId, canAct, onClose, onChanged.
 */
export default function RecorrenciaDetalheModal({ open, contratoId, canAct, onClose, onChanged }) {
  const [d, setD] = useState(null);
  const [erro, setErro] = useState('');
  const [tab, setTab] = useState('resumo');
  const [busy, setBusy] = useState(false);
  const [sim, setSim] = useState(null);
  const [form, setForm] = useState({ tipo: '', data_efetiva: '', isencao: '', isencao_motivo: '', motivo: '' });

  const carregar = async (gateway = false) => {
    setErro('');
    try {
      const r = await recorrenciaService.obter(contratoId, gateway);
      setD(r);
      setSim(r.simulacao_cancelamento);
    } catch (e) {
      setErro(msg(e, 'Não foi possível carregar o contrato.'));
    }
  };
  useEffect(() => { if (open) { setD(null); setTab('resumo'); carregar(true); } }, [open, contratoId]);

  const simular = async (f = form) => {
    try {
      const r = await recorrenciaService.simular(contratoId, { tipo: f.tipo || undefined, data_efetiva: f.data_efetiva || undefined, isencao: f.isencao || undefined });
      setSim(r.simulacao);
    } catch (e) {
      showError(msg(e, 'Não foi possível simular.'));
    }
  };
  const setF = (patch) => { const f = { ...form, ...patch }; setForm(f); if (!('motivo' in patch) && !('isencao_motivo' in patch)) simular(f); };

  const executar = async (acao, sucesso) => {
    setBusy(true);
    try {
      const r = await acao();
      showSuccess(r?.message || r?.data?.message || sucesso);
      onChanged?.();
      await carregar();
      setTab('resumo');
    } catch (e) {
      showError(msg(e, 'Não foi possível concluir.'));
    } finally {
      setBusy(false);
    }
  };

  const c = d?.contrato;
  const pendente = (d?.cancelamentos || []).find((k) => k.status === 'solicitado');
  const vivo = c && ['pendente_ativacao', 'ativo', 'pagamento_pendente', 'inadimplente'].includes(c.status);
  const tabs = [{ value: 'resumo', label: 'Resumo' }, { value: 'cronograma', label: 'Cronograma' }, { value: 'pagamentos', label: 'Pagamentos' }, { value: 'eventos', label: 'Histórico' }];
  if (canAct && (vivo || pendente)) tabs.push({ value: 'cancelar', label: pendente ? 'Confirmar cancelamento' : 'Simular cancelamento' });

  return <Modal open={open} size="lg" dialogClassName="ck-rec__dlg" title={c ? `${c.aluno_nome} · ${c.plano_nome}` : 'Contrato'}
    description={c ? `Contrato #${c.id} · matrícula #${c.matricula_id} · ${c.ciclo_nome || ''}` : undefined} dismissable={!busy} onClose={onClose}
    footer={<Button disabled={busy} onClick={onClose}>Fechar</Button>}>
    {erro ? <Callout tone="danger">{erro}</Callout> : !c ? <p className="ck-rec__hint" role="status">Carregando…</p> : <div className="ck-rec__det">
      <div className="ck-rec__badges">
        <Badge tone={STATUS[c.status]?.[0] || 'neutral'} dot>{c.status_nome}</Badge>
        {c.em_fidelizacao ? <Badge tone="brand" icon="lock">Fidelização {c.meses_cumpridos}/{c.fidelizacao_meses}</Badge> : <Badge tone="outline">Sem prazo mínimo</Badge>}
        {pendente ? <Badge tone="warning" icon="alert-triangle">Cancelamento aguardando confirmação</Badge> : null}
      </div>
      <Tabs label="Seções" tabs={tabs} value={tab} onChange={setTab} />

      {tab === 'resumo' ? <>
        <dl className="ck-rec__facts">
          <div><dt>Mensalidade</dt><dd>{brl(c.valor_mensal_vigente)}</dd><span>contratado {brl(c.valor_mensal_contratado)} · referência {brl(c.valor_mensal_referencia)}</span></div>
          <div><dt>Desconto por mês</dt><dd>{brl(c.desconto_mensal)}</dd><span>{c.em_fidelizacao ? `fim da fidelização em ${dataBr(c.fim_fidelizacao_em)}` : 'fidelização concluída'}</span></div>
          <div><dt>Próxima cobrança</dt><dd>{dataBr(c.proxima_cobranca_em)}</dd><span>{c.metodo === 'cartao' ? 'cartão recorrente' : c.metodo}</span></div>
          <div><dt>Acesso até</dt><dd>{dataBr(c.acesso_ate)}</dd><span>início {dataBr(c.inicio_em)}</span></div>
        </dl>
        <Meter total={c.fidelizacao_meses} format={(v) => `${v} ${v === 1 ? 'mês' : 'meses'}`} segments={[
          { label: 'Mensalidades pagas', value: Math.min(c.meses_pagos, c.fidelizacao_meses), tone: 'success' },
          { label: 'Restantes da fidelização', value: Math.max(0, c.fidelizacao_meses - c.meses_pagos), tone: 'muted' },
        ]} />
        <p className="ck-rec__hint"><strong>Ao fim da fidelização:</strong> {RENOVACAO[c.politica_renovacao]}{c.renovacao_oferecida_em ? ` Nova fidelização oferecida em ${dataBr(c.renovacao_oferecida_em)}.` : ''}</p>
        {d.gateway ? <Callout tone={d.gateway.erro ? 'warning' : 'info'} title="Asaas">
          {d.gateway.erro ? d.gateway.erro : `Assinatura ${d.gateway.removida ? 'removida' : d.gateway.status || '—'} · próximo vencimento ${dataBr(d.gateway.proximo_vencimento)} · ${brl(d.gateway.valor)} · ${d.gateway.forma || ''}`}
        </Callout> : null}
        <details className="ck-rec__termos"><summary>Termos aceitos (versão {c.termos_versao}) em {dataBr(c.aceite_em)}{c.aceite_ip ? ` · IP ${c.aceite_ip}` : ''}</summary>
          <pre>{c.termos_aceitos}</pre><small>Hash {c.termos_hash}</small></details>
        {canAct ? <div className="ck-rec__acts">
          <Button icon="refresh-cw" loading={busy} onClick={() => executar(() => recorrenciaService.sincronizar(c.id), 'Conferido no Asaas.')}>Conferir no Asaas</Button>
        </div> : null}
      </> : null}

      {tab === 'cronograma' ? <table className="ck-rec__tbl">
        <thead><tr><th scope="col">Nº</th><th scope="col">Vencimento</th><th scope="col">Valor</th><th scope="col">Situação</th><th scope="col">Pago em</th></tr></thead>
        <tbody>{d.cronograma.map((x) => <tr key={x.numero} className={x.na_fidelizacao ? undefined : 'is-fora'}>
          <td>{x.numero}{x.na_fidelizacao ? '' : ' *'}</td><td>{dataBr(x.vencimento)}</td><td>{brl(x.valor)}</td>
          <td><Badge tone={COBRANCA[x.status]?.[0] || 'neutral'} dot>{COBRANCA[x.status]?.[1] || x.status}</Badge>{x.tentativas ? <small> {x.tentativas} recusa(s)</small> : null}</td>
          <td>{dataBr(x.pago_em)}</td>
        </tr>)}</tbody>
        <caption>Cada linha é uma cobrança separada no Asaas. {d.cronograma.some((x) => !x.na_fidelizacao) ? '* Depois da fidelização.' : ''}</caption>
      </table> : null}

      {tab === 'pagamentos' ? <table className="ck-rec__tbl">
        <thead><tr><th scope="col">Vencimento</th><th scope="col">Valor</th><th scope="col">Situação</th><th scope="col">Pago em</th><th scope="col">Observação</th></tr></thead>
        <tbody>{d.pagamentos.map((p) => <tr key={p.id}><td>{dataBr(p.data_vencimento)}</td><td>{brl(p.valor)}</td><td>{p.status_nome}</td><td>{dataBr(p.data_pagamento)}</td><td className="ck-rec__obs">{p.observacoes}</td></tr>)}</tbody>
      </table> : null}

      {tab === 'eventos' ? <ol className="ck-rec__eventos">{d.eventos.map((e) => <li key={e.id}>
        <span>{new Date(String(e.created_at).replace(' ', 'T')).toLocaleString('pt-BR')}</span>
        <strong>{e.tipo.replace(/_/g, ' ')}</strong>
        {e.de_status || e.para_status ? <em>{STATUS[e.de_status]?.[1] || e.de_status || '—'} → {STATUS[e.para_status]?.[1] || e.para_status}</em> : null}
        {e.usuario_nome ? <small>por {e.usuario_nome}</small> : null}
      </li>)}</ol> : null}

      {tab === 'cancelar' ? <div className="ck-rec__sim">
        {pendente ? <Callout tone="warning" title={`Pedido do aluno em ${dataBr(pendente.solicitado_em)}`}>
          As cobranças futuras já foram canceladas. Confirme o valor de regularização (com isenção, se for o caso). {pendente.motivo ? `Motivo: ${pendente.motivo}` : ''}
        </Callout> : <div className="ck-rec__simform">
          <SelectField label="Tipo" value={form.tipo} onChange={(e) => setF({ tipo: e.target.value })} options={[
            { value: '', label: 'Pelo cálculo (antecipado, fim do prazo ou arrependimento)' },
            { value: 'impossibilidade', label: 'Impossibilidade de prestar o serviço (sem regularização)' },
            { value: 'administrativo', label: 'Administrativo' },
          ]} />
          <TextField label="Último dia de acesso" type="date" value={form.data_efetiva} onChange={(e) => setF({ data_efetiva: e.target.value })} hint="Vazio: até o fim do período pago." />
        </div>}
        {sim ? <div className="ck-rec__simres">
          <dl className="ck-rec__facts">
            <div><dt>Meses usufruídos</dt><dd>{sim.meses_usufruidos} de {sim.fidelizacao_meses}</dd></div>
            <div><dt>Desconto concedido</dt><dd>{brl(sim.desconto_acumulado)}</dd></div>
            <div><dt>Regularização</dt><dd>{brl(pendente ? Number(pendente.valor_regularizacao) - Number(form.isencao || 0) : sim.valor_regularizacao)}</dd><span>{sim.politica_cobra ? 'política do contrato cobra' : 'política não cobra'}</span></div>
            <div><dt>Em aberto (à parte)</dt><dd>{brl(sim.valor_em_aberto)}</dd><span>créditos do aluno {brl(sim.creditos_disponiveis)}</span></div>
          </dl>
          <ul className="ck-rec__memoria">{sim.memoria.map((l, i) => <li key={i}>{l}</li>)}</ul>
          <p className="ck-rec__hint">Data efetiva: <strong>{dataBr(sim.data_efetiva)}</strong>{sim.dentro_arrependimento ? ` · dentro do prazo de arrependimento (até ${dataBr(sim.arrependimento_ate)})` : ''}</p>
        </div> : null}
        <div className="ck-rec__simform">
          <TextField label="Isenção (R$)" inputMode="decimal" placeholder="0,00" value={form.isencao} onChange={(e) => setF({ isencao: e.target.value.replace(',', '.').replace(/[^0-9.]/g, '') })} />
          <TextField label="Motivo da isenção" value={form.isencao_motivo} onChange={(e) => setForm({ ...form, isencao_motivo: e.target.value })} hint="Obrigatório quando há isenção." />
        </div>
        {!pendente ? <TextAreaField label="Motivo do cancelamento" rows={2} value={form.motivo} onChange={(e) => setForm({ ...form, motivo: e.target.value })} /> : null}
        <div className="ck-rec__acts">
          <Button variant="ghost" disabled={busy} onClick={() => setTab('resumo')}>Voltar</Button>
          {pendente
            ? <Button variant="primary" icon="check" loading={busy} onClick={() => executar(() => recorrenciaService.confirmarCancelamento(pendente.id, { isencao: form.isencao || undefined, isencao_motivo: form.isencao_motivo || undefined }), 'Cancelamento confirmado.')}>Confirmar cancelamento</Button>
            : <Button variant="danger" icon="close-circle" loading={busy} onClick={() => executar(() => recorrenciaService.cancelar(c.id, { ...form, tipo: form.tipo || undefined, data_efetiva: form.data_efetiva || undefined, isencao: form.isencao || undefined }), 'Assinatura cancelada.')}>Confirmar cancelamento</Button>}
        </div>
      </div> : null}
    </div>}
  </Modal>;
}
