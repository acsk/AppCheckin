import React, { useEffect, useRef, useState } from 'react';
import { Button, Callout, Icon, Modal, SelectField, TextField } from './index';
import { AulaModal } from './aulasOficial';
import { turmaService } from '../../services/turmaService';
import modalidadeService from '../../services/modalidadeService';
import { professorService } from '../../services/professorService';
import { aulaError, aulaPayload, dateLabel, isEnabled, officialAula, replicationPayload, weekStart } from '../../utils/aulas';
import { showSuccess } from '../../utils/toast';

export function AulaEditor({ id, date, diaId, draft, onCancel, onSaved }) {
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [retry, setRetry] = useState(0);
  const [future, setFuture] = useState(null);
  const busy = useRef(false);
  useEffect(() => {
    let active = true;
    setData(null); setError('');
    Promise.all([modalidadeService.listar(), professorService.listar(false), id ? turmaService.buscarPorId(id) : null])
      .then(([modalities, teachers, row]) => {
        if (id && !row?.id) throw new Error('A API não retornou os dados da aula.');
        const on = (v) => v == null || isEnabled(v);
        const available = (items, selected) => items.filter(item => on(item.ativo) || String(item.id) === String(selected));
        // Professor = papel Professor ativo nesta academia (Equipe): vinculo_ativo, além do cadastro ativo
        const teachersOn = teachers.filter(t => (on(t.ativo) && on(t.vinculo_ativo)) || String(t.id) === String(row?.professor_id));
        if (active) setData({ modalities: available(modalities, row?.modalidade_id), teachers: teachersOn, aula: row ? officialAula(row, date) : null });
      })
      .catch(err => { console.error('Erro ao carregar aula:', err); if (active) setError(aulaError(err, 'Não foi possível carregar o formulário.')); });
    return () => { active = false; };
  }, [id, date, retry]);
  const save = async (values, applyFuture) => {
    if (busy.current) return;
    busy.current = true; setSaving(true); setError('');
    try {
      const payload = aulaPayload(values, data.modalities, data.teachers, diaId);
      if (id && applyFuture === undefined) {
        const response = await turmaService.equivalentesFuturas(id);
        if (response.turmas?.length) { setFuture({ values, count: response.turmas.length }); return; }
      }
      const response = id
        ? await turmaService.atualizar(id, { ...payload, aplicar_em_futuras: applyFuture === true })
        : await turmaService.criar(payload);
      if (response?.type === 'error') throw new Error(response.message);
      if (!id && !response?.id) throw new Error('A API não confirmou a criação da aula.');
      showSuccess(id ? response.message || 'Aula atualizada com sucesso!' : 'Aula criada com sucesso!');
      setFuture(null);
      onSaved(values);
    } catch (err) {
      console.error('Erro ao salvar aula:', err);
      setError(aulaError(err, 'Não foi possível salvar a aula.'));
    } finally { busy.current = false; setSaving(false); }
  };
  if (!data) return <Modal open title={id ? 'Editar aula' : 'Nova aula'} onClose={onCancel}>
    {error ? <Callout tone="danger" title="Não foi possível carregar" action={<Button onClick={() => setRetry(n => n + 1)}>Tentar novamente</Button>}>{error}</Callout> : <p role="status">Carregando formulário…</p>}
  </Modal>;
  return <>
    <AulaModal key={id || 'new'} open={!future} aula={data.aula || (draft ? {
      modality: { id: draft.modalityId }, teacherId: draft.teacherId, date,
      start: draft.start, end: draft.end, capacity: draft.capacity, active: draft.active,
      openBefore: draft.openBefore, lateAfter: draft.lateAfter, closeBefore: draft.closeBefore, cancelBefore: draft.cancelBefore,
    } : null)} date={date} saving={saving} error={error}
      modalities={data.modalities.map(item => ({ id: String(item.id), name: item.nome, icon: item.icone, color: item.cor }))}
      teachers={data.teachers.map(item => ({ id: String(item.id), name: item.nome }))}
      onSave={save} onCancel={onCancel} />
    <Modal open={!!future} title="Aplicar nas aulas seguintes?" description={`${future?.count || 0} aulas equivalentes foram encontradas.`} dismissable={!saving} onClose={() => setFuture(null)}
      footer={<><Button disabled={saving} onClick={() => setFuture(null)}>Voltar</Button><Button disabled={saving} onClick={() => save(future.values, false)}>Só esta aula</Button><Button variant="primary" loading={saving} onClick={() => save(future.values, true)}>Esta e as seguintes</Button></>}>
      <p>As aulas equivalentes têm o mesmo professor, modalidade, dia da semana e horário.</p>
      {error ? <p role="alert" className="ck-field__error">{error}</p> : null}
    </Modal>
  </>;
}

const DAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
function Choice({ selected, title, description, onClick, disabled }) {
  return <button type="button" role="radio" aria-checked={selected} disabled={disabled} className="ck-ct-opt" onClick={onClick}><span className="ck-ct-opt__text"><strong>{title}</strong><span>{description}</span></span><span className="ck-ct-opt__radio" aria-hidden="true" /></button>;
}
function BusyBody({ busy, children }) {
  return <fieldset className="ck-au__fieldset ck-au__modalbody" disabled={busy}>{children}</fieldset>;
}

export function DesativarAulaModal({ aula, onCancel, onSaved }) {
  const [scope, setScope] = useState('apenas_esta');
  const [month, setMonth] = useState(aula.date.slice(0, 7));
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const busy = useRef(false);
  const confirm = async () => {
    if (busy.current) return;
    busy.current = true; setSaving(true); setError('');
    try {
      if (scope === 'mes_todo' && !month) throw new Error('Selecione o mês.');
      const response = await turmaService.desativar(aula.id, scope, month);
      if (response.type !== 'success') throw new Error(response.message || 'A API não confirmou a desativação.');
      showSuccess(response.message || 'Aula(s) desativada(s) com sucesso.');
      onSaved();
    } catch (err) { console.error('Erro ao desativar aula:', err); setError(aulaError(err, 'Não foi possível desativar a aula.')); }
    finally { busy.current = false; setSaving(false); }
  };
  return <Modal open sheet title="Desativar aula" description={`${aula.modality.name}, ${aula.start} às ${aula.end}, ${aula.teacher}`} dismissable={!saving} onClose={onCancel}
    leading={<span className="ck-au__modalicon"><Icon name="ban" size={20} /></span>}
    footer={<div className="ck-adm__foot"><Button variant="ghost" disabled={saving} onClick={onCancel}>Cancelar</Button><Button variant="danger" icon="ban" loading={saving} onClick={confirm}>Desativar {scope === 'apenas_esta' ? 'esta aula' : 'aulas'}</Button></div>}>
    <BusyBody busy={saving}><div className="ck-ct-opts" role="radiogroup" aria-label="Quais aulas desativar">
      <Choice selected={scope === 'apenas_esta'} title="Só nesta data" description={dateLabel(aula.date)} onClick={() => setScope('apenas_esta')} />
      <Choice selected={scope === 'proxima_semana'} title="Mesmo dia da semana no mês da próxima ocorrência" description="Desativa esta aula e as equivalentes no mês que contém a data daqui a sete dias, inclusive datas anteriores." onClick={() => setScope('proxima_semana')} />
      <Choice selected={scope === 'mes_todo'} title="Todas as equivalentes no mês" description="Desativa esta aula e as equivalentes em todos os dias do mês escolhido, inclusive datas anteriores." onClick={() => setScope('mes_todo')} />
    </div>
    {scope === 'mes_todo' ? <TextField type="month" label="Mês" value={month} onChange={e => setMonth(e.target.value)} /> : null}
    <p className="ck-al-hint">A aula deixa de aparecer como ativa no app. Os registros existentes são preservados. Nenhum motivo ou aviso automático é enviado por esta operação.</p>
    {aula.booked > 0 ? <Callout tone="warning" title={`${aula.booked} check-ins nesta aula`}>Confira os alunos afetados antes de desativar. Esta ação não confirma estorno de saldo nem notificação.</Callout> : null}
    {error ? <p role="alert" className="ck-field__error">{error}</p> : null}</BusyBody>
  </Modal>;
}

