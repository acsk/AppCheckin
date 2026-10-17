import { postComRetryGateway, type EscolherGatewayPix } from "@/src/utils/pagamentoPix";

export type TaxaCartaoParcela = {
  provider: string;
  aplica_repasse: boolean;
  valor_plano: number;
  valor_plano_formatado: string;
  tarifa: number;
  tarifa_formatada: string;
  sobretaxa?: number;
  sobretaxa_formatada?: string;
  sobretaxa_percentual?: number | null;
  liquido_estimado?: number | null;
  valor_cobrar: number;
  valor_cobrar_formatado: string;
  parcela_count: number;
  valor_parcela: number;
  valor_parcela_formatado: string;
  rotulo_parcela: string;
  fee_percentage?: number | null;
  operation_fee?: number | null;
};

export type TaxaCartaoSimulacao = TaxaCartaoParcela & {
  aviso?: string | null;
  parcelas?: TaxaCartaoParcela[];
};

export function opcoesParcelasCartao(sim: TaxaCartaoSimulacao | null | undefined): TaxaCartaoParcela[] {
  if (!sim) return [];
  if (sim.parcelas && sim.parcelas.length > 0) return sim.parcelas;
  return [sim];
}

export function aplicarParcelaSelecionada(
  sim: TaxaCartaoSimulacao,
  count: number,
): TaxaCartaoSimulacao {
  const lista = opcoesParcelasCartao(sim);
  const escolhida = lista.find((p) => (p.parcela_count ?? 1) === count) || lista[0];
  return { ...sim, ...escolhida, parcelas: lista };
}

export type LinhaTaxaCartao = {
  label: string;
  valor: string;
  hint?: string | null;
  destaque?: boolean;
};

function formatBrl(valor: number): string {
  return new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(valor);
}

function rotuloPctMes(pct?: number | null): string | null {
  if (pct == null || !Number.isFinite(Number(pct))) return null;
  return `${Number(pct).toLocaleString("pt-BR", { minimumFractionDigits: 0, maximumFractionDigits: 2 })}% ao mês`;
}

/** Taxa Asaas + sobretaxa: aluno vê uma linha; admin/sandbox vê o detalhe. */
export function linhasSomenteTaxa(
  sim: TaxaCartaoParcela,
  detalhar: boolean,
): LinhaTaxaCartao[] {
  const tarifa = Number(sim.tarifa) || 0;
  const sobre = Number(sim.sobretaxa) || 0;
  const total = tarifa + sobre;
  if (total <= 0.001) return [];

  if (!detalhar) {
    return [{ label: "Taxa", valor: formatBrl(total) }];
  }

  const n = sim.parcela_count ?? 1;
  const provedor = String(sim.provider || "").toLowerCase() === "mercadopago" ? "Mercado Pago" : "Asaas";
  const linhas: LinhaTaxaCartao[] = [];
  if (tarifa > 0.001) {
    linhas.push({
      label: "Taxa do cartão",
      hint: [provedor, n > 1 ? `${n}x` : "à vista"].join(" · "),
      valor: sim.tarifa_formatada || formatBrl(tarifa),
    });
  }
  if (sobre > 0.001) {
    linhas.push({
      label: "Sobretaxa de antecipação",
      hint: rotuloPctMes(sim.sobretaxa_percentual),
      valor: sim.sobretaxa_formatada || formatBrl(sobre),
    });
  }
  return linhas;
}

export function linhasTaxaCartao(sim: TaxaCartaoSimulacao, detalhar = false): LinhaTaxaCartao[] {
  const n = sim.parcela_count ?? 1;
  const linhas: LinhaTaxaCartao[] = [
    { label: "Valor do plano", valor: sim.valor_plano_formatado },
    ...linhasSomenteTaxa(sim, detalhar),
  ];
  if (n > 1) {
    linhas.push({ label: "Total", valor: sim.valor_cobrar_formatado });
    linhas.push({
      label: "Você paga",
      valor: sim.rotulo_parcela || `${n}x de ${sim.valor_parcela_formatado}`,
      destaque: true,
    });
  } else {
    linhas.push({ label: "Você paga", valor: sim.valor_cobrar_formatado, destaque: true });
  }
  return linhas;
}

export async function simularTaxaCartao(params: {
  apiUrl: string;
  token: string;
  valor: number;
  gateway?: string;
  escolherGateway?: EscolherGatewayPix;
  onEscolherGateway?: () => void;
  podeSandbox?: boolean;
}) {
  return postComRetryGateway({
    url: `${params.apiUrl}/mobile/simular-taxa-cartao`,
    token: params.token,
    body: {
      valor: params.valor,
      ...(params.gateway ? { gateway: params.gateway } : {}),
    },
    escolherGateway: params.escolherGateway,
    onEscolherGateway: params.onEscolherGateway,
    podeSandbox: params.podeSandbox,
  });
}
