import React, { useEffect, useState } from "react";
import { StyleSheet, View } from "react-native";
import { idProvedorPagamento } from "@/src/utils/metodosPagamento";
import type { PixGateway } from "@/src/utils/pagamentoPix";
import PaySheet from "./PaySheet";
import ProviderLogo, { logoProvedor } from "./ProviderLogo";
import SelectRow from "./SelectRow";
import { space } from "./tokens";

type Props = {
  visible: boolean;
  gateways: PixGateway[];
  /** Forma de pagamento em andamento ("pix", "cartao", "boleto"), para os textos da folha. */
  metodo?: string | null;
  /** Provedor usado da última vez (pré-selecionado, com a tag "Último usado"). */
  ultimoUsado?: string | null;
  onSelect: (id: string) => void;
  onCancel: () => void;
};

function subtitulo(g: PixGateway, metodo: string): string {
  if (g.environment === "sandbox") return "Ambiente de testes";
  if (metodo === "pix") return "QR Code e copia e cola no app";
  if (metodo === "boleto") return "Boleto emitido pelo provedor";
  if ((idProvedorPagamento(g.id) ?? idProvedorPagamento(g.nome)) === "mercadopago") {
    return "Crédito ou saldo da conta";
  }
  return "Crédito à vista · checkout seguro";
}

// Folha "Escolha onde pagar" (design system · TelasPagamento): a logo de cada provedor numa SelectRow;
// tocar seleciona e o botão principal confirma.
export default function GatewayPickerModal({ visible, gateways, metodo, ultimoUsado, onSelect, onCancel }: Props) {
  const [selecionado, setSelecionado] = useState<string | null>(null);

  useEffect(() => {
    if (!visible) return;
    const lembrado = gateways.find((g) => g.id === ultimoUsado)?.id;
    setSelecionado(lembrado ?? gateways[0]?.id ?? null);
  }, [visible, gateways, ultimoUsado]);

  const m = String(metodo || "").toLowerCase();
  const pix = m === "pix";
  const boleto = m === "boleto";
  const escolhido = gateways.find((g) => g.id === selecionado) || null;
  const temSandbox = gateways.some((g) => g.environment === "sandbox");

  return (
    <PaySheet
      visible={visible}
      icon={pix ? "pix" : boleto ? "file-text" : "credit-card"}
      eyebrow={pix ? "Pagamento com PIX" : boleto ? "Pagamento com boleto" : m === "cartao" ? "Pagamento com cartão" : "Pagamento"}
      title={pix ? "Escolha o provedor do PIX" : "Escolha onde pagar"}
      description={
        temSandbox
          ? "Sandbox só aparece para Admin. Aluno vê apenas produção."
          : pix
            ? "O QR Code é gerado pelo provedor escolhido e você paga sem sair do app."
            : "Você paga no site seguro do provedor e volta para o app quando terminar."
      }
      secureNote={
        m === "cartao" || !m
          ? "Os dados do cartão ficam com o provedor. O AppCheckin não os guarda."
          : "O pagamento é processado pelo provedor. O AppCheckin não guarda seus dados bancários."
      }
      primary={{
        label: escolhido ? (pix ? `Gerar PIX no ${escolhido.nome}` : `Continuar no ${escolhido.nome}`) : "Continuar",
        iconRight: pix ? "arrow-right" : "external-link",
        disabled: !escolhido,
        onPress: () => escolhido && onSelect(escolhido.id),
      }}
      onClose={onCancel}
    >
      <View style={styles.list} accessibilityRole="radiogroup" accessibilityLabel="Provedor de pagamento">
        {gateways.map((g) => (
          <SelectRow
            key={g.id}
            title={g.nome}
            lead={
              logoProvedor(g.id) ? (
                <ProviderLogo provider={g.id} name={g.nome} />
              ) : logoProvedor(g.nome) ? (
                <ProviderLogo provider={g.nome} name={g.nome} />
              ) : null
            }
            subtitle={subtitulo(g, m)}
            tag={g.id === ultimoUsado ? "Último usado" : null}
            selected={g.id === selecionado}
            onSelect={() => setSelecionado(g.id)}
          />
        ))}
      </View>
    </PaySheet>
  );
}

const styles = StyleSheet.create({
  list: { gap: space[2] },
});
