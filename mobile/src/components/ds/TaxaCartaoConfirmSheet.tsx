import React from "react";
import { Text } from "react-native";
import { nomeProvedorPagamento } from "@/src/utils/metodosPagamento";
import { linhasTaxaCartao, type TaxaCartaoSimulacao } from "@/src/utils/taxaCartao";
import PaySheet, { PaySteps, payStyles } from "./PaySheet";
import ProviderLogo, { logoProvedor } from "./ProviderLogo";

type Props = {
  visible: boolean;
  simulacao: TaxaCartaoSimulacao | null;
  tituloPlano?: string | null;
  loading?: boolean;
  onConfirm: () => void;
  onClose: () => void;
};

export default function TaxaCartaoConfirmSheet({
  visible,
  simulacao,
  tituloPlano,
  loading,
  onConfirm,
  onClose,
}: Props) {
  const nome = nomeProvedorPagamento(simulacao?.provider, null);

  return (
    <PaySheet
      visible={visible}
      icon="credit-card"
      media={logoProvedor(simulacao?.provider) ? <ProviderLogo provider={simulacao?.provider} name={nome} size="lg" /> : null}
      eyebrow="Pagamento com cartão"
      title="Confira a taxa antes de pagar"
      description="A taxa do cartão é cobrada pelo Asaas. Ela entra no valor para a academia receber o preço cheio do plano."
      resumo={
        simulacao
          ? {
              titulo: tituloPlano || "Plano",
              linhas: linhasTaxaCartao(simulacao),
            }
          : null
      }
      secureNote="Os dados do cartão ficam com o Asaas. O AppCheckin não os guarda."
      primary={{
        label: `Continuar no ${nome}`,
        iconRight: "arrow-right",
        loading: !!loading,
        disabled: !simulacao || !!loading,
        onPress: onConfirm,
      }}
      onClose={onClose}
    >
      <PaySteps
        steps={[
          <>
            Você confirma o total com a <Text style={payStyles.bold}>taxa do cartão</Text>.
          </>,
          <>
            Abrimos o checkout seguro do <Text style={payStyles.bold}>{nome}</Text>.
          </>,
          <>O plano ativa quando o pagamento for aprovado.</>,
        ]}
      />
    </PaySheet>
  );
}
