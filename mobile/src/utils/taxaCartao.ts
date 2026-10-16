import { postComRetryGateway, type EscolherGatewayPix } from "@/src/utils/pagamentoPix";

export type TaxaCartaoSimulacao = {
  provider: string;
  aplica_repasse: boolean;
  valor_plano: number;
  valor_plano_formatado: string;
  tarifa: number;
  tarifa_formatada: string;
  valor_cobrar: number;
  valor_cobrar_formatado: string;
  fee_percentage?: number | null;
  operation_fee?: number | null;
  aviso?: string | null;
};

export function linhasTaxaCartao(sim: TaxaCartaoSimulacao) {
  const linhas = [
    { label: "Valor do plano", valor: sim.valor_plano_formatado },
    { label: "Taxa do cartão (Asaas)", valor: sim.tarifa_formatada },
  ];
  return [
    ...linhas,
    { label: "Você paga", valor: sim.valor_cobrar_formatado, destaque: true },
  ];
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
