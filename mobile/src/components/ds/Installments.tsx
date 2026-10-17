import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { ds, space } from "./tokens";

export type InstallmentOption = { id: string; label: string; amount: string };

type Props = { options: InstallmentOption[]; value: string; onChange: (id: string) => void; label?: string };

// Parcelas do cartão em grade de 4 colunas (design system · Installments): "3x" e o valor da parcela.
export default function Installments({ options, value, onChange, label = "Parcelas" }: Props) {
  return (
    <View style={styles.grid} accessibilityRole="radiogroup" accessibilityLabel={label}>
      {options.map((o) => {
        const on = o.id === value;
        return (
          <TouchableOpacity
            key={o.id}
            activeOpacity={0.85}
            onPress={() => onChange(o.id)}
            accessibilityRole="radio"
            accessibilityState={{ checked: on }}
            accessibilityLabel={`${o.label}, ${o.amount}`}
            style={[styles.opt, on && styles.optOn]}
          >
            <Text style={[styles.title, on && { color: ds.brandInk }]}>{o.label}</Text>
            <Text style={[styles.amount, on && { color: ds.brandInk }]} numberOfLines={1}>
              {o.amount}
            </Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  grid: { flexDirection: "row", flexWrap: "wrap", gap: space[2] },
  opt: {
    flexBasis: "22%",
    flexGrow: 1,
    maxWidth: "25%",
    minWidth: 0,
    alignItems: "center",
    gap: 1,
    paddingVertical: 8,
    paddingHorizontal: 4,
    borderRadius: 12,
    backgroundColor: ds.surface,
    borderWidth: 1.5,
    borderColor: ds.line,
  },
  optOn: { backgroundColor: ds.brandSoft, borderWidth: 2, borderColor: ds.brand },
  title: { fontSize: 15, lineHeight: 20, fontWeight: "900", color: ds.ink },
  amount: { fontSize: 11, lineHeight: 14, fontWeight: "700", color: ds.inkMuted, fontVariant: ["tabular-nums"] },
});
