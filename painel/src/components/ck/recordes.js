/* Definições de recordes — referência: definicoes_recordes_tabela.html.
   Estilos: ck-recordes.css. Formulário compartilhado entre modal e rotas diretas. */
import React, { useEffect, useId, useMemo, useState } from 'react';
import { Badge, Button, Callout, Icon, IconButton, Modal, PageHeader, SelectField, TextAreaField, TextField } from './index';
import recordeService from '../../services/recordeService';
import { showSuccess } from '../../utils/toast';
import { definitionErrors, definitionFromApi, definitionPayload, emptyRecordDefinition, emptyRecordMetric,
  filterDefinitions, METRIC_DIRECTIONS, METRIC_TYPES, normalizeRecordText, RECORD_CATEGORIES, recordActive } from '../../utils/recordeDefinicoes';

const num = (value) => Number(value).toLocaleString('pt-BR');
export const recordError = (error, fallback) => error?.response?.data?.error || error?.response?.data?.message || error?.error || error?.message || fallback;

export function RecordeDefinicoesLista({ rows = [], modalities = [], loading, error, onRefresh, onNew, onEdit, onToggle, onRecords }) {
  const [query, setQuery] = useState('');
  const [category, setCategory] = useState('');
  const [modality, setModality] = useState('');
  const [status, setStatus] = useState('all');
  const visible = useMemo(() => filterDefinitions(rows, { query, category, modality, status }), [rows, query, category, modality, status]);
  const modalityOptions = new Map(modalities.map((item) => [String(item.id), item.nome]));
  rows.forEach((row) => { if (row.modalidade_id && !modalityOptions.has(String(row.modalidade_id))) modalityOptions.set(String(row.modalidade_id), row.modalidade_nome || `Modalidade ${row.modalidade_id}`); });
  const hasFilters = query || category || modality || status !== 'all';
  const reset = () => { setQuery(''); setCategory(''); setModality(''); setStatus('all'); };
  const stats = [
    ['Total de definições', rows.length, 'Disponíveis no catálogo'],
    ['Movimentos', rows.filter((row) => row.categoria === 'movimento').length, 'Exercícios de força e reps'],
    ['Provas', rows.filter((row) => row.categoria === 'prova').length, 'Desafios e tempos'],
    ['Modalidade natação', rows.filter((row) => normalizeRecordText(row.modalidade_nome) === 'natacao').length, 'Definições específicas'],
  ];
  return (
    <div className="ck-rd">
      <PageHeader title="Definições de Recordes" description="Gerencie movimentos, provas e métricas utilizadas nos recordes dos alunos." actions={<><Button icon="external-link" onClick={onRecords}>Ver recordes</Button><Button variant="primary" icon="plus" onClick={onNew} disabled={loading || !!error}>Nova definição</Button></>} />
      {error ? <Callout tone="danger" title="Não foi possível carregar as definições" action={<Button size="sm" onClick={onRefresh}>Tentar novamente</Button>}>{error}</Callout> : null}
      <section className="ck-rd__summary" aria-label="Resumo do catálogo">
        {stats.map(([label, value, hint]) => <div className="ck-card ck-rd__stat" key={label}><span>{label}</span><strong>{loading ? '—' : num(value)}</strong><span>{hint}</span></div>)}
      </section>
      <section className="ck-card ck-rd__toolbar" aria-label="Filtrar definições">
        <div className="ck-rd__tools">
          <div className="ck-control ck-rd__search"><Icon name="search" size={18} className="ck-control__icon" /><input type="search" className="ck-control__input" aria-label="Buscar definições" placeholder="Buscar definição por nome, descrição ou métrica…" value={query} onChange={(event) => setQuery(event.target.value)} />{query ? <IconButton icon="x" size="sm" label="Limpar busca" onClick={() => setQuery('')} /> : null}</div>
          <SelectField aria-label="Filtrar modalidade" value={modality} onChange={(event) => setModality(event.target.value)} options={[{ value: '', label: 'Todas as modalidades' }, { value: 'none', label: 'Sem modalidade' }, ...[...modalityOptions].map(([value, label]) => ({ value, label }))]} />
          <SelectField aria-label="Filtrar situação" value={status} onChange={(event) => setStatus(event.target.value)} options={[{ value: 'all', label: 'Todos os status' }, { value: 'active', label: 'Somente ativas' }, { value: 'inactive', label: 'Somente inativas' }]} />
        </div>
        <div className="ck-rd__filters" role="group" aria-label="Filtrar categoria">
          {[{ value: '', label: 'Todas' }, ...RECORD_CATEGORIES].map((item) => <button type="button" key={item.value} className="ck-ml__chip ck-rd__chip" aria-pressed={category === item.value} onClick={() => setCategory(item.value)}>{item.label}</button>)}
        </div>
      </section>
      <p className="ck-rd__count" role="status">{loading ? 'Carregando definições…' : `${num(visible.length)} ${visible.length === 1 ? 'definição encontrada' : 'definições encontradas'}`}</p>
      {loading ? <div className="ck-rd__empty"><Icon name="loader" className="ck-spin" size={24} /><strong>Carregando catálogo…</strong></div>
        : visible.length ? <div className="ck-card ck-rd__scroll" role="region" aria-label="Tabela de definições de recordes" tabIndex={0}>
          <table className="ck-mt-table ck-rd__table"><thead><tr>{['Definição', 'Categoria', 'Modalidade', 'Métrica', 'Direção', 'Situação', 'Ações'].map((label) => <th key={label} scope="col" className={label === 'Ações' ? 'ck-rd__actions' : undefined}>{label}</th>)}</tr></thead>
            <tbody>{visible.map((row) => <tr key={row.id} className="ck-ml__row" onClick={(event) => { if (!event.target.closest('button, a')) onEdit(row); }}>
              <td><button type="button" className="ck-ml__name ck-rd__name" onClick={() => onEdit(row)}>{row.nome}</button>{row.descricao ? <p className="ck-rd__description">{row.descricao}</p> : null}</td>
              <td><Badge tone="outline">{RECORD_CATEGORIES.find((item) => item.value === row.categoria)?.label || row.categoria || '—'}</Badge></td>
              <td>{row.modalidade_nome ? <Badge tone="info">{row.modalidade_nome}</Badge> : <span className="ck-rd__subtle">—</span>}</td>
              <td>{row.metricas?.length ? row.metricas.map((metric, index) => <div className="ck-rd__metric" key={metric.id || index}><strong>{metric.nome}</strong>{metric.unidade ? <span className="ck-rd__subtle"> ({metric.unidade})</span> : null}</div>) : <span className="ck-rd__subtle">Sem métricas</span>}</td>
              <td>{(row.metricas || []).map((metric, index) => <div className={`ck-rd__direction ${metric.direcao === 'menor_melhor' ? 'is-down' : ''}`} key={metric.id || index}>{METRIC_DIRECTIONS.find((item) => item.value === metric.direcao)?.label || '—'}</div>)}</td>
              <td><Badge tone={recordActive(row.ativo) ? 'success' : 'neutral'} dot>{recordActive(row.ativo) ? 'Ativa' : 'Inativa'}</Badge></td>
              <td className="ck-mt-actions"><IconButton icon="pencil" size="sm" label={`Editar ${row.nome}`} onClick={() => onEdit(row)} /><IconButton icon="power" size="sm" label={`${recordActive(row.ativo) ? 'Desativar' : 'Ativar'} ${row.nome}`} onClick={() => onToggle(row)} /></td>
            </tr>)}</tbody>
          </table>
        </div> : !error ? <div className="ck-rd__empty"><Icon name="search" size={24} /><strong>Nenhuma definição encontrada</strong><p>{hasFilters ? 'Ajuste os filtros para encontrar a definição desejada.' : 'Cadastre a primeira definição para começar.'}</p><Button size="sm" onClick={hasFilters ? reset : onNew}>{hasFilters ? 'Limpar filtros' : 'Nova definição'}</Button></div> : null}
    </div>
  );
}

