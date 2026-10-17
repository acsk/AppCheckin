import React from "react";
import { Text } from "react-native";
import { nomeProvedorPagamento } from "@/src/utils/metodosPagamento";
import { linhasTaxaCartao, type TaxaCartaoSimulacao } from "@/src/utils/taxaCartao";
import PaySheet, { PaySteps, payStyles, type PayResumo } from "./PaySheet";
import ProviderLogo, { logoProvedor } from "./ProviderLogo";

type Props = {
  visible: boolean;
  url: string | null;
  provider?: string | null;
  metodo?: string | null;
  /** Plano · ciclo e valor, mostrados no resumo da folha. */
  resumo?: PayResumo | null;
  tarifaCartao?: TaxaCartaoSimulacao | null;
  detalharTaxas?: boolean;
  onRedirect: () => void;
  onClose: () => void;
};

function rotuloMetodo(metodo?: string | null): string {
  const id = String(metodo || "").toLowerCase();
  if (id === "boleto") return "boleto";
  if (id === "pix") return "PIX";
  return "cartão";
}

// Folha "Continuar no {provedor}" (design system · TelasPagamento, um só provedor):
// resumo do valor + 3 passos do checkout externo + nota de segurança.
export default function CheckoutRedirectModal({ visible, url, provider, metodo, resumo, tarifaCartao, detalharTaxas = false, onRedirect, onClose }: Props) {
  const nome = nomeProvedorPagamento(provider, url);
  const metodoLabel = rotuloMetodo(metodo);
  const boleto = metodoLabel === "boleto";

  return (
    <PaySheet
      visible={visible}
      icon={boleto ? "file-text" : metodoLabel === "PIX" ? "pix" : "credit-card"}
      media={logoProvedor(provider, url) ? <ProviderLogo provider={provider} url={url} name={nome} size="lg" /> : null}
      eyebrow={`Pagamento com ${metodoLabel}`}
      title={`Continuar no ${nome}`}
      description={`O pagamento com ${metodoLabel} é concluído no site seguro do ${nome}.`}
      resumo={
        tarifaCartao?.aplica_repasse
          ? { ...resumo, valor: undefined, linhas: linhasTaxaCartao(tarifaCartao, detalharTaxas) }
          : resumo
      }
      secureNote={
        boleto
          ? `O boleto é emitido pelo ${nome}. O AppCheckin não guarda seus dados de pagamento.`
          : `Os dados do cartão ficam com o ${nome}. O AppCheckin não os guarda.`
      }
      primary={{ label: `Ir para o ${nome}`, iconRight: "external-link", disabled: !url, onPress: onRedirect }}
      onClose={onClose}
    >
      <PaySteps
        steps={[
          <>
            Abrimos o <Text style={payStyles.bold}>checkout do {nome}</Text> no navegador.
          </>,
          <>
            Você {boleto ? "gera e paga o " : "paga com o "}
            <Text style={payStyles.bold}>{boleto ? "boleto" : metodoLabel === "PIX" ? "PIX" : "cartão de crédito"}</Text>.
          </>,
          <>
            Volte ao app: o plano <Text style={payStyles.bold}>ativa sozinho</Text> quando o pagamento for aprovado.
          </>,
        ]}
      />
    </PaySheet>
  );
}
