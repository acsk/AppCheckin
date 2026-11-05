import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { ds, space } from "./tokens";

export type FreqOption = {
  id: number | string;
  /** "2x", "∞" */
  freq: string;
  /** padrão "por semana" */
  unit?: string;
  /** "R$ 130" */
  price: string;
  /** "≈ R$ 16,25" */
  perClass?: string | null;
  tag?: string | null;
  current?: boolean;
  /** Nome do plano, quando duas opções têm a mesma frequência. */
  name?: string | null;
};

type Props = {
  options: FreqOption[];
  value: FreqOption["id"] | null;
  onChange: (id: FreqOption["id"]) => void;
  label?: string;
};

// Planos individuais em grade 2×2 (rádio): "2x por semana", preço por mês e por aula.
export default function FreqGrid({ options, value, onChange, label = "Aulas por semana" }: Props) {
  return (
    <View style={styles.grid} accessibilityRole="radiogroup" accessibilityLabel={label}>
      {options.map((o) => {
        const on = value === o.id;
        const tag = o.current ? "Seu plano" : o.tag;
        return (
          <TouchableOpacity
            key={String(o.id)}
            activeOpacity={0.85}
            onPress={() => onChange(o.id)}
            accessibilityRole="radio"
            accessibilityState={{ checked: on }}
            accessibilityLabel={`${o.freq} ${o.unit || "por semana"}, ${o.price} por mês${tag ? `, ${tag}` : ""}`}
            style={[styles.cell, on && styles.cellOn]}
          >
            {tag ? (
              <View style={[styles.tag, o.current && styles.tagCurrent]}>
                <Text
                  style={[styles.tagText, o.current && styles.tagTextCurrent]}
                  numberOfLines={1}
                  adjustsFontSizeToFit
                  minimumFontScale={0.75}
                  maxFontSizeMultiplier={1.1}
                >
                  {tag}
                </Text>
              </View>
            ) : null}
            <View style={styles.freqRow}>
              <Text style={styles.freq} maxFontSizeMultiplier={1.2}>{o.freq}</Text>
              <Text style={styles.unit} numberOfLines={1} maxFontSizeMultiplier={1.2}>{o.unit || "por semana"}</Text>
            </View>
            {o.name ? <Text style={styles.name} numberOfLines={1} maxFontSizeMultiplier={1.2}>{o.name}</Text> : null}
            <Text numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.8} maxFontSizeMultiplier={1.2}>
              <Text style={styles.price}>{o.price}</Text>
              <Text style={styles.per}>/mês</Text>
            </Text>
            {o.perClass ? (
              <Text style={styles.perClass} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.8} maxFontSizeMultiplier={1.2}>
                {o.perClass} por aula
              </Text>
            ) : null}
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  grid: { flexDirection: "row", flexWrap: "wrap", justifyContent: "space-between", rowGap: space[4], paddingTop: 8 },
  cell: {
    width: "48.5%",
    position: "relative",
    gap: 2,
    padding: space[4],
    borderRadius: 18,
    backgroundColor: ds.surface,
    borderWidth: 1.5,
    borderColor: ds.line,
  },
  cellOn: { backgroundColor: ds.brandSoft, borderWidth: 2, borderColor: ds.brand },
  tag: {
    position: "absolute",
    top: -10,
    left: 12,
    maxWidth: "88%",
    height: 20,
    paddingHorizontal: 8,
    borderRadius: 999,
    justifyContent: "center",
    backgroundColor: ds.successSoft,
    zIndex: 1,
  },
  tagCurrent: { backgroundColor: ds.inverse },
  tagText: { fontSize: 10, fontWeight: "900", letterSpacing: 0.6, textTransform: "uppercase", color: ds.success },
  tagTextCurrent: { color: ds.onInverse },
  freqRow: { flexDirection: "row", alignItems: "baseline", gap: 6, marginBottom: 6 },
  freq: { fontSize: 30, lineHeight: 32, fontWeight: "900", letterSpacing: -1.2, color: ds.brandInk },
  unit: { flexShrink: 1, fontSize: 12, lineHeight: 16, fontWeight: "800", color: ds.inkMuted },
  name: { fontSize: 12, lineHeight: 16, fontWeight: "800", color: ds.ink, marginTop: -4 },
  price: { fontSize: 20, lineHeight: 24, fontWeight: "900", letterSpacing: -0.4, color: ds.ink, fontVariant: ["tabular-nums"] },
  per: { fontSize: 12, fontWeight: "700", color: ds.inkMuted },
  perClass: { fontSize: 12, lineHeight: 16, fontWeight: "600", color: ds.inkMuted, fontVariant: ["tabular-nums"] },
});
