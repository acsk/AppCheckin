/* Integração dos layouts oficiais de recordes com o contrato da API. */
import React, { useEffect, useState } from 'react';
import { Button, Callout, IconButton, SelectField, TextField } from './index';
import { DefinicaoRecordeForm, DefinicoesRecordes } from './recordesOficial';
import { definitionStats, officialDefinition } from '../../utils/recordesOficiais';
import { definitionErrors, definitionPayload, emptyRecordMetric, METRIC_TYPES, METRIC_DIRECTIONS } from '../../utils/recordeDefinicoes';
import recordeService from '../../services/recordeService';
import { showSuccess } from '../../utils/toast';

export const recordError = (error, fallback) => error?.response?.data?.error || error?.response?.data?.message || error?.error || error?.message || fallback;

export function RecordeDefinicoesLista({ rows = [], records, loading, error, busy, onRefresh, onNew, onEdit, onToggle, onRecords }) {
  const definitions = definitionStats(rows.map(officialDefinition), records || [], records != null);
  return <div>
    {error ? <Callout tone="danger" title="Não foi possível carregar todos os dados" action={<Button size="sm" disabled={loading} onClick={onRefresh}>Tentar novamente</Button>}>{error}</Callout> : null}
    {loading ? <p role="status" className="ck-enrollment-state">Carregando definições…</p> : <DefinicoesRecordes definitions={definitions} busy={busy} onBack={onRecords} onNew={onNew} onEdit={def=>onEdit(def.api)} onToggleActive={def=>onToggle(def.api)} />}
  </div>;
}

function AdditionalMetrics({ metrics, onChange }) {
  const update = (index,key,value) => onChange(metrics.map((metric,i)=>i===index?{...metric,[key]:value}:metric));
  return <section className="ck-al-sec"><header className="ck-al-sec__head"><div><h2 className="ck-al-sec__title">Métricas adicionais</h2><p className="ck-al-sec__desc">Mantidas junto à marca. O ranking usa a métrica principal.</p></div><Button size="sm" icon="plus" onClick={()=>onChange([...metrics,emptyRecordMetric()])}>Adicionar</Button></header>
    <div className="ck-al-sec__body">{metrics.map((metric,index)=><section className="ck-rc-extra" key={metric.id || `new-${index}`}><header><strong>Métrica {index+2}</strong><IconButton size="sm" icon="trash" label={`Remover métrica ${index+2}`} onClick={()=>onChange(metrics.filter((_,i)=>i!==index))} /></header><div className="ck-al-grid">
      <TextField label="Nome" value={metric.nome || ''} onChange={event=>update(index,'nome',event.target.value)} />
      <TextField label="Código" value={metric.codigo || ''} onChange={event=>update(index,'codigo',event.target.value)} />
      <SelectField label="Tipo" value={metric.tipo_valor || 'decimal'} options={METRIC_TYPES} onChange={event=>update(index,'tipo_valor',event.target.value)} />
      <TextField label="Unidade" value={metric.unidade || ''} onChange={event=>update(index,'unidade',event.target.value)} />
      <SelectField label="Direção" value={metric.direcao || 'maior_melhor'} options={METRIC_DIRECTIONS} onChange={event=>update(index,'direcao',event.target.value)} />
      <SelectField label="Obrigatória" value={String(Number(metric.obrigatoria))} options={[{value:'1',label:'Sim'},{value:'0',label:'Não'}]} onChange={event=>update(index,'obrigatoria',Number(event.target.value))} />
    </div></section>)}</div>
  </section>;
}

export function RecordeDefinicaoEditor({ id, modalities = [], onClose, onSaved }) {
  const [definition,setDefinition] = useState(null);
  const [loading,setLoading] = useState(!!id);
  const [saving,setSaving] = useState(false);
  const [error,setError] = useState('');
  const [retry,setRetry] = useState(0);
  useEffect(()=>{
    let active=true;
    if (!id) {setDefinition(null);setLoading(false);return ()=>{active=false;};}
    setLoading(true);setError('');
    Promise.allSettled([recordeService.buscarDefinicao(id),recordeService.listarRecordes({definicao_id:id})]).then(results=>{
      const [detail,marks]=results;
      if(detail.status==='rejected')throw detail.reason;
      const definition=detail.value.definicao || detail.value;
      const data=marks.status==='fulfilled'?marks.value:null;
      const count=data?(Array.isArray(data)?data:data.recordes || []).length:undefined;
      if(active)setDefinition({...definition,count});
    })
      .catch(err=>{if(active)setError(recordError(err,'Não foi possível carregar a definição.'));})
      .finally(()=>{if(active)setLoading(false);});
    return ()=>{active=false;};
  },[id,retry]);
  const save = async (data) => {
    if(saving)return;
    const original=definition?.metricas ? [...definition.metricas].sort((a,b)=>Number(a.ordem_comparacao)-Number(b.ordem_comparacao))[0] : null;
    const metric=data.metric;
    const form={nome:data.name,categoria:data.category==='teste'?'teste_fisico':data.category,modalidade_id:data.modality || '',descricao:data.description || '',ordem:String(definition?.ordem ?? 10),ativo:data.active,
      metricas:[{...(original?.id?{id:original.id}:{}),codigo:original?.codigo || metric.code,nome:metric.name,
        tipo_valor:metric.type==='tempo'?'tempo_ms':metric.type==='reps'?'inteiro':metric.type==='numero'?(original?.tipo_valor==='inteiro'?'inteiro':'decimal'):'decimal',
        unidade:metric.type==='tempo'?'ms':metric.unit || '',direcao:metric.better==='menor'?'menor_melhor':'maior_melhor',obrigatoria:original?.obrigatoria ?? 1},...(data.extraMetrics || []).map(m=>({...m,unidade:m.unidade || ''}))]};
    const errors=definitionErrors(form);
    if(Object.keys(errors).length){setError(Object.values(errors).join(' '));return;}
    setSaving(true);setError('');
    try{
      const payload=definitionPayload(form,!!id);
      const response=id?await recordeService.atualizarDefinicao(id,payload):await recordeService.criarDefinicao(payload);
      showSuccess(id?'Definição atualizada com sucesso':'Definição criada com sucesso');onSaved(response);
    }catch(err){setError(recordError(err,'Não foi possível salvar a definição.'));}
    finally{setSaving(false);}
  };
  if(loading)return <p role="status" className="ck-enrollment-state">Carregando definição…</p>;
  return <>
    {error?<Callout tone="danger" title="Não foi possível concluir" action={id&&!definition?<Button onClick={()=>setRetry(n=>n+1)}>Tentar novamente</Button>:null}>{error}</Callout>:null}
    {(!id||definition)?<DefinicaoRecordeForm key={id || 'new'} definition={definition?officialDefinition(definition):undefined} modalities={modalities} saving={saving} error={error} onBack={onClose} onCancel={onClose} onSave={save} renderExtraMetrics={(metrics,onChange)=><AdditionalMetrics metrics={metrics} onChange={onChange} />}/>:null}
  </>;
}
