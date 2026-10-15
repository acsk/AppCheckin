import { Feather } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { ds, radius, space } from "./tokens";

type Props = {
  present: number;
  absent: number;
  pending: number;
  capacity?: number | null;
  onAllPresent?: () => void;
};

// Resumo da chamada: presentes, faltas e pendentes, com barra empilhada contra as vagas.
export default function RosterSummary({ present, absent, pending, capacity, onAllPresent }: Props) {
  const total = present + absent + pending;
  const cap = Math.max(capacity || total || 1, total, 1);
  const pct = (n: number) => `${(100 * n) / cap}%` as const;
  const vagas = Math.max(cap - total, 0);

  return (
    <View style={styles.card}>
      <View style={styles.nums}>
        {[
          { n: present, label: "Presentes", icon: "check" as const, color: ds.success },
          { n: absent, label: "Faltas", icon: "x" as const, color: ds.danger },
          { n: pending, label: "Pendentes", icon: "clock" as const, color: ds.inkMuted },
        ].map((x, i) => (
          <View key={x.label} style={[styles.num, i > 0 && styles.numDivider]}>
            <Text style={styles.numN}>{x.n}</Text>
            <View style={styles.numLabel}>
              <Feather name={x.icon} size={13} color={x.color} />
              <Text style={[styles.numLabelText, { color: x.color }]}>{x.label}</Text>
            </View>
          </View>
        ))}
      </View>

      <View
        style={styles.bar}
        accessibilityRole="image"
        accessibilityLabel={`${present} presentes, ${absent} faltas, ${pending} pendentes de ${cap} vagas`}
      >
        {present > 0 ? <View style={[styles.seg, { width: pct(present), backgroundColor: ds.success }]} /> : null}
        {absent > 0 ? <View style={[styles.seg, { width: pct(absent), backgroundColor: ds.danger }]} /> : null}
        {pending > 0 ? <View style={[styles.seg, { width: pct(pending), backgroundColor: ds.lineStrong }]} /> : null}
      </View>

      <View style={styles.foot}>
        <Text style={styles.footText}>
          <Text style={styles.footBold}>
            {total}/{cap}
          </Text>{" "}
          alunos na turma · {vagas} {vagas === 1 ? "vaga" : "vagas"}
        </Text>
        {onAllPresent && pending > 0 ? (
          <TouchableOpacity onPress={onAllPresent} accessibilityRole="button" style={styles.linkHit}>
            <Text style={styles.link}>Marcar todos presentes</Text>
          </TouchableOpacity>
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: ds.surface,
    borderWidth: 1,
    borderColor: ds.line,
    borderRadius: radius.lg,
    padding: space[4],
    gap: space[3],
  },
  nums: { flexDirection: "row" },
  num: { flex: 1 },
  numDivider: { borderLeftWidth: 1, borderLeftColor: ds.line, paddingLeft: space[3] },
  numN: { fontSize: 30, lineHeight: 32, fontWeight: "900", letterSpacing: -0.9, color: ds.ink, fontVariant: ["tabular-nums"] },
  numLabel: { flexDirection: "row", alignItems: "center", gap: 4 },
  numLabelText: { fontSize: 12, lineHeight: 16, fontWeight: "800" },
  bar: {
    flexDirection: "row",
    gap: 3,
    height: 10,
    borderRadius: 999,
    backgroundColor: ds.surfaceSunken,
    borderWidth: 1,
    borderColor: ds.line,
    overflow: "hidden",
  },
  seg: { height: "100%", borderRadius: 999 },
  foot: { flexDirection: "row", flexWrap: "wrap", alignItems: "center", justifyContent: "space-between", columnGap: space[2], rowGap: 4 },
  footText: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted },
  footBold: { color: ds.ink, fontWeight: "900" },
  linkHit: { minHeight: 32, justifyContent: "center" },
  link: { fontSize: 14, fontWeight: "800", color: ds.brandInk },
});
