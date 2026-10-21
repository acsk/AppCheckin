import React, { useEffect, useRef, useState } from 'react';
import { useLocalSearchParams } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import WodPreviewModal from '../../components/WodPreviewModal';
import { AulasAgenda } from '../../components/ck/aulasOficial';
import { AulaEditor, DesativarAulaModal, ReplicarAulasModal } from '../../components/ck/aulas';
import { Button, Callout, Modal, SelectField } from '../../components/ck';
import { turmaService } from '../../services/turmaService';
import { buscarWodPorDataModalidade } from '../../services/wodService';
import { aulaError, dateLabel, localISO, officialAula } from '../../utils/aulas';
import { showSuccess } from '../../utils/toast';

export default function TurmasScreen() {
  const params = useLocalSearchParams();
  const [date, setDate] = useState(() => /^\d{4}-\d{2}-\d{2}$/.test(params.data || '') ? params.data : localISO());
  const [result, setResult] = useState(null);
  const [counts, setCounts] = useState({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [reload, setReload] = useState(0);
  const [modal, setModal] = useState(null);
  const [busy, setBusy] = useState(false);
  const [modalError, setModalError] = useState('');
  const [wod, setWod] = useState(null);
  const [wodModality, setWodModality] = useState('');
  const lock = useRef(false);
  const mounted = useRef(true);
  const currentDate = useRef(date);
  currentDate.current = date;
  useEffect(() => () => { mounted.current = false; }, []);
  useEffect(() => {
    let active = true;
    setLoading(true); setError(''); setResult(null);
    turmaService.listar(date).then(data => {
      if (!active) return;
      setResult(data);
      setCounts(list => ({ ...list, [date]: data.turmas.length }));
    }).catch(err => {
      console.error('Erro ao carregar aulas:', err);
      if (active) setError(aulaError(err, 'Não foi possível carregar as aulas.'));
    }).finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [date, reload]);

  const aulas = (result?.turmas || []).map(row => officialAula(row, date));
  const modalities = [...new Map(aulas.map(a => [a.modality.id, a.modality])).values()];
  const refresh = () => { setCounts({}); setReload(n => n + 1); };
  const close = () => { if (!lock.current) { setModal(null); setModalError(''); } };
  const open = value => { if (!busy && !loading && !error) { setModalError(''); setModal(value); } };
  const saved = () => { setModal(null); setModalError(''); refresh(); };
  const mutate = async (action, message, dismiss = true) => {
    if (lock.current) return;
    const actionDate = date;
    lock.current = true; setBusy(true); setModalError(''); setError('');
    try {
      const response = await action();
      if (response?.type === 'error') throw new Error(response.message);
      showSuccess(response?.message || message);
      if (mounted.current && currentDate.current === actionDate) {
        if (dismiss) setModal(null);
        refresh();
      }
    } catch (err) {
      console.error('Erro na operação de aula:', err);
      if (mounted.current) {
        const message = aulaError(err, 'Não foi possível concluir a operação.');
        if (dismiss) setModalError(message); else setError(message);
      }
    } finally { lock.current = false; if (mounted.current) setBusy(false); }
  };
  const toggleLock = aula => mutate(
    () => aula.checkinLocked ? turmaService.desbloquearCheckin(aula.id) : turmaService.bloquearCheckin(aula.id),
    aula.checkinLocked ? 'Check-in reaberto.' : 'Check-in fechado.', false,
  );
  const openWod = async modalityId => {
    if (lock.current) return;
    lock.current = true; setBusy(true); setModalError('');
    try {
      const response = await buscarWodPorDataModalidade(date, modalityId);
      if (response?.type === 'error') throw new Error(response.message);
      const data = response?.data || response;
      if (!data?.id) throw new Error('Nenhum WOD encontrado para esta modalidade neste dia.');
      setModal(null); setWod(data);
    } catch (err) { console.error('Erro ao buscar WOD:', err); setModalError(aulaError(err, 'Não foi possível carregar o WOD.')); }
    finally { lock.current = false; setBusy(false); }
  };
  const confirmation = modal && ['delete', 'deleteDay', 'reactivate'].includes(modal.type);
  return <LayoutBase title="Aulas" pageHeader={false}>
    <div className="ck-au__statusbar">
      <Button size="sm" icon="refresh-cw" disabled={loading || busy} onClick={refresh}>Atualizar</Button>
      {loading ? <span role="status">Carregando aulas…</span> : null}
    </div>
    {error ? <Callout tone="danger" title="Não foi possível concluir" action={<Button size="sm" onClick={refresh}>Tentar novamente</Button>}>{error}</Callout> : null}
    <fieldset className="ck-au__fieldset ck-au__view" disabled={busy} aria-busy={loading || busy}>
      <AulasAgenda date={date} loading={loading} error={error} today={localISO()} now={new Date().toTimeString().slice(0, 5)} counts={counts} aulas={aulas}
        onDateChange={value => { if (!lock.current && !modal) setDate(value); }}
        onNew={() => open({ type: result?.dia?.id ? 'editor' : 'missingDay' })}
        onOpen={aula => open({ type: 'editor', id: aula.id })}
        onEdit={aula => open({ type: 'editor', id: aula.id })}
        onToggleLock={toggleLock}
        onDeactivate={aula => open({ type: 'deactivate', aula })}
        onReactivate={aula => open({ type: 'reactivate', aula })}
        onDelete={aula => open({ type: 'delete', aula })}
        onDeleteDay={() => open({ type: 'deleteDay' })}
        onReplicate={() => open({ type: 'replicate' })}
        onOpenWod={() => { setWodModality(modalities.length === 1 ? modalities[0].id : ''); open({ type: 'wod' }); }} />
    </fieldset>
    {modal?.type === 'editor' ? <AulaEditor id={modal.id} draft={modal.draft} date={date} diaId={result?.dia?.id} onCancel={close} onSaved={values => {
      refresh();
      setModal(modal.id ? null : { type: 'continue', values });
    }} /> : null}
    {modal?.type === 'continue' ? <Modal open title="Criar outra aula?" description="A aula foi salva. Continue no mesmo dia ou volte à agenda." onClose={close}
      footer={<><Button onClick={close}>Voltar à agenda</Button><Button variant="primary" onClick={() => setModal({ type: 'editor', draft: modal.values })}>Criar outra aula</Button></>}><p>{dateLabel(date)}</p></Modal> : null}
    {modal?.type === 'missingDay' ? <Modal open title="Dia não cadastrado" onClose={close} footer={<Button onClick={close}>Voltar</Button>}><p>Não há um dia cadastrado para {dateLabel(date)}. Cadastre-o no módulo de dias antes de criar aulas.</p></Modal> : null}
    {modal?.type === 'deactivate' ? <DesativarAulaModal aula={modal.aula} onCancel={close} onSaved={saved} /> : null}
    {modal?.type === 'replicate' ? <ReplicarAulasModal date={date} diaId={result?.dia?.id} aulas={aulas} onCancel={close} onSaved={saved} /> : null}
    {confirmation ? <Modal open title={modal.type === 'reactivate' ? 'Reativar aula' : modal.type === 'deleteDay' ? 'Excluir todas as aulas do dia' : 'Excluir aula permanentemente'} dismissable={!busy} onClose={close}
      footer={<><Button disabled={busy} onClick={close}>Cancelar</Button><Button variant={modal.type === 'reactivate' ? 'primary' : 'danger'} loading={busy} onClick={() => {
        if (modal.type === 'deleteDay') {
          if (!result?.dia?.id) { setModalError('Não foi possível identificar o dia. Atualize a agenda.'); return; }
          mutate(() => turmaService.deletarHorariosDia(result.dia.id), 'Aulas do dia excluídas.');
        } else if (modal.type === 'reactivate') {
          mutate(() => turmaService.atualizar(modal.aula.id, { ativo: 1 }), 'Aula reativada.');
        } else mutate(() => turmaService.deletarPermanente(modal.aula.id), 'Aula excluída permanentemente.');
      }}>Confirmar {modal.type === 'reactivate' ? 'reativação' : 'exclusão'}</Button></>}>
      <p>{modal.type === 'deleteDay' ? `Excluir as ${aulas.length} aulas de ${dateLabel(date)}?` : `${modal.aula.modality.name}, ${modal.aula.start} às ${modal.aula.end}, ${modal.aula.teacher}.`}</p>
      {modal.type !== 'reactivate' ? <Callout tone="warning" title="Atenção">A exclusão é definitiva e pode remover os registros vinculados. Para apenas ocultar a aula, use Desativar.</Callout> : null}
      {modalError ? <p className="ck-field__error" role="alert">{modalError}</p> : null}
    </Modal> : null}
    {modal?.type === 'wod' ? <Modal open title="WOD do dia" description={dateLabel(date)} dismissable={!busy} onClose={close}
      footer={<><Button disabled={busy} onClick={close}>Cancelar</Button><Button variant="primary" loading={busy} disabled={!wodModality} onClick={() => openWod(wodModality)}>Abrir WOD</Button></>}>
      <SelectField label="Modalidade" value={wodModality} placeholder="Escolha" options={modalities.map(m => ({ value: m.id, label: m.name }))} onChange={e => setWodModality(e.target.value)} disabled={busy} />
      {!modalities.length ? <p>Nenhuma modalidade encontrada no dia selecionado.</p> : null}
      {modalError ? <p role="alert" className="ck-field__error">{modalError}</p> : null}
    </Modal> : null}
    <WodPreviewModal visible={!!wod} onClose={() => setWod(null)} wodData={wod || {}} />
  </LayoutBase>;
}
