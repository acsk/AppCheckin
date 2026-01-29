/**
 * Models/Interfaces do projeto AppCheckin Painel
 *
 * Este módulo exporta todos os tipos e valores default utilizados no projeto.
 *
 * Uso:
 * ```typescript
 * import { DashboardCardsData, DASHBOARD_CARDS_DEFAULT } from '../models';
 * ```
 */

// Dashboard
export type {
  TotalAlunos,
  ReceitaMensal,
  CheckinsHoje,
  PlanosVencendo,
  DashboardCardsData,
  DashboardCardsResponse,
} from './dashboard';

export { DASHBOARD_CARDS_DEFAULT } from './dashboard';
