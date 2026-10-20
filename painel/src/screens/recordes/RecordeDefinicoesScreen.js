import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useRouter } from 'expo-router';
import recordeService from '../../services/recordeService';
import modalidadeService from '../../services/modalidadeService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, Modal } from '../../components/ck';
import { RecordeDefinicoesLista, recordError } from '../../components/ck/recordes';
import { definitionFromApi, recordActive } from '../../utils/recordeDefinicoes';
import { officialDefinition, officialRecord } from '../../utils/recordesOficiais';
import { showSuccess } from '../../utils/toast';

export default function RecordeDefinicoesScreen() {
  const router = useRouter();
  const [definitions, setDefinitions] = useState([]);
  const [modalities, setModalities] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [records, setRecords] = useState(null);
  const [confirm, setConfirm] = useState(null);
  const [toggling, setToggling] = useState(false);
  const [toggleError, setToggleError] = useState('');
  const requestId = useRef(0);

  const load = useCallback(async () => {
    const request = ++requestId.current;
    setLoading(true); setError('');
    try {
      const results = await Promise.allSettled([recordeService.listarDefinicoes({ todas: 'true' }), modalidadeService.listar(), recordeService.listarRecordes()]);
      if (request !== requestId.current) return;
      const [defsResult, modsResult, recordsResult] = results;
      if (defsResult.status === 'rejected') throw defsResult.reason;
      const rows = defsResult.value;
      const mods = modsResult.status === 'fulfilled' ? modsResult.value : [];
      if (modsResult.status === 'rejected') setError(recordError(modsResult.reason,'Não foi possível carregar as modalidades.'));
      if (recordsResult.status === 'rejected') { setRecords(null); setError(recordError(recordsResult.reason,'Contagem de registros indisponível.')); }
      else { const data = recordsResult.value; const mapped = rows.map(officialDefinition); setRecords((Array.isArray(data) ? data : data.recordes || []).map(record=>officialRecord(record,mapped))); }
      if (request !== requestId.current) return;
      setDefinitions(rows.map(definitionFromApi));
      setModalities(mods);
    } catch (err) {
      if (request === requestId.current) setError(recordError(err, 'Não foi possível carregar as definições.'));
    } finally { if (request === requestId.current) setLoading(false); }
  }, []);
  useEffect(() => { load(); return () => { requestId.current += 1; }; }, [load]);

  const toggle = async () => {
    if (!confirm || toggling) return;
    setToggling(true); setToggleError('');
    try {
      if (recordActive(confirm.ativo)) await recordeService.desativarDefinicao(confirm.id);
      else {
        // DELETE só desativa. Reativação usa PUT sem reenviar as métricas,
        // preservando os IDs e valores associados aos recordes existentes.
        await recordeService.atualizarDefinicao(confirm.id, {
          nome: confirm.nome, categoria: confirm.categoria,
          descricao: confirm.descricao || null,
          modalidade_id: confirm.modalidade_id ? Number(confirm.modalidade_id) : null,
          ordem: Number(confirm.ordem), ativo: 1,
        });
      }
      showSuccess(`Definição ${recordActive(confirm.ativo) ? 'desativada' : 'ativada'} com sucesso`);
      setConfirm(null);
      load();
    } catch (err) { setToggleError(recordError(err, 'Não foi possível alterar a situação.')); }
    finally { setToggling(false); }
  };

  return (
    <LayoutBase title="Definições de Recordes" pageHeader={false}>
      <RecordeDefinicoesLista rows={definitions} records={records} modalities={modalities} loading={loading} error={error} busy={toggling}
        onRefresh={load} onNew={() => router.push('/recordes/definicoes/novo')} onEdit={(row) => router.push(`/recordes/definicoes/${row.id}`)}
        onToggle={(row) => { setToggleError(''); setConfirm(row); }} onRecords={() => router.push('/recordes')} />
      <Modal open={!!confirm} size="sm" title={`${recordActive(confirm?.ativo) ? 'Desativar' : 'Ativar'} definição?`}
        description={confirm ? `Deseja ${recordActive(confirm.ativo) ? 'desativar' : 'ativar'} “${confirm.nome}”?` : ''}
        dismissable={!toggling} onClose={() => setConfirm(null)} footer={<>
          <Button disabled={toggling} onClick={() => setConfirm(null)}>Cancelar</Button>
          <Button variant={recordActive(confirm?.ativo) ? 'danger' : 'primary'} loading={toggling} onClick={toggle}>{recordActive(confirm?.ativo) ? 'Desativar' : 'Ativar'}</Button>
        </>}>
        <p className="ck-rd__confirm">Os recordes já registrados permanecem no histórico.</p>
        {toggleError ? <Callout tone="danger" title="Não foi possível alterar a definição">{toggleError}</Callout> : null}
      </Modal>
    </LayoutBase>
  );
}
