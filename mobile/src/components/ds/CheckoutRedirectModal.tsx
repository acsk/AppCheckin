import React from "react";
import { Text } from "react-native";
import { nomeProvedorPagamento } from "@/src/utils/metodosPagamento";
import PaySheet, { PaySteps, payStyles, type PayResumo } from "./PaySheet";

type Props = {
  visible: boolean;
  url: string | null;
  provider?: string | null;
  metodo?: string | null;
  /** Plano · ciclo e valor, mostrados no resumo da folha. */
  resumo?: PayResumo | null;
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
export default function CheckoutRedirectModal({ visible, url, provider, metodo, resumo, onRedirect, onClose }: Props) {
  const nome = nomeProvedorPagamento(provider, url);
  const metodoLabel = rotuloMetodo(metodo);
  const boleto = metodoLabel === "boleto";

  return (
    <PaySheet
      visible={visible}
      icon={boleto ? "file-text" : metodoLabel === "PIX" ? "pix" : "credit-card"}
      eyebrow={`Pagamento com ${metodoLabel}`}
      title={`Continuar no ${nome}`}
      description={`O pagamento com ${metodoLabel} é concluído no site seguro do ${nome}.`}
      resumo={resumo}
      secureNote={
        boleto
          ? `O boleto é emitido pelo ${nome}. O AppCheckin não guarda seus dados de pagamento.`
          : "Os dados do cartão ficam com o provedor. O AppCheckin não os guarda."
      }
      primary={{ label: `Ir para o ${nome}`, iconRight: "external-link", disabled: !url, onPress: onRedirect }}
      onClose={onClose}
    >
      <PaySteps
        steps={[
          <>
            Abrimos o checkout do <Text style={payStyles.bold}>{nome}</Text> no navegador.
          </>,
          boleto ? "Você gera e paga o boleto." : `Você paga com ${metodoLabel === "PIX" ? "o PIX" : "o cartão de crédito"}.`,
          "Volte ao app: o plano ativa sozinho quando o pagamento for aprovado.",
        ]}
      />
    </PaySheet>
  );
}
