import { Feather } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, Text, View } from "react-native";
import { ds, space } from "./tokens";

type Props = {
  steps: string[];
  current: number;
  /** brand: dentro do AppHeader `bottom` (sobre o laranja). */
  tone?: "brand" | "surface";
};

// Etapas numeradas com uma barra de progresso cada (ex.: Plano → Ciclo e pagamento).
export default function Stepper({ steps, current, tone = "surface" }: Props) {
  const brand = tone === "brand";
  return (
    <View
      style={[styles.row, brand && styles.rowBrand]}
      accessibilityRole="progressbar"
      accessibilityLabel={`Etapa ${current + 1} de ${steps.length}`}
    >
      {steps.map((label, i) => {
        const done = i < current;
        const on = i <= current;
        return (
          <View key={label} style={styles.step}>
            <View
              style={[
                styles.bar,
                { backgroundColor: on ? (brand ? ds.onBrand : ds.ink) : brand ? "rgba(255,255,255,0.35)" : ds.line },
              ]}
            />
            <View style={styles.labelRow}>
              <View
                style={[
                  styles.num,
                  { backgroundColor: on ? (brand ? ds.onBrand : ds.ink) : brand ? "rgba(255,255,255,0.35)" : ds.line },
                ]}
              >
                {done ? (
                  <Feather name="check" size={12} color={ds.onInverse} />
                ) : (
                  <Text style={[styles.numText, { color: on ? ds.onInverse : brand ? ds.onBrand : ds.ink }]}>{i + 1}</Text>
                )}
              </View>
              <Text
                style={[
                  styles.label,
                  { color: on ? (brand ? ds.onBrand : ds.ink) : brand ? "rgba(23,26,33,0.7)" : ds.inkMuted },
                ]}
                numberOfLines={1}
              >
                {label}
              </Text>
            </View>
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: "row", gap: space[2] },
  rowBrand: { paddingTop: 4, paddingHorizontal: space[4], paddingBottom: space[4] },
  step: { flex: 1, gap: 6 },
  bar: { height: 5, borderRadius: 999 },
  labelRow: { flexDirection: "row", alignItems: "center", gap: 6 },
  num: { width: 18, height: 18, borderRadius: 9, alignItems: "center", justifyContent: "center" },
  numText: { fontSize: 11, fontWeight: "900" },
  label: { flexShrink: 1, fontSize: 12, lineHeight: 16, fontWeight: "800", letterSpacing: 0.48, textTransform: "uppercase" },
});