export function RecordeDefinicaoEditor({ id, modalities = [], modal = true, onClose, onSaved }) {
  const editing = !!id;
  const formId = useId();
  const [form, setForm] = useState(emptyRecordDefinition);
  const [loading, setLoading] = useState(editing);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [errors, setErrors] = useState({});
  const [retry, setRetry] = useState(0);
  const set = (key, value) => setForm((previous) => ({ ...previous, [key]: value }));
  useEffect(() => {
    let active = true;
    if (!id) { setForm(emptyRecordDefinition()); setLoading(false); return () => { active = false; }; }
    setLoading(true); setError('');
    recordeService.buscarDefinicao(id).then((response) => {
      if (active) setForm(definitionFromApi(response.definicao || response));
    }).catch((err) => { if (active) setError(recordError(err, 'Não foi possível carregar a definição.')); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [id, retry]);
  const updateMetric = (index, key, value) => setForm((previous) => ({ ...previous, metricas: previous.metricas.map((metric, position) => position === index ? { ...metric, [key]: value } : metric) }));
  const submit = async (event) => {
    event.preventDefault();
    if (saving || loading) return;
    const nextErrors = definitionErrors(form);
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length) return;
    setSaving(true); setError('');
    try {
      const payload = definitionPayload(form, editing);
      const response = editing ? await recordeService.atualizarDefinicao(id, payload) : await recordeService.criarDefinicao(payload);
      showSuccess(editing ? 'Definição atualizada com sucesso' : 'Definição criada com sucesso');
      onSaved(response);
    } catch (err) { setError(recordError(err, 'Não foi possível salvar a definição.')); }
    finally { setSaving(false); }
  };
  const title = editing ? 'Editar definição' : 'Nova definição';
  const footer = <><Button disabled={saving} onClick={onClose}>Cancelar</Button><Button type="submit" form={formId} variant="primary" icon="save" loading={saving} disabled={loading || (editing && !form.id)}>{saving ? 'Salvando…' : 'Salvar definição'}</Button></>;
  const fields = <>
    {error ? <Callout tone="danger" title={form.id || !editing ? 'Não foi possível salvar' : 'Não foi possível carregar'} action={editing && !form.id ? <Button size="sm" onClick={() => setRetry((value) => value + 1)}>Tentar novamente</Button> : null}>{error}</Callout> : null}
    {loading ? <p role="status" className="ck-rd__wait"><Icon name="loader" className="ck-spin" size={20} />Carregando definição…</p> : <form id={formId} onSubmit={submit} noValidate className="ck-rd__form">
      <fieldset disabled={saving || (editing && !form.id)}>
        <legend className="ck-sr">Dados da definição</legend>
        <div className="ck-rd__formgrid">
          <TextField className="ck-rd__full" label="Nome *" value={form.nome} placeholder="Ex.: Deadlift, 100m Crawl" onChange={(event) => set('nome', event.target.value)} error={errors.nome} required />
          <SelectField label="Categoria *" value={form.categoria} options={RECORD_CATEGORIES} onChange={(event) => set('categoria', event.target.value)} error={errors.categoria} />
          <SelectField label="Modalidade" value={form.modalidade_id} options={[{ value: '', label: 'Nenhuma' }, ...modalities.map((item) => ({ value: String(item.id), label: item.nome })), ...(form.modalidade_id && !modalities.some((item) => String(item.id) === form.modalidade_id) ? [{ value: form.modalidade_id, label: form.modalidade_nome || `Modalidade ${form.modalidade_id}` }] : [])]} onChange={(event) => set('modalidade_id', event.target.value)} />
          <TextAreaField className="ck-rd__full" label="Descrição" value={form.descricao} placeholder="Descrição da definição" rows={2} onChange={(event) => set('descricao', event.target.value)} />
          <TextField label="Ordem" type="number" min="0" step="1" value={form.ordem} onChange={(event) => set('ordem', event.target.value)} error={errors.ordem} />
          <SelectField label="Status" value={form.ativo ? '1' : '0'} disabled={!editing} options={[{ value: '1', label: 'Ativa' }, { value: '0', label: 'Inativa' }]} onChange={(event) => set('ativo', event.target.value === '1')} hint={!editing ? 'Novas definições são criadas ativas.' : undefined} />
        </div>
        <div className="ck-rd__metrichead"><div><h3>Métricas</h3><p>A primeira métrica é a principal na comparação.</p></div><Button size="sm" icon="plus" onClick={() => set('metricas', [...form.metricas, emptyRecordMetric()])}>Adicionar métrica</Button></div>
        {errors.metricas ? <p className="ck-field__error" role="alert">{errors.metricas}</p> : null}
        {form.metricas.map((metric, index) => <section className="ck-rd__metriceditor" key={metric.id || `new-${index}`} aria-label={`Métrica ${index + 1}`}>
          <header><h3>{index === 0 ? 'Métrica principal' : `Métrica ${index + 1}`}</h3>{form.metricas.length > 1 ? <IconButton icon="trash" size="sm" label={`Remover métrica ${index + 1}`} onClick={() => set('metricas', form.metricas.filter((_, position) => position !== index))} /> : null}</header>
          <div className="ck-rd__formgrid">
            <TextField label="Nome da métrica *" value={metric.nome} placeholder="Carga, Tempo, Repetições" onChange={(event) => updateMetric(index, 'nome', event.target.value)} error={errors[`metric_${index}_nome`]} required />
            <TextField label="Código *" value={metric.codigo} placeholder="peso_kg, tempo_ms, repeticoes" onChange={(event) => updateMetric(index, 'codigo', event.target.value)} error={errors[`metric_${index}_codigo`]} required />
            <SelectField label="Tipo de valor" value={metric.tipo_valor} options={METRIC_TYPES} onChange={(event) => updateMetric(index, 'tipo_valor', event.target.value)} error={errors[`metric_${index}_tipo_valor`]} />
            <TextField label="Unidade" value={metric.unidade} placeholder="kg, ms, reps, m" onChange={(event) => updateMetric(index, 'unidade', event.target.value)} />
            <SelectField label="Direção" value={metric.direcao} options={METRIC_DIRECTIONS} onChange={(event) => updateMetric(index, 'direcao', event.target.value)} error={errors[`metric_${index}_direcao`]} />
            <SelectField label="Obrigatória" value={recordActive(metric.obrigatoria) ? '1' : '0'} options={[{ value: '1', label: 'Sim' }, { value: '0', label: 'Não' }]} onChange={(event) => updateMetric(index, 'obrigatoria', Number(event.target.value))} />
          </div>
        </section>)}
      </fieldset>
    </form>}
  </>;
  return modal ? <div className="ck-rd"><Modal open title={title} description="Configure o recorde e suas métricas de comparação." size="md" dismissable={!saving} onClose={onClose} footer={footer}>{fields}</Modal></div>
    : <div className="ck-rd"><PageHeader title={title} description="Configure o recorde e suas métricas de comparação." actions={<Button variant="ghost" icon="arrow-left" disabled={saving} onClick={onClose}>Voltar para definições</Button>} /><section className="ck-rd__editor">{fields}<footer>{footer}</footer></section></div>;
}
