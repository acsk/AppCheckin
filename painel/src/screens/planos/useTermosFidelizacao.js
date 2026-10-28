import { useCallback, useEffect, useState } from 'react';
import planoService from '../../services/planoService';
import { showError, showSuccess } from '../../utils/toast';

/** Termos da fidelidade da academia (herdados pelos ciclos): dados, modal e salvar. */
export default function useTermosFidelizacao(ativo = true) {
  const [dados, setDados] = useState(null);
  const [aberto, setAberto] = useState(false);
  const [salvando, setSalvando] = useState(false);

  const carregar = useCallback(() => {
    if (!ativo) return Promise.resolve();
    return planoService.termosFidelizacao().then(setDados).catch(() => setDados({ texto: '', ciclos: [] }));
  }, [ativo]);
  useEffect(() => { carregar(); }, [carregar]);

  const salvar = async (payload, depois) => {
    setSalvando(true);
    try {
      const r = await planoService.salvarTermosFidelizacao(payload);
      setDados(r);
      setAberto(false);
      showSuccess(r?.message || 'Termos salvos');
      depois?.();
    } catch (e) {
      showError(e?.error || 'Não foi possível salvar os termos');
    } finally {
      setSalvando(false);
    }
  };

  return { dados, texto: dados?.texto || '', aberto, abrir: () => { carregar(); setAberto(true); }, fechar: () => !salvando && setAberto(false), salvar, salvando };
}
