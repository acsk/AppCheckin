import React from "react";
import { Platform, ScrollView, StyleSheet, Text, TouchableOpacity } from "react-native";
import { ds, radius, space } from "./tokens";

export type PickerDay = { id: string; weekday: string; day: number | string };

type Props = { days: PickerDay[]; value: string; onChange: (id: string) => void };

// Fila rolável de chips de data, colada abaixo do cabeçalho. O selecionado é inverse.
export default function DayPicker({ days, value, onChange }: Props) {
  return (
    <ScrollView
      horizontal
      nestedScrollEnabled
      showsHorizontalScrollIndicator={false}
      style={styles.bar}
      contentContainerStyle={styles.row}
      keyboardShouldPersistTaps="handled"
      accessibilityRole="tablist"
      accessibilityLabel="Escolha o dia"
    >
      {days.map((d) => {
        const on = d.id === value;
        return (
          <TouchableOpacity
            key={d.id}
            activeOpacity={0.8}
            focusable={Platform.OS === "web" ? false : undefined}
            onPress={() => onChange(d.id)}
            accessibilityRole="tab"
            accessibilityState={{ selected: on }}
            style={[styles.chip, on && styles.chipOn]}
          >
            <Text style={[styles.wd, on && styles.wdOn]}>{d.weekday}</Text>
            <Text style={[styles.n, on && styles.nOn]}>{d.day}</Text>
          </TouchableOpacity>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  bar: { flexGrow: 0, backgroundColor: ds.surface, borderBottomWidth: 1, borderBottomColor: ds.line },
  row: { gap: space[2], paddingVertical: space[3], paddingHorizontal: space[4] },
  chip: {
    width: 54,
    height: 64,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: ds.line,
    backgroundColor: ds.surface,
    alignItems: "center",
    justifyContent: "center",
    gap: 2,
  },
  chipOn: { backgroundColor: ds.inverse, borderColor: ds.inverse },
  wd: { fontSize: 11, lineHeight: 14, fontWeight: "800", letterSpacing: 0.66, textTransform: "uppercase", color: ds.inkMuted },
  wdOn: { color: ds.onInverse, opacity: 0.75 },
  n: { fontSize: 20, lineHeight: 24, fontWeight: "800", color: ds.ink, fontVariant: ["tabular-nums"] },
  nOn: { color: ds.onInverse },
});
