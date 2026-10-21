import React from "react";
import { ScrollView, StyleSheet, Text, TouchableOpacity } from "react-native";
import { ds, radius, space } from "./tokens";

export type ChipOption = { id: string; label: string; count?: number | null };

type Props = {
  options: ChipOption[];
  value: string;
  onChange: (id: string) => void;
  label?: string;
};

// Filtro de uma escolha em chips roláveis (estilos de nado, provas). Escolhido em grafite.
export default function ChipFilter({ options, value, onChange, label }: Props) {
  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      style={styles.scroll}
      contentContainerStyle={styles.row}
      accessibilityRole="radiogroup"
      accessibilityLabel={label}
    >
      {options.map((o) => {
        const on = o.id === value;
        return (
          <TouchableOpacity
            key={o.id}
            accessibilityRole="radio"
            accessibilityState={{ checked: on }}
            onPress={() => onChange(o.id)}
            activeOpacity={0.85}
            style={[styles.chip, on && styles.chipOn]}
          >
            <Text style={[styles.text, on && { color: ds.surface }]}>{o.label}</Text>
            {o.count != null ? <Text style={[styles.count, on && styles.countOn]}>{o.count}</Text> : null}
          </TouchableOpacity>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  // Sangra até a borda da tela: a lista rola por baixo do respiro lateral.
  scroll: { marginHorizontal: -space[4], flexGrow: 0 },
  row: { gap: space[2], paddingHorizontal: space[4], paddingVertical: 2 },
  chip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    height: 36,
    paddingHorizontal: 14,
    borderRadius: radius.full,
    backgroundColor: ds.surface,
    borderWidth: 1.5,
    borderColor: ds.line,
  },
  // ink/surface (e não inverse) para o escolhido continuar contrastando no tema escuro.
  chipOn: { backgroundColor: ds.ink, borderColor: ds.ink },
  text: { fontSize: 14, fontWeight: "800", color: ds.ink },
  count: { fontSize: 12, fontWeight: "800", color: ds.inkMuted },
  countOn: { color: ds.surface, opacity: 0.7 },
});
