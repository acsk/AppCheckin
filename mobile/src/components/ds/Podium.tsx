import React from "react";
import { StyleSheet, Text, View } from "react-native";
import Avatar from "./Avatar";
import { ds, space } from "./tokens";

export type PodiumItem = { name: string; value: string; self?: boolean; avatarUri?: string | null };

const MEDAL = [ds.medalGold, ds.medalSilver, ds.medalBronze];
const STEP = [64, 44, 32];

// Os três primeiros de uma prova, na ordem 2 · 1 · 3.
export default function Podium({ items }: { items: PodiumItem[] }) {
  return (
    <View style={styles.row}>
      {[1, 0, 2].map((i) => {
        const x = items[i];
        if (!x) return <View key={i} style={styles.pod} />;
        const medal = MEDAL[i];
        return (
          <View key={i} style={styles.pod} accessible accessibilityLabel={`${i + 1}º lugar, ${x.name}, ${x.value}`}>
            <View style={[styles.av, { borderColor: medal }]}>
              <Avatar name={x.name} uri={x.avatarUri} size={i === 0 ? 64 : 52} />
              <View style={[styles.num, { backgroundColor: medal }]}>
                <Text style={styles.numText}>{i + 1}</Text>
              </View>
            </View>
            <Text style={[styles.name, x.self && { color: ds.brandInk }]} numberOfLines={1}>
              {x.name.split(" ")[0]}
            </Text>
            <Text style={[styles.value, i === 0 && { fontSize: 20 }]}>{x.value}</Text>
            <View style={[styles.step, { height: STEP[i], backgroundColor: medal }]}>
              <Text style={[styles.stepText, i === 0 && { fontSize: 26, lineHeight: 30 }]}>{i + 1}</Text>
            </View>
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: "row", alignItems: "flex-end", gap: space[2], paddingTop: space[2] },
  pod: { flex: 1, minWidth: 0, alignItems: "center", gap: 2 },
  av: { borderWidth: 3, borderRadius: 999, marginBottom: 4 },
  num: {
    position: "absolute",
    bottom: -9,
    alignSelf: "center",
    width: 22,
    height: 22,
    borderRadius: 11,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 2,
    borderColor: ds.ground,
  },
  numText: { fontSize: 12, fontWeight: "900", color: "#171a21" },
  name: { marginTop: 6, maxWidth: "100%", fontSize: 13, lineHeight: 16, fontWeight: "800", textTransform: "uppercase", color: ds.ink },
  value: { fontSize: 17, lineHeight: 22, fontWeight: "900", color: ds.ink, fontVariant: ["tabular-nums"] },
  step: { width: "100%", marginTop: 8, alignItems: "center", paddingTop: 6, borderTopLeftRadius: 12, borderTopRightRadius: 12, borderBottomLeftRadius: 4, borderBottomRightRadius: 4 },
  stepText: { fontSize: 20, lineHeight: 24, fontWeight: "900", color: "#171a21" },
});
