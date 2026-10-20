/* Modal oficial, adaptada aos IDs e múltiplos valores da API. */
import React, { useRef, useState } from 'react';
import { Avatar, Button, Callout, Icon, Modal, SegmentedControl, SelectField, TextAreaField, TextField } from './index';
import { fmtMetric, isBetter, localToday, metricLabel, parseMark, rankRecords, timeInput } from '../../utils/recordesOficiais';

export default function RecordeModal({ definitions, defId, record, students = [], records = [], onSave, onCancel }) {
  const [selected, setSelected] = useState(record?.defId || defId || definitions[0]?.id);
  const [who, setWho] = useState(record && !record.student ? 'academia' : 'aluno');
  const [student, setStudent] = useState(record?.student || null);
  const [query, setQuery] = useState('');
  const [date, setDate] = useState(record?.date || localToday());
  const [origin, setOrigin] = useState(record?.origin || 'academia');
  const [notes, setNotes] = useState(record?.notes || '');
  const [more, setMore] = useState(!!record?.notes);
  const [values, setValues] = useState(() => Object.fromEntries((record?.values || []).map(value => [value.metrica_id,
    value.valor_tempo_ms != null ? timeInput(value.valor_tempo_ms) : String(value.valor_decimal ?? value.valor_int ?? '')])));
  const [tried, setTried] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const lock = useRef(false);
  const definition = definitions.find(d => d.id === Number(selected));
  if (!definition) return null;
  const metrics = definition.metrics;
  const parsed = metrics.map(metric => ({ metric, value: parseMark(values[metric.id], metric) }));
  const errors = {};
  if (who === 'aluno' && !student) errors.student = 'Escolha o aluno.';
  const validDate = /^\d{4}-\d{2}-\d{2}$/.test(date) && !Number.isNaN(Date.parse(`${date}T12:00:00Z`)) && new Date(`${date}T12:00:00Z`).toISOString().slice(0,10) === date;
  if (!validDate || date > localToday()) errors.date = 'Informe uma data válida até hoje.';
  parsed.forEach(({metric,value}) => {
    if (value == null && [true,1,'1'].includes(metric.obrigatoria)) errors[metric.id] = 'Informe a marca.';
    else if (value != null && (!Number.isFinite(value) || value <= 0)) errors[metric.id] = metric.tipo_valor === 'tempo_ms' ? 'Use 1:05,320 ou 35,4. O tempo deve ser maior que zero.' : 'Informe um número maior que zero no formato válido.';
  });
  if (!parsed.some(item => item.value != null)) errors.values = 'Preencha pelo menos uma métrica.';
  const primary = parsed[0]?.value;
  const rank = rankRecords(records.filter(r => r.id !== record?.id), definition);
  const mine = rank.find(r => who === 'aluno' ? student && r.student?.id === student.id : !r.student);
  let feedback;
  if (Number.isFinite(primary) && primary > 0 && (who === 'academia' || student)) {
    if (mine && !isBetter(primary,mine.value,definition.metric)) feedback = ['neutral','info','Não supera a melhor marca',`A melhor continua ${fmtMetric(mine.value,definition.metric)}. O registro fica no histórico.`];
    else if (rank.length && isBetter(primary,rank[0].value,definition.metric)) feedback = ['success','trophy','Novo recorde da academia',`Supera a marca de ${fmtMetric(rank[0].value,definition.metric)}.`];
    else if (mine) feedback = ['success','trending-up','Novo recorde pessoal',`Melhora sobre ${fmtMetric(mine.value,definition.metric)}.`];
    else feedback = ['info','trophy','Primeira marca nesta prova',`Entra no ranking com ${fmtMetric(primary,definition.metric)}.`];
  }
  const normalized = query.trim().toLowerCase();
  const options = students.filter(s => `${s.name} ${s.id} ${s.cpf || ''}`.toLowerCase().includes(normalized)).slice(0,6);
  const save = async () => {
    setTried(true);
    if (lock.current || Object.keys(errors).length) return;
    lock.current = true; setSaving(true); setError('');
    try {
      await onSave({ definicao_id:definition.id, aluno_id:who === 'aluno' ? student.id : null, data_recorde:date, origem:origin,
        observacoes:notes.trim() || null, valores:parsed.filter(item => item.value != null).map(({metric,value}) => ({metrica_id:metric.id,
          [metric.tipo_valor === 'tempo_ms' ? 'valor_tempo_ms' : metric.tipo_valor === 'inteiro' ? 'valor_int' : 'valor_decimal']:value})) });
    } catch (err) { setError(err?.response?.data?.message || err?.message || err?.error || 'Não foi possível salvar a marca.'); }
    finally { lock.current = false; setSaving(false); }
  };
  return <div className="ck-recorde-modal"><Modal open size="md" title={record ? 'Editar marca' : 'Registrar marca'} description={metricLabel(definition.metric)}
    leading={<span className="ck-adm__icon"><Icon name="trophy" size={20} /></span>} dismissable={!saving} onClose={onCancel}
    initialFocus="input[type=search]" footer={<div className="ck-adm__foot"><Button variant="ghost" disabled={saving} onClick={onCancel}>Cancelar</Button><Button variant="primary" icon="check" loading={saving} onClick={save}>{record ? 'Salvar alterações' : 'Registrar'}{Number.isFinite(primary) && primary > 0 ? ` ${fmtMetric(primary,definition.metric)}` : ''}</Button></div>}>
    <fieldset className="ck-adm ck-rc-modal__fields" disabled={saving}>
      <legend className="ck-sr">Dados da marca</legend>
      {error ? <Callout tone="danger" title="Não foi possível salvar">{error}</Callout> : null}
      <SelectField label="Prova ou movimento" value={String(selected)} disabled={!!record} options={definitions.map(d => ({value:String(d.id),label:`${d.name}${d.modality ? `, ${d.modality}` : ''}`}))} onChange={event => {setSelected(Number(event.target.value));setValues({});setTried(false);}} />
      <div className="ck-field"><div className="ck-field__top"><span className="ck-field__label">De quem é a marca</span><SegmentedControl label="De quem é a marca" value={who} onChange={setWho} options={[{value:'aluno',label:'Aluno'},{value:'academia',label:'Sem aluno'}]} /></div>
        {who === 'aluno' ? student ? <div className="ck-rc__picked"><Avatar name={student.name} size="sm" decorative /><div><strong>{student.name}</strong><span>Aluno #{student.id}</span></div><Button size="sm" variant="ghost" onClick={()=>{setStudent(null);setQuery('');}}>Trocar</Button></div>
          : <div className="ck-rc__picker"><div className="ck-control"><Icon name="search" size={16} className="ck-control__icon" /><input type="search" className="ck-control__input" aria-label="Buscar aluno" placeholder="Nome, número ou CPF do aluno" value={query} onChange={event=>setQuery(event.target.value)} /></div>
            <ul className="ck-rc__opts" aria-label="Alunos">{options.length ? options.map(s=><li key={s.id}><button type="button" onClick={()=>setStudent(s)}><Avatar name={s.name} size="sm" decorative /><span>{s.name}</span><em>#{s.id}</em></button></li>) : <li className="ck-rc__noopt">Nenhum aluno encontrado.</li>}</ul>{tried && errors.student ? <span className="ck-field__error">{errors.student}</span> : null}</div>
          : <p className="ck-al-hint"><Icon name="info" size={15} />Marca da academia, sem aluno. Útil para referência ou recordes antigos.</p>}
      </div>
      <div className="ck-al-grid">{metrics.map(metric=><TextField key={metric.id} className="ck-rc__valfield" label={`${metric.nome}${[true,1,'1'].includes(metric.obrigatoria) ? ' *' : ''}`} value={values[metric.id] || ''} inputMode={metric.tipo_valor === 'tempo_ms' ? 'text' : 'decimal'} autoComplete="off" placeholder={metric.tipo_valor === 'tempo_ms' ? '0:35,400' : '0'} error={tried ? errors[metric.id] : undefined}
        suffix={metric.tipo_valor !== 'tempo_ms' && metric.unidade ? <span className="ck-sp-unit">{metric.unidade}</span> : null}
        hint={metric.tipo_valor === 'tempo_ms' ? 'Minutos:segundos,milissegundos. Ex.: 1:05,320 ou 35,4.' : metric.unidade ? `Valor em ${metric.unidade}.` : undefined}
        onChange={event=>setValues(previous=>({...previous,[metric.id]:event.target.value}))} />)}<TextField type="date" label="Data" value={date} max={localToday()} error={tried ? errors.date : undefined} onChange={event=>setDate(event.target.value)} /></div>
      {tried && errors.values ? <p className="ck-field__error">{errors.values}</p> : null}
      {feedback ? <div className={`ck-rc__fb is-${feedback[0]}`} role="status"><Icon name={feedback[1]} size={18} /><div><strong>{feedback[2]}</strong><span>{feedback[3]}</span></div></div> : null}
      <div className="ck-field"><span className="ck-field__label">Origem</span><SegmentedControl label="Origem" block value={origin} onChange={setOrigin} options={[{value:'academia',label:'Medido pela equipe'},{value:'aluno',label:'Informado pelo aluno'}]} /></div>
      {more ? <TextAreaField label="Observações" value={notes} placeholder="Ex.: piscina de 25m, com saída do bloco" onChange={event=>setNotes(event.target.value)} /> : <button type="button" className="ck-linkbtn" onClick={()=>setMore(true)}>+ Adicionar observação</button>}
    </fieldset>
  </Modal></div>;
}
