import { postComRetryGateway, type EscolherGatewayPix } from "@/src/utils/pagamentoPix";

export type TaxaCartaoParcela = {
  provider: string;
  aplica_repasse: boolean;
  valor_plano: number;
  valor_plano_formatado: string;
  tarifa: number;
  tarifa_formatada: string;
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

export function linhasTaxaCartao(sim: TaxaCartaoSimulacao) {
  const n = sim.parcela_count ?? 1;
  const linhas = [
    { label: "Valor do plano", valor: sim.valor_plano_formatado },
    { label: "Taxa do cartão (Asaas)", valor: sim.tarifa_formatada },
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