export function ReplicarAulasModal({ date, diaId, aulas, onCancel, onSaved }) {
  const sourceModalities = [...new Map(aulas.map(a => [a.modality.id, a.modality])).values()];
  const [modalities, setModalities] = useState(sourceModalities);
  const [loading, setLoading] = useState(true);
  const [optionsError, setOptionsError] = useState('');
  const [retry, setRetry] = useState(0);
  const [step, setStep] = useState(1);
  const [modalityId, setModalityId] = useState(sourceModalities.length === 1 ? sourceModalities[0].id : '');
  const [mode, setMode] = useState(aulas.length ? 'dia' : 'semana');
  const [month, setMonth] = useState(date.slice(0, 7));
  const [weekdays, setWeekdays] = useState([new Date(`${date}T12:00:00`).getDay()]);
  const [sourceWeek, setSourceWeek] = useState(weekStart(date));
  const [months, setMonths] = useState([date.slice(0, 7)]);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const busy = useRef(false);
  useEffect(() => {
    let active = true;
    setLoading(true); setOptionsError('');
    modalidadeService.listar(true).then(rows => {
      if (!active) return;
      const list = [...new Map([...sourceModalities, ...rows.map(item => ({ id: String(item.id), name: item.nome, icon: item.icone, color: item.cor }))].map(item => [item.id, item])).values()];
      setModalities(list);
      if (list.length === 1) setModalityId(current => current || list[0].id);
    }).catch(err => {
      console.error('Erro ao carregar modalidades para replicação:', err);
      if (active) setOptionsError(aulaError(err, 'Não foi possível carregar as modalidades.'));
    }).finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [retry]);
  const chosen = aulas.filter(a => a.modality.id === modalityId);
  const options = { mode, modalityId, month, weekdays, sourceWeek, months };
  const next = () => {
    setError('');
    if (!modalityId) { setError('Selecione a modalidade.'); return; }
    if (step === 2) {
      if (mode === 'dia' && !chosen.length) { setError('Não há aulas desta modalidade neste dia. Escolha uma semana modelo.'); return; }
      try { replicationPayload(options, diaId); } catch (err) { setError(err.message); return; }
    }
    setStep(n => n + 1);
  };
  const confirm = async () => {
    if (busy.current) return;
    busy.current = true; setSaving(true); setError('');
    try {
      const payload = replicationPayload(options, diaId);
      const response = mode === 'semana'
        ? await turmaService.replicarSemana(payload.semana_data, payload.meses_destino, payload.modalidade_id)
        : await turmaService.replicar(payload.dia_id, payload.periodo, payload.dias_semana, payload.mes, payload.modalidade_id);
      if (response.type !== 'success') throw new Error(response.message || 'A API não confirmou a replicação.');
      const count = response.summary?.total_criadas ?? response.total_criadas ?? response.turmas_criadas?.length;
      const skipped = response.summary?.total_puladas || 0;
      showSuccess(`${response.message || 'Replicação concluída.'}${count != null ? ` ${count} aulas criadas.` : ''}${skipped ? ` ${skipped} já existentes foram puladas.` : ''}`);
      onSaved();
    } catch (err) { console.error('Erro ao replicar aulas:', err); setError(aulaError(err, 'Não foi possível replicar as aulas.')); }
    finally { busy.current = false; setSaving(false); }
  };
  return <Modal open sheet size="lg" title="Replicar aulas" description={`Copie a grade de ${dateLabel(date)} para outros dias.`} dismissable={!saving} onClose={onCancel}
    leading={<span className="ck-au__modalicon"><Icon name="copy" size={20} /></span>}
    footer={<div className="ck-adm__foot ck-au__replfoot"><span className="ck-au__replinfo">{mode === 'dia' ? `${chosen.length} ${chosen.length === 1 ? 'aula' : 'aulas'} de origem` : 'Semana modelo'}</span>
      <Button variant="ghost" disabled={saving} onClick={step > 1 ? () => { setStep(n => n - 1); setError(''); } : onCancel}>{step > 1 ? 'Voltar' : 'Cancelar'}</Button>
      {step < 3 ? <Button variant="primary" disabled={loading || !!optionsError} onClick={next}>Continuar</Button> : <Button variant="primary" icon="copy" loading={saving} onClick={confirm}>Confirmar replicação</Button>}</div>}>
    <BusyBody busy={saving}>
      {loading ? <p role="status">Carregando modalidades…</p> : null}
      {optionsError ? <Callout tone="danger" title="Não foi possível carregar" action={<Button size="sm" onClick={() => setRetry(n => n + 1)}>Tentar novamente</Button>}>{optionsError}</Callout> : null}
      <ol className="ck-au__steps">{['O que copiar', 'Para quando', 'Conferir'].map((label, i) => <li key={label} className={step === i + 1 ? 'is-on' : step > i + 1 ? 'is-done' : ''} aria-current={step === i + 1 ? 'step' : undefined}><span>{i + 1}</span>{label}</li>)}</ol>
      {step === 1 ? <div className="ck-adm">
        <SelectField label="Modalidade" placeholder="Escolha" value={modalityId} options={modalities.map(m => ({ value: m.id, label: m.name }))} onChange={e => setModalityId(e.target.value)} />
        <p className="ck-al-hint">Ao copiar um dia, todas as aulas da modalidade são incluídas, mesmo as desativadas. No modelo semanal, somente aulas ativas são usadas. As novas cópias são ativas.</p>
        <ul className="ck-au__srclist">{chosen.map(a => <li key={a.id} className="ck-au__srcitem"><b>{a.start}–{a.end}</b><span>{a.modality.name}</span><em>{a.teacher}, {a.capacity} vagas{a.active ? '' : ', desativada'}</em></li>)}</ul>
        {modalityId && !chosen.length ? <p className="ck-al-hint">Não há aulas desta modalidade neste dia. Use uma semana modelo na próxima etapa.</p> : null}
      </div> : step === 2 ? <div className="ck-adm">
        <div className="ck-ct-opts" role="radiogroup" aria-label="Modelo de replicação">
          <Choice disabled={!aulas.length} selected={mode === 'dia'} title="Copiar este dia" description="Para os dias da semana escolhidos em um mês." onClick={() => setMode('dia')} />
          <Choice selected={mode === 'semana'} title="Copiar uma semana modelo" description="Repete a grade da modalidade nos meses escolhidos." onClick={() => setMode('semana')} />
        </div>
        {mode === 'dia' ? <>
          <TextField type="month" label="Mês de destino" value={month} onChange={e => setMonth(e.target.value)} />
          <div className="ck-field"><span className="ck-field__label">Dias da semana</span><div className="ck-au__wd" role="group" aria-label="Dias da semana">{DAYS.map((label, i) => <button type="button" key={label} className="ck-au__wdb" aria-pressed={weekdays.includes(i)} onClick={() => setWeekdays(list => list.includes(i) ? list.filter(n => n !== i) : [...list, i])}>{label}</button>)}</div></div>
          <p className="ck-al-hint">Inclui todos os dias escolhidos do mês, mesmo anteriores à origem. O dia de origem não é copiado sobre ele mesmo.</p>
        </> : <>
          <TextField type="date" label="Semana modelo (domingo a sábado)" value={sourceWeek} onChange={e => { if (e.target.value) setSourceWeek(weekStart(e.target.value)); }} />
          <TextField type="month" label="Adicionar mês de destino" value={month} onChange={e => setMonth(e.target.value)} />
          <Button size="sm" disabled={!month || months.includes(month)} onClick={() => setMonths(list => [...list, month].sort())}>Adicionar mês</Button>
          <div className="ck-ml__filters">{months.map(m => <Button key={m} size="sm" icon="x" onClick={() => setMonths(list => list.filter(item => item !== m))}>Remover {m}</Button>)}</div>
        </>}
      </div> : <div className="ck-adm"><div className="ck-au__sum">
        <div><span>Origem</span><strong>{mode === 'dia' ? chosen.length : 'Semana'}</strong><em>{mode === 'dia' ? dateLabel(date) : sourceWeek}</em></div>
        <div><span>Modalidade</span><strong>{modalities.find(m => m.id === modalityId)?.name}</strong></div>
        <div className="is-total"><span>Destino</span><strong>{mode === 'dia' ? month : `${months.length} meses`}</strong><em>{mode === 'dia' ? weekdays.map(i => DAYS[i]).join(', ') : months.join(', ')}</em></div>
      </div><Callout tone="info" title="Conferência">A API informa as quantidades criadas e puladas após a operação. Aulas já existentes são mantidas; não há substituição de conflitos. As cópias começam sem check-ins.</Callout></div>}
      {error ? <p role="alert" className="ck-field__error">{error}</p> : null}
    </BusyBody>
  </Modal>;
}
