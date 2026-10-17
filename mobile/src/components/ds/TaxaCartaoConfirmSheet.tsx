import React, { useEffect, useState } from "react";
import { Text } from "react-native";
import { nomeProvedorPagamento } from "@/src/utils/metodosPagamento";
import { linhasTaxaCartao, type TaxaCartaoSimulacao } from "@/src/utils/taxaCartao";
import ParcelasCartaoPicker from "./ParcelasCartaoPicker";
import PaySheet, { PaySteps, payStyles } from "./PaySheet";
import ProviderLogo, { logoProvedor } from "./ProviderLogo";

type Props = {
  visible: boolean;
  simulacao: TaxaCartaoSimulacao | null;
  tituloPlano?: string | null;
  loading?: boolean;
  onConfirm: (simulacao: TaxaCartaoSimulacao) => void;
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
  const [atual, setAtual] = useState<TaxaCartaoSimulacao | null>(simulacao);

  useEffect(() => {
    if (visible) setAtual(simulacao);
  }, [visible, simulacao]);

  const nome = nomeProvedorPagamento(atual?.provider || simulacao?.provider, null);
  const n = atual?.parcela_count ?? 1;
  const labelContinuar =
    atual && n > 1
      ? `Continuar · ${atual.rotulo_parcela}`
      : `Continuar no ${nome}`;

  return (
    <PaySheet
      visible={visible}
      icon="credit-card"
      media={logoProvedor(atual?.provider) ? <ProviderLogo provider={atual?.provider} name={nome} size="lg" /> : null}
      eyebrow="Pagamento com cartão"
      title="Confira a taxa antes de pagar"
      description="A taxa do cartão é cobrada pelo Asaas e muda se você parcelar. Escolha aqui — no checkout essa quantidade fica travada."
      resumo={
        atual
          ? {
              titulo: tituloPlano || "Plano",
              linhas: linhasTaxaCartao(atual),
            }
          : null
      }
      secureNote="Os dados do cartão ficam com o Asaas. O AppCheckin não os guarda."
      primary={{
        label: labelContinuar,
        iconRight: "arrow-right",
        loading: !!loading,
        disabled: !atual || !!loading,
        onPress: () => {
          if (atual) onConfirm(atual);
        },
      }}
      onClose={onClose}
    >
      {atual ? <ParcelasCartaoPicker simulacao={atual} onChange={setAtual} /> : null}
      <PaySteps
        steps={[
          <>
            Você escolhe à vista ou parcelas e confirma o total com a{" "}
            <Text style={payStyles.bold}>taxa do cartão</Text>.
          </>,
          <>
            Abrimos o checkout seguro do <Text style={payStyles.bold}>{nome}</Text> já com essa opção.
          </>,
          <>O plano ativa quando o pagamento for aprovado.</>,
        ]}
      />
    </PaySheet>
  );
}
