export interface PagamentoAssinatura {
  id: number;
  valor: number;
  data_vencimento?: string | null;
  data_pagamento?: string | null;
  status?: string | null;
  status_pagamento_id?: number | null;
  forma_pagamento?: string | null;
  baixado_por_nome?: string | null;
  criado_por_nome?: string | null;
  tipo_baixa_nome?: string | null;
  origem?: string | null;
}

export function isPagamentoPago(pagamento: PagamentoAssinatura): boolean {
  return (
    Number(pagamento.status_pagamento_id) === 2 ||
    !!pagamento.data_pagamento ||
    String(pagamento.status || "").toLowerCase().includes("pago")
  );
}

export function pagamentosVisiveis(
  pagamentos: readonly PagamentoAssinatura[] | null | undefined,
  hojeIso: string,
): PagamentoAssinatura[] {
  return (pagamentos ?? [])
    .filter((pagamento) => {
      if (Number(pagamento.status_pagamento_id) === 4) return false;
      if (isPagamentoPago(pagamento)) return true;
      const statusId = Number(pagamento.status_pagamento_id);
      const vencimento = String(pagamento.data_vencimento || "").slice(0, 10);
      return (
        (statusId === 1 || statusId === 3) &&
        (!vencimento || vencimento <= hojeIso)
      );
    })
    .sort((a, b) => {
      const da = String(a.data_pagamento || a.data_vencimento || "");
      const db = String(b.data_pagamento || b.data_vencimento || "");
      return db.localeCompare(da) || b.id - a.id;
    });
}

interface AssinaturaFinanceiro {
  id: number;
  status: { codigo: string };
  pagamentos?: PagamentoAssinatura[] | null;
  pode_pagar?: boolean;
  pode_renovar?: boolean;
  payment_url?: string | null;
}

export function criarCardsAssinaturas<T extends AssinaturaFinanceiro>(
  assinaturas: readonly T[],
  hojeIso: string,
): { key: string; assinatura: T; pagamentos: PagamentoAssinatura[] }[] {
  return assinaturas.flatMap((assinatura) => {
    const pagamentos = pagamentosVisiveis(assinatura.pagamentos, hojeIso);
    const codigo = assinatura.status.codigo.toLowerCase();
    if (
      pagamentos.length === 0 &&
      (codigo === "pendente" || codigo === "pending")
    ) {
      return [];
    }
    return [{ key: `assinatura-${assinatura.id}`, assinatura, pagamentos }];
  });
}

export function podePagarAssinatura(
  assinatura: AssinaturaFinanceiro,
  pagamentos: readonly PagamentoAssinatura[],
): boolean {
  const codigo = assinatura.status.codigo.toLowerCase();
  const pendente = codigo === "pendente" || codigo === "pending";
  // Histórico quitado não bloqueia a renovação autorizada pelo backend.
  return (
    !!assinatura.pode_pagar &&
    (!pendente || !!assinatura.payment_url) &&
    (pagamentos.some((p) => !isPagamentoPago(p)) ||
      !!assinatura.pode_renovar ||
      !pagamentos.some(isPagamentoPago))
  );
}
