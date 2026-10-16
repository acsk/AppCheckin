import React, { useEffect, useState } from "react";
import { StyleSheet, View } from "react-native";
import type { PixGateway } from "@/src/utils/pagamentoPix";
import PaySheet from "./PaySheet";
import SelectRow from "./SelectRow";
import { space } from "./tokens";

type Props = {
  visible: boolean;
  gateways: PixGateway[];
  /** Provedor usado da última vez (pré-selecionado, com a tag "Último usado"). */
  ultimoUsado?: string | null;
  onSelect: (id: string) => void;
  onCancel: () => void;
};

const MARCA: Record<string, string> = { mercadopago: "MP", mercado_pago: "MP", asaas: "AS" };

const marca = (g: PixGateway) => MARCA[String(g.id).toLowerCase().replace(/[^a-z_]/g, "")] || null;

// Folha "Escolha onde pagar" (design system · TelasPagamento): escolhe o provedor e confirma.
export default function GatewayPickerModal({ visible, gateways, ultimoUsado, onSelect, onCancel }: Props) {
  const [selecionado, setSelecionado] = useState<string | null>(null);

  useEffect(() => {
    if (!visible) return;
    const lembrado = gateways.find((g) => g.id === ultimoUsado)?.id;
    setSelecionado(lembrado ?? gateways[0]?.id ?? null);
  }, [visible, gateways, ultimoUsado]);

  const escolhido = gateways.find((g) => g.id === selecionado) || null;
  const temSandbox = gateways.some((g) => g.environment === "sandbox");

  return (
    <PaySheet
      visible={visible}
      icon="credit-card"
      eyebrow="Pagamento"
      title="Escolha onde pagar"
      description={
        temSandbox
          ? "Sandbox só aparece para Admin. Aluno vê apenas produção."
          : "Você paga no site seguro do provedor e volta para o app quando terminar."
      }
      secureNote="Os dados de pagamento ficam com o provedor. O AppCheckin não os guarda."
      primary={{
        label: escolhido ? `Continuar no ${escolhido.nome}` : "Continuar",
        iconRight: "external-link",
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
            mark={marca(g)}
            subtitle={g.environment === "sandbox" ? "Ambiente de testes" : "Checkout seguro do provedor"}
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
