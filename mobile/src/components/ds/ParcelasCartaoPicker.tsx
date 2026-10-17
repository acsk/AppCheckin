import React from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import {
  aplicarParcelaSelecionada,
  opcoesParcelasCartao,
  type TaxaCartaoSimulacao,
} from "@/src/utils/taxaCartao";
import { ds, space } from "./tokens";

type Props = {
  simulacao: TaxaCartaoSimulacao;
  onChange: (next: TaxaCartaoSimulacao) => void;
};

export default function ParcelasCartaoPicker({ simulacao, onChange }: Props) {
  const opcoes = opcoesParcelasCartao(simulacao);
  if (opcoes.length <= 1) return null;

  const selecionada = simulacao.parcela_count ?? 1;

  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>Parcelar no cartão</Text>
      <Text style={styles.hint}>A taxa muda conforme o número de parcelas. A academia recebe o valor do plano.</Text>
      <View style={styles.chips}>
        {opcoes.map((op) => {
          const n = op.parcela_count ?? 1;
          const on = n === selecionada;
          return (
            <Pressable
              key={n}
              onPress={() => onChange(aplicarParcelaSelecionada(simulacao, n))}
              accessibilityRole="radio"
              accessibilityState={{ checked: on }}
              accessibilityLabel={op.rotulo_parcela}
              style={[styles.chip, on && styles.chipOn]}
            >
              <Text style={[styles.chipTitle, on && styles.chipTitleOn]}>
                {n <= 1 ? "À vista" : `${n}x`}
              </Text>
              <Text style={[styles.chipSub, on && styles.chipSubOn]} numberOfLines={1}>
                {n <= 1 ? op.valor_cobrar_formatado : op.valor_parcela_formatado}
              </Text>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { gap: 8, width: "100%" },
  label: { fontSize: 13, lineHeight: 18, fontWeight: "800", color: ds.ink },
  hint: { fontSize: 12, lineHeight: 16, fontWeight: "500", color: ds.inkMuted },
  chips: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: {
    minWidth: 88,
    flexGrow: 1,
    paddingVertical: 10,
    paddingHorizontal: space[3],
    borderRadius: 14,
    borderWidth: 1.5,
    borderColor: ds.line,
    backgroundColor: ds.surface,
    alignItems: "center",
  },
  chipOn: {
    borderColor: ds.brand,
    backgroundColor: ds.brandSoft,
  },
  chipTitle: { fontSize: 13, lineHeight: 16, fontWeight: "800", color: ds.ink },
  chipTitleOn: { color: ds.brandInk },
  chipSub: { fontSize: 12, lineHeight: 16, fontWeight: "600", color: ds.inkMuted, fontVariant: ["tabular-nums"] },
  chipSubOn: { color: ds.ink },
});
