import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, Modal, PageHeader } from '../../components/ck';
import { RecordesPainel } from '../../components/ck/recordesOficial';
import RecordeModal from '../../components/ck/RecordeModal';
import { recordError } from '../../components/ck/recordes';
import { officialDefinition, officialRecord } from '../../utils/recordesOficiais';
import recordeService from '../../services/recordeService';
import modalidadeService from '../../services/modalidadeService';
import alunoService from '../../services/alunoService';
import { showSuccess, showError } from '../../utils/toast';

export default function RecordesScreen() {
  const router = useRouter();
  const [rawDefinitions,setRawDefinitions] = useState([]);
  const [rawRecords,setRawRecords] = useState([]);
  const [students,setStudents] = useState([]);
  const [loading,setLoading] = useState(true);
  const [available,setAvailable] = useState({definitions:false,records:false});
  const [error,setError] = useState('');
  const [modal,setModal] = useState(null);
  const [selected,setSelected] = useState(null);
  const [deleting,setDeleting] = useState(false);
  const [confirm,setConfirm] = useState(null);
  const [deleteError,setDeleteError] = useState('');
  const [opening,setOpening] = useState(false);
  const requestId = useRef(0);
  const definitions = useMemo(()=>rawDefinitions.map(officialDefinition),[rawDefinitions]);
  const records = useMemo(()=>rawRecords.map(record=>officialRecord(record,definitions)),[rawRecords,definitions]);
  const load = useCallback(async()=>{
    const request = ++requestId.current;
    setLoading(true);setError('');
    // Mantém o carregamento parcial que já existia: uma falha em alunos ou
    // modalidades não impede a leitura das marcas e definições disponíveis.
    const results = await Promise.allSettled([recordeService.listarRecordes(),recordeService.listarDefinicoes({todas:'true'}),modalidadeService.listar(),alunoService.listarBasico()]);
    if(request!==requestId.current)return;
    const labels=['recordes','definições','modalidades','alunos'];
    const failures=[];
    results.forEach((result,index)=>{
      if(result.status==='rejected'){failures.push(recordError(result.reason,`Não foi possível carregar ${labels[index]}.`));return;}
      const data=result.value;
      if(index===0){setRawRecords(Array.isArray(data)?data:data.recordes || []);setAvailable(previous=>({...previous,records:true}));}
      if(index===1){setRawDefinitions(data);setAvailable(previous=>({...previous,definitions:true}));}
      if(index===2){ /* As modalidades já acompanham cada definição; a consulta mantém a disponibilidade separada. */ }
      if(index===3)setStudents((Array.isArray(data)?data:data.alunos || []).map(student=>({id:Number(student.id),name:student.nome,cpf:student.cpf || ''})));
    });
    setError(failures.join(' '));setLoading(false);
  },[]);
  useEffect(()=>{load();return()=>{requestId.current+=1;};},[load]);
  const save = async payload=>{
    if(modal?.record){await recordeService.atualizarRecorde(modal.record.id,payload);showSuccess('Recorde atualizado com sucesso!');}
    else{await recordeService.criarRecorde(payload);showSuccess('Recorde registrado com sucesso!');}
    setSelected(Number(payload.definicao_id));setModal(null);load();
  };
  const edit = async record=>{
    if(opening)return;
    setOpening(true);
    try{
      const response=await recordeService.buscarRecorde(record.id);
      const data=response.recorde || response;
      let defs=definitions;
      if(!defs.some(def=>def.id===Number(data.definicao_id))){
        const result=await recordeService.buscarDefinicao(data.definicao_id);
        const raw=result.definicao || result;
        setRawDefinitions(previous=>[...previous,raw]);defs=[...defs,officialDefinition(raw)];
      }
      setModal({record:officialRecord(data,defs),definitionId:Number(data.definicao_id)});
    }catch(err){showError(recordError(err,'Não foi possível abrir o recorde.'));}
    finally{setOpening(false);}
  };
  const remove = async()=>{
    if(!confirm||deleting)return;
    setDeleting(true);setDeleteError('');
    try{await recordeService.excluirRecorde(confirm.id);showSuccess('Recorde excluído com sucesso');setConfirm(null);load();}
    catch(err){setDeleteError(recordError(err,'Não foi possível excluir o registro.'));}
    finally{setDeleting(false);}
  };
  const ready=available.records&&available.definitions;
  return <LayoutBase title="Recordes" pageHeader={false}>
    {error?<Callout tone="danger" title="Alguns dados não foram carregados" action={<Button size="sm" disabled={loading} onClick={load}>Tentar novamente</Button>}>{error}</Callout>:null}
    {loading?<p role="status" className="ck-enrollment-state">Atualizando recordes…</p>:null}
    {opening?<p role="status" className="ck-enrollment-state">Abrindo registro…</p>:null}
    {ready?<RecordesPainel definitions={definitions} records={records} selected={selected || undefined} onSelect={setSelected}
      onManage={()=>router.push('/recordes/definicoes')} onNewRecord={def=>setModal({definitionId:def?.id})}
      onEditRecord={edit} onDeleteRecord={record=>{setDeleteError('');setConfirm(record);}} />:!loading?<PageHeader title="Recordes" description="Recarregue os dados para consultar as marcas."/>:null}
    {modal?<RecordeModal key={modal.record?.id || 'new'} definitions={modal.record?definitions:definitions.filter(def=>def.active)} defId={modal.definitionId} record={modal.record} records={records} students={students} onSave={save} onCancel={()=>setModal(null)} />:null}
    <Modal open={!!confirm} size="sm" title="Excluir registro?" description="O registro será excluído e deixará de participar do ranking." dismissable={!deleting} onClose={()=>setConfirm(null)} footer={<><Button disabled={deleting} onClick={()=>setConfirm(null)}>Cancelar</Button><Button variant="danger" loading={deleting} onClick={remove}>Excluir registro</Button></>}>
      {deleteError?<Callout tone="danger" title="Não foi possível excluir">{deleteError}</Callout>:<p className="ck-al-hint">Confirme a exclusão desta marca.</p>}
    </Modal>
  </LayoutBase>;
}
