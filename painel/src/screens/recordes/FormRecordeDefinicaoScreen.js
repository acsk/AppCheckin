import React, { useCallback, useEffect, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout } from '../../components/ck';
import { RecordeDefinicaoEditor, recordError } from '../../components/ck/recordes';
import modalidadeService from '../../services/modalidadeService';

export default function FormRecordeDefinicaoScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const editing = !!id && id !== 'novo';
  const definitionId = editing ? Number(id) : null;
  const [modalities, setModalities] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const back = () => router.replace('/recordes/definicoes');
  const load = useCallback(async () => {
    setLoading(true); setError('');
    try { setModalities(await modalidadeService.listar()); }
    catch (err) { setError(recordError(err, 'Não foi possível carregar as modalidades.')); }
    finally { setLoading(false); }
  }, []);
  useEffect(() => { load(); }, [load]);

  return (
    <LayoutBase title={editing ? 'Editar definição' : 'Nova definição'} pageHeader={false}>
      {editing && (!Number.isInteger(definitionId) || definitionId <= 0)
        ? <Callout tone="danger" title="Definição inválida" action={<Button onClick={back}>Voltar para definições</Button>}>Confira o endereço e tente novamente.</Callout>
        : loading ? <p role="status">Carregando modalidades…</p>
          : error ? <Callout tone="danger" title="Não foi possível abrir o formulário" action={<Button onClick={load}>Tentar novamente</Button>}>{error}</Callout>
            : <RecordeDefinicaoEditor key={definitionId || 'new'} id={definitionId} modalities={modalities} modal={false} onClose={back} onSaved={back} />}
    </LayoutBase>
  );
}
