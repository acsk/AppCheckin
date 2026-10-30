import api from './api';

/** Períodos aceitos pela API (App\Support\Bi\Periodo) */
export type BiPeriodo = 'mes' | 'mes_anterior' | '3m' | '12m';

/**
 * BI da academia — Visão geral: indicadores do período com o anterior equivalente e séries dos gráficos.
 * GET /admin/bi/visao-geral?periodo=… (permissão bi.ver)
 */
export const buscarBiVisaoGeral = async (periodo: BiPeriodo): Promise<any> => {
  const response = await api.get('/admin/bi/visao-geral', { params: { periodo } });
  return response.data?.data;
};

/** Indicadores que abrem lista (BiVisaoGeralService::DETALHAVEIS) */
export type BiIndicadorDetalhe = 'alunos_ativos' | 'novos_alunos' | 'saidas' | 'receita_recebida' | 'valor_vencido';

/** Registros que compõem um indicador no período. GET /admin/bi/visao-geral/detalhe */
export const buscarBiDetalhe = async (indicador: BiIndicadorDetalhe, periodo: BiPeriodo): Promise<any> => {
  const response = await api.get('/admin/bi/visao-geral/detalhe', { params: { indicador, periodo } });
  return response.data?.data;
};

export default { buscarBiVisaoGeral, buscarBiDetalhe };
