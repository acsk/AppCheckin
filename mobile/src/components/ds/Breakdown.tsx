import React from "react";
import { StyleSheet, Text, View } from "react-native";
import { ds, space } from "./tokens";

export type BreakdownRow = { label: string; hint?: string | null; value: string; tone?: "credit" };

type Props = { rows: BreakdownRow[]; total?: BreakdownRow | null };

// A conta, linha a linha, até o total (design system · Breakdown). tone "credit" = desconto em verde.
export default function Breakdown({ rows, total }: Props) {
  const linha = (r: BreakdownRow, isTotal = false, key?: React.Key) => (
    <View key={key} style={[styles.row, isTotal && styles.rowTotal]}>
      <View style={styles.dt}>
        <Text style={[styles.label, isTotal && styles.labelTotal]}>{r.label}</Text>
        {r.hint ? <Text style={styles.hint}>{r.hint}</Text> : null}
      </View>
      <Text style={[styles.value, r.tone === "credit" && { color: ds.success }, isTotal && styles.valueTotal]}>
        {r.value}
      </Text>
    </View>
  );

  return (
    <View>
      {rows.map((r, i) => linha(r, false, i))}
      {total ? linha(total, true) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: "row", alignItems: "flex-start", justifyContent: "space-between", gap: space[3], paddingVertical: 6 },
  rowTotal: {
    marginTop: 6,
    paddingTop: space[3],
    borderTopWidth: 1,
    borderStyle: "dashed",
    borderTopColor: ds.lineStrong,
    alignItems: "center",
  },
  dt: { flex: 1, minWidth: 0 },
  label: { fontSize: 14, lineHeight: 20, fontWeight: "600", color: ds.inkMuted },
  labelTotal: { color: ds.ink, fontWeight: "800" },
  hint: { fontSize: 12, lineHeight: 16, fontWeight: "600", color: ds.inkMuted, opacity: 0.85 },
  value: { fontSize: 15, lineHeight: 20, fontWeight: "800", color: ds.ink, fontVariant: ["tabular-nums"] },
  valueTotal: { fontSize: 20, lineHeight: 24, fontWeight: "900" },
});
