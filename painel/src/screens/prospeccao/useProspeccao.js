import { useCallback, useEffect, useState } from 'react';
import prospeccaoService from '../../services/prospeccaoService';
import { erroApi } from '../../utils/prospeccao';

// Rótulos, papel (admin/comercial), responsáveis e filtros do módulo. Carregado uma vez por sessão de página.
let cache = null;

export function useProspeccaoContexto() {
  const [ctx, setCtx] = useState(cache);
  const [erro, setErro] = useState('');
  useEffect(() => {
    if (cache) return;
    prospeccaoService.contexto()
      .then((data) => { cache = data; setCtx(data); })
      .catch((e) => setErro(erroApi(e, 'Não foi possível carregar o módulo Prospecção.')));
  }, []);
  return { ctx, erro };
}

export function limparContextoProspeccao() {
  cache = null;
}

/** Modelos ativos (para abordagem), carregados sob demanda. */
export function useModelosAtivos() {
  const [modelos, setModelos] = useState([]);
  const carregar = useCallback(async () => {
    if (modelos.length) return modelos;
    const data = await prospeccaoService.modelos({ ativos: true });
    setModelos(data.modelos || []);
    return data.modelos || [];
  }, [modelos]);
  return carregar;
}
