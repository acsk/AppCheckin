/**
 * Interfaces do Dashboard
 */

export interface TotalAlunos {
  /** Total de alunos cadastrados */
  total: number;
  /** Quantidade de alunos ativos */
  ativos: number;
  /** Quantidade de alunos inativos */
  inativos: number;
}

export interface ReceitaMensal {
  /** Valor numérico da receita mensal */
  valor: number;
  /** Valor formatado em R$ (ex: "R$ 1.500,00") */
  valor_formatado: string;
  /** Quantidade de contas pendentes */
  contas_pendentes: number;
}

export interface CheckinsHoje {
  /** Quantidade de check-ins realizados hoje */
  hoje: number;
  /** Quantidade de check-ins realizados no mês */
  no_mes: number;
}

export interface PlanosVencendo {
  /** Quantidade de planos vencendo */
  vencendo: number;
  /** Quantidade de novos alunos neste mês */
  novos_este_mes: number;
}

export interface DashboardCardsData {
  /** Dados de total de alunos */
  total_alunos: TotalAlunos;
  /** Dados de receita mensal */
  receita_mensal: ReceitaMensal;
  /** Dados de check-ins de hoje */
  checkins_hoje: CheckinsHoje;
  /** Dados de planos vencendo */
  planos_vencendo: PlanosVencendo;
}

export interface DashboardCardsResponse {
  /** Indica se a requisição foi bem sucedida */
  success: boolean;
  /** Dados dos cards do dashboard */
  data: DashboardCardsData;
}
