import React, { useEffect, useState } from 'react';
import { useRouter, useLocalSearchParams } from 'expo-router';
import modalidadeService from '../../services/modalidadeService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout } from '../../components/ck';
import { ModalidadeForm } from '../../components/ck/modalidades';
import { modalidadeFromApi } from './ModalidadesScreen';
import { showSuccess, showError } from '../../utils/toast';

export default function FormModalidadeScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const isEdit = !!id && id !== 'novo';
  const [modality, setModality] = useState(isEdit ? null : {});
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  const load = async () => {
    setError('');
    try {
      const response = await modalidadeService.buscar(parseInt(id, 10));
      setModality(modalidadeFromApi(response.modalidade));
    } catch (err) {
      setError(err?.message || err?.error || 'Não foi possível carregar a modalidade.');
    }
  };

  useEffect(() => { if (isEdit) load(); }, [id]);

  const back = () => router.push('/modalidades');

  // Planos não são enviados: o backend só mexe neles quando "planos" vem preenchido
  const save = async (m) => {
    setSaving(true);
    const dados = { nome: m.name, descricao: m.description, cor: m.color, icone: m.icon, ativo: m.active ? 1 : 0 };
    try {
      if (isEdit) {
        await modalidadeService.atualizar(m.id, dados);
        showSuccess(`${m.name} atualizada`);
      } else {
        await modalidadeService.criar(dados);
        showSuccess(`${m.name} cadastrada`);
      }
      back();
    } catch (err) {
      showError(err?.message || err?.error || `Não foi possível ${isEdit ? 'salvar' : 'cadastrar'} a modalidade`);
    } finally {
      setSaving(false);
    }
  };

  return (
    <LayoutBase title={isEdit ? 'Editar modalidade' : 'Nova modalidade'} pageHeader={false}>
      {error ? (
        <Callout tone="danger" title="Não foi possível carregar a modalidade" action={<Button size="sm" onClick={load}>Tentar de novo</Button>}>{error}</Callout>
      ) : !modality ? (
        <p className="ck-al-hint" role="status">Carregando modalidade…</p>
      ) : (
        <ModalidadeForm
          key={modality.id || 'nova'}
          modality={modality}
          saving={saving}
          onSave={save}
          onBack={back}
          onCancel={back}
          onOpenPlans={() => router.push('/planos')}
        />
      )}
    </LayoutBase>
  );
}
