import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useRouter } from 'expo-router';
import recordeService from '../../services/recordeService';
import modalidadeService from '../../services/modalidadeService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, Modal } from '../../components/ck';
import { RecordeDefinicaoEditor, RecordeDefinicoesLista, recordError } from '../../components/ck/recordes';
import { definitionFromApi, recordActive } from '../../utils/recordeDefinicoes';
import { showSuccess } from '../../utils/toast';

export default function RecordeDefinicoesScreen() {
  const router = useRouter();
  const [definitions, setDefinitions] = useState([]);
  const [modalities, setModalities] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editor, setEditor] = useState(null);
  const [confirm, setConfirm] = useState(null);
  const [toggling, setToggling] = useState(false);
  const [toggleError, setToggleError] = useState('');
  const requestId = useRef(0);

  const load = useCallback(async () => {
    const request = ++requestId.current;
    setLoading(true); setError('');
    try {
      const [rows, mods] = await Promise.all([
        recordeService.listarDefinicoes({ todas: 'true' }), modalidadeService.listar(),
      ]);
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
      <RecordeDefinicoesLista rows={definitions} modalities={modalities} loading={loading} error={error}
        onRefresh={load} onNew={() => setEditor({ id: null })} onEdit={(row) => setEditor({ id: row.id })}
        onToggle={(row) => { setToggleError(''); setConfirm(row); }} onRecords={() => router.push('/recordes')} />
      {editor ? <RecordeDefinicaoEditor key={editor.id || 'new'} id={editor.id} modalities={modalities}
        onClose={() => setEditor(null)} onSaved={() => { setEditor(null); load(); }} /> : null}
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
