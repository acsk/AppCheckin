import React from "react";
import { Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { ds } from "./tokens";

export type PickerDay = { id: string; weekday: string; day: number | string; count?: number | null };

type Props = {
  days: PickerDay[];
  value?: string | null;
  today?: string | null;
  onChange?: (id: string) => void;
};

// Dias da semana sobre o degradê laranja (dentro do AppHeader `bottom`), com o dia
// escolhido em grafite. Hoje ganha contorno grafite e o rótulo "HOJE".
export default function DayPicker({ days, value, today, onChange }: Props) {
  const fill = days.length <= 7;
  const chips = days.map((d) => {
    const on = d.id === value;
    const isToday = d.id === today;
    const empty = d.count === 0;
    return (
      <Pressable
        key={d.id}
        onPress={() => onChange?.(d.id)}
        accessibilityRole="tab"
        accessibilityState={{ selected: on }}
        accessibilityLabel={`${d.weekday} ${d.day}${isToday ? ", hoje" : ""}${d.count != null ? `, ${d.count} aulas` : ""}`}
        style={({ pressed }) => [
          styles.chip,
          fill ? styles.chipFill : styles.chipFixed,
          empty && styles.chipEmpty,
          isToday && !on && styles.chipToday,
          on && styles.chipActive,
          pressed && { transform: [{ scale: 0.95 }] },
        ]}
      >
        <Text style={[styles.wd, on && { color: ds.brandYellow }, empty && styles.dim]}>
          {isToday ? "HOJE" : d.weekday}
        </Text>
        <Text style={[styles.n, on && { color: ds.onInverse }, empty && styles.dim]}>{d.day}</Text>
        <View style={styles.dots}>
          {d.count
            ? Array.from({ length: Math.min(d.count, 3) }).map((_, i) => (
                <View key={i} style={[styles.dot, on && { backgroundColor: ds.brand }]} />
              ))
            : null}
        </View>
      </Pressable>
    );
  });

  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      scrollEnabled={!fill}
      contentContainerStyle={[styles.row, fill && { flexGrow: 1 }]}
      accessibilityRole="tablist"
      accessibilityLabel="Escolha o dia"
    >
      {chips}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  row: { gap: 6, paddingTop: 8, paddingBottom: 16, paddingHorizontal: 16 },
  chip: {
    height: 70,
    borderRadius: 14,
    backgroundColor: "rgba(255, 255, 255, 0.28)",
    borderWidth: 1,
    borderColor: "rgba(255, 255, 255, 0.45)",
    alignItems: "center",
    justifyContent: "center",
    gap: 1,
  },
  chipFill: { flex: 1, minWidth: 42 },
  chipFixed: { width: 48 },
  chipEmpty: { backgroundColor: "rgba(255, 255, 255, 0.12)", borderColor: "rgba(255, 255, 255, 0.25)" },
  chipToday: { borderWidth: 2, borderColor: ds.ink },
  chipActive: {
    backgroundColor: ds.ink,
    borderColor: ds.ink,
    transform: [{ translateY: -2 }],
    shadowColor: ds.ink,
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.4,
    shadowRadius: 10,
    elevation: 6,
  },
  wd: { fontSize: 11, lineHeight: 14, fontWeight: "900", letterSpacing: 0.66, color: ds.ink, textTransform: "uppercase" },
  n: { fontSize: 22, lineHeight: 24, fontWeight: "900", letterSpacing: -0.4, color: ds.ink, fontVariant: ["tabular-nums"] },
  dim: { opacity: 0.7 },
  dots: { flexDirection: "row", gap: 3, height: 5, marginTop: 3 },
  dot: { width: 5, height: 5, borderRadius: 3, backgroundColor: ds.ink },
});
