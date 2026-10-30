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
export type BiIndicadorDetalhe = 'alunos_ativos' | 'novos_alunos' | 'reativados' | 'saidas' | 'receita_recebida' | 'valor_vencido' | 'a_vencer' | 'estornos'
  | 'sem_checkin' | 'em_queda' | 'cancelaram' | 'nao_renovaram' | 'renovacao_atrasada' | 'recuperados';

/** Frequência e ocupação: check-ins, ocupação, mapa, turmas, professores e alunos em atenção. GET /admin/bi/frequencia */
export const buscarBiFrequencia = async (periodo: BiPeriodo): Promise<any> => {
  const response = await api.get('/admin/bi/frequencia', { params: { periodo } });
  return response.data?.data;
};

/** Retenção e evasão: o que houve com os ativos do início, motivos, Prospecção e risco. GET /admin/bi/retencao */
export const buscarBiRetencao = async (periodo: BiPeriodo): Promise<any> => {
  const response = await api.get('/admin/bi/retencao', { params: { periodo } });
  return response.data?.data;
};

/** Telas com histórico exportável (BiExportacaoService::TELAS) */
export type BiTelaExportavel = 'financeiro' | 'alunos' | 'retencao' | 'frequencia';

/**
 * Baixa o CSV (Excel pt-BR) de uma lista de detalhe ou do histórico de uma tela. GET /admin/bi/exportar
 * O nome do arquivo vem da API (Content-Disposition).
 */
export const exportarBi = async (alvo: { indicador?: BiIndicadorDetalhe; tela?: BiTelaExportavel | 'global' }, periodo: BiPeriodo): Promise<void> => {
  // O ranking do BI Global sai pela rota do superadmin
  const response = alvo.tela === 'global'
    ? await api.get('/superadmin/bi/global/exportar', { params: { periodo }, responseType: 'blob' })
    : await api.get('/admin/bi/exportar', { params: { ...alvo, periodo }, responseType: 'blob' });
  const disp = String(response.headers?.['content-disposition'] || '');
  const nome = /filename="([^"]+)"/.exec(disp)?.[1] || `bi-${alvo.indicador || alvo.tela}.csv`;
  const url = URL.createObjectURL(response.data);
  const a = document.createElement('a');
  a.href = url;
  a.download = nome;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
};

/** BI Global (superadmin): academias somadas, ranking e receita da plataforma. GET /superadmin/bi/global */
export const buscarBiGlobal = async (periodo: BiPeriodo): Promise<any> => {
  const response = await api.get('/superadmin/bi/global', { params: { periodo } });
  return response.data?.data;
};

/** Alunos e matrículas: entradas, retornos, saídas, retenção e distribuição. GET /admin/bi/alunos */
export const buscarBiAlunos = async (periodo: BiPeriodo): Promise<any> => {
  const response = await api.get('/admin/bi/alunos', { params: { periodo } });
  return response.data?.data;
};

/** Financeiro: indicadores, receita por plano e forma, histórico mensal. GET /admin/bi/financeiro */
export const buscarBiFinanceiro = async (periodo: BiPeriodo): Promise<any> => {
  const response = await api.get('/admin/bi/financeiro', { params: { periodo } });
  return response.data?.data;
};

/** Registros que compõem um indicador no período. GET /admin/bi/visao-geral/detalhe */
export const buscarBiDetalhe = async (indicador: BiIndicadorDetalhe, periodo: BiPeriodo): Promise<any> => {
  const response = await api.get('/admin/bi/visao-geral/detalhe', { params: { indicador, periodo } });
  return response.data?.data;
};

export default { buscarBiVisaoGeral, buscarBiDetalhe, buscarBiFinanceiro, buscarBiAlunos, buscarBiFrequencia, buscarBiRetencao, buscarBiGlobal, exportarBi };
