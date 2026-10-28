/** Decide apenas a revisão necessária; valores e elegibilidade continuam sendo validados pela API. */
export function precisaRevisaoMigracao(plano: {
  pode_migrar?: boolean;
  is_plano_atual?: boolean;
  matricula_ativa?: { status_codigo?: string | null; plano_ciclo_id?: number | null } | null;
}, cicloId: number): boolean {
  if (plano.pode_migrar) return true;
  return !!plano.is_plano_atual
    && String(plano.matricula_ativa?.status_codigo || '').toLowerCase() !== 'pendente'
    && (plano.matricula_ativa?.plano_ciclo_id == null || Number(plano.matricula_ativa.plano_ciclo_id) !== Number(cicloId));
}

export function metodosAssinaturaDisponiveis(metodos?: readonly string[]): ('cartao' | 'pix')[] {
  // Ofertas antigas não trazem a flag de PIX: cartão é o padrão conservador.
  return ['cartao', ...(metodos?.includes('pix') ? ['pix' as const] : [])] as ('cartao' | 'pix')[];
}
