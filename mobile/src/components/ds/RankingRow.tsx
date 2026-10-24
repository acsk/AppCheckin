import React from "react";
import { StyleSheet, Text, View } from "react-native";
import Avatar from "./Avatar";
import { ds, radius, space } from "./tokens";

type Props = {
  position: number | null;
  name: string;
  value: string;
  unit?: string;
  /** Distância para o primeiro ("+1,18"). */
  gap?: string | null;
  self?: boolean;
  avatarUri?: string | null;
};

const MEDAL: Record<number, string> = { 1: ds.medalGold, 2: ds.medalSilver, 3: ds.medalBronze };

// Uma linha do ranking: posição (medalha no pódio), aluno, marca e a distância para o líder.
export default function RankingRow({ position, name, value, unit, gap, self, avatarUri }: Props) {
  const medal = position ? MEDAL[position] : undefined;
  return (
    <View style={[styles.row, self && { backgroundColor: ds.brandSoft }]} accessible accessibilityLabel={`${position ? `${position}º` : "Sem posição"}, ${name}, ${value} ${unit ?? ""}`}>
      <View style={[styles.pos, medal ? { backgroundColor: medal } : null]}>
        <Text style={[styles.posText, medal ? { color: "#171a21" } : null]}>{position ?? "–"}</Text>
      </View>
      <Avatar name={name} uri={avatarUri} size={36} />
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text style={[styles.name, self && { color: ds.brandInk }]} numberOfLines={1}>
          {name}
        </Text>
        {self ? <Text style={styles.you}>Você</Text> : null}
      </View>
      <View style={styles.right}>
        <Text style={styles.value}>{value}</Text>
        {unit ? <Text style={styles.gap}>{unit}</Text> : null}
        {gap ? <Text style={styles.gap}>{gap}</Text> : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: "row", alignItems: "center", gap: space[3], paddingVertical: space[3], paddingHorizontal: space[4] },
  pos: { width: 36, height: 36, borderRadius: radius.sm, alignItems: "center", justifyContent: "center", backgroundColor: ds.surfaceSunken },
  posText: { fontSize: 17, fontWeight: "900", color: ds.ink, fontVariant: ["tabular-nums"] },
  name: { fontSize: 15, lineHeight: 20, fontWeight: "800", textTransform: "uppercase", color: ds.ink },
  you: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted },
  right: { alignItems: "flex-end" },
  value: { fontSize: 20, lineHeight: 22, fontWeight: "900", color: ds.ink, fontVariant: ["tabular-nums"] },
  gap: { fontSize: 11, lineHeight: 14, fontWeight: "700", color: ds.inkMuted, fontVariant: ["tabular-nums"] },
});
