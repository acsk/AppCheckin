import { Feather } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, Text, View } from "react-native";
import { ds, space } from "./tokens";

type Plano = { name: string; cycle?: string | null; price?: string | null };

type Props = { from: Plano; to: Plano; fromLabel?: string; toLabel?: string };

// De → para numa troca de plano (design system · PlanChange). O atual fica apagado; o novo em destaque.
export default function PlanChange({ from, to, fromLabel = "Atual", toLabel = "Novo" }: Props) {
  const row = (x: Plano, label: string, novo: boolean) => (
    <View style={[styles.row, novo ? styles.rowTo : styles.rowFrom]}>
      <Text style={[styles.label, novo && { color: ds.brandInk }]}>{label}</Text>
      <View style={styles.main}>
        <Text style={[styles.name, !novo && { color: ds.inkMuted }]} numberOfLines={1}>
          {x.name}
        </Text>
        {x.cycle ? (
          <Text style={styles.cycle} numberOfLines={1}>
            {x.cycle}
          </Text>
        ) : null}
      </View>
      {x.price ? <Text style={[styles.price, !novo && { color: ds.inkMuted }]}>{x.price}</Text> : null}
    </View>
  );

  return (
    <View style={styles.box} accessibilityLabel={`Troca de ${from.name} para ${to.name}`}>
      {row(from, fromLabel, false)}
      {row(to, toLabel, true)}
      <View style={styles.arrow} pointerEvents="none">
        <Feather name="arrow-down" size={16} color={ds.onInverse} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  box: { position: "relative", borderWidth: 1, borderColor: ds.line, borderRadius: 16, overflow: "hidden" },
  row: { flexDirection: "row", alignItems: "center", gap: space[3], paddingVertical: space[3], paddingHorizontal: space[4] },
  rowFrom: { backgroundColor: ds.surfaceSunken },
  rowTo: { backgroundColor: ds.brandSoft, borderTopWidth: 1, borderTopColor: ds.line },
  label: {
    width: 44,
    fontSize: 10,
    lineHeight: 12,
    fontWeight: "800",
    letterSpacing: 0.8,
    textTransform: "uppercase",
    color: ds.inkMuted,
  },
  main: { flex: 1, minWidth: 0 },
  name: { fontSize: 15, lineHeight: 20, fontWeight: "800", color: ds.ink },
  cycle: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted },
  price: { fontSize: 16, lineHeight: 20, fontWeight: "900", color: ds.ink, fontVariant: ["tabular-nums"] },
  arrow: {
    position: "absolute",
    left: space[4] + 8,
    top: "50%",
    marginTop: -14,
    width: 28,
    height: 28,
    borderRadius: 14,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: ds.inverse,
    borderWidth: 3,
    borderColor: ds.surface,
  },
});
