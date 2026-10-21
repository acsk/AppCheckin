import { Feather } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, Text, View } from "react-native";
import Svg, { Circle, Path } from "react-native-svg";
import { ds, radius, space } from "./tokens";

const NAV_BG = "#171a21";
const NAV_MUTED = "#a3aab6";

type SparkProps = {
  values: number[];
  /** true quando menor é melhor (tempo): o gráfico sobe quando a marca melhora. */
  lowerIsBetter?: boolean;
  width?: number;
  height?: number;
};

// Evolução das marcas: melhorar sempre sobe.
export function TimeSpark({ values, lowerIsBetter = true, width = 112, height = 56 }: SparkProps) {
  if (values.length < 2) return null;
  const mn = Math.min(...values);
  const mx = Math.max(...values);
  const r = mx - mn || 1;
  const pts = values.map((x, i) => {
    const t = (x - mn) / r;
    return [Math.round((i * (width - 8)) / (values.length - 1)) + 4, Math.round(4 + (lowerIsBetter ? t : 1 - t) * (height - 8))];
  });
  const d = pts.map((q, i) => `${i ? "L" : "M"}${q[0]} ${q[1]}`).join("");
  const last = pts[pts.length - 1];
  return (
    <Svg width={width} height={height} viewBox={`0 0 ${width} ${height}`}>
      <Path d={`${d}L${last[0]} ${height}L4 ${height}Z`} fill={ds.brand} opacity={0.14} />
      <Path d={d} fill="none" stroke={ds.brand} strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round" />
      <Circle cx={last[0]} cy={last[1]} r={4} fill={ds.brand} stroke={NAV_BG} strokeWidth={2.5} />
    </Svg>
  );
}

type Props = {
  event: string;
  time: string;
  date?: string | null;
  tag?: string;
  delta?: string | null;
  note?: string | null;
  history?: number[];
  lowerIsBetter?: boolean;
};

// Recorde pessoal mais recente: card grafite, marca como herói, quanto melhorou e a evolução.
export default function RecordHero({ event, time, date, tag = "Novo recorde", delta, note, history = [], lowerIsBetter }: Props) {
  return (
    <View style={styles.card} accessible accessibilityLabel={`${tag}: ${event}, ${time}${delta ? `, ${delta}` : ""}`}>
      <View style={styles.top}>
        <View style={styles.tag}>
          <Feather name="award" size={13} color="#171a21" />
          <Text style={styles.tagText}>{tag}</Text>
        </View>
        {date ? <Text style={styles.date}>{date}</Text> : null}
      </View>
      <View style={styles.main}>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={styles.event} numberOfLines={1}>
            {event}
          </Text>
          <Text style={styles.time} numberOfLines={1} adjustsFontSizeToFit>
            {time}
          </Text>
          <View style={styles.meta}>
            {delta ? (
              <View style={styles.delta}>
                <Feather name={lowerIsBetter === false ? "arrow-up" : "arrow-down"} size={12} color="#4ade80" />
                <Text style={styles.deltaText}>{delta}</Text>
              </View>
            ) : null}
            {note ? <Text style={styles.note}>{note}</Text> : null}
          </View>
        </View>
        <TimeSpark values={history} lowerIsBetter={lowerIsBetter} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    gap: space[3],
    padding: space[4],
    borderRadius: 20,
    backgroundColor: NAV_BG,
    borderWidth: 1,
    borderColor: "rgba(255, 255, 255, 0.06)",
    shadowColor: "#171a21",
    shadowOffset: { width: 0, height: 14 },
    shadowOpacity: 0.3,
    shadowRadius: 16,
    elevation: 4,
  },
  top: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  tag: { flexDirection: "row", alignItems: "center", gap: 4, height: 22, paddingHorizontal: 10, borderRadius: radius.full, backgroundColor: ds.brandYellow },
  tagText: { fontSize: 11, fontWeight: "900", letterSpacing: 0.66, textTransform: "uppercase", color: "#171a21" },
  date: { fontSize: 12, fontWeight: "700", color: NAV_MUTED, fontVariant: ["tabular-nums"] },
  main: { flexDirection: "row", alignItems: "flex-end", justifyContent: "space-between", gap: space[3] },
  event: { fontSize: 15, lineHeight: 20, fontWeight: "800", color: NAV_MUTED },
  time: { fontSize: 44, lineHeight: 46, fontWeight: "900", letterSpacing: -1.3, color: "#ffffff", fontVariant: ["tabular-nums"] },
  meta: { flexDirection: "row", alignItems: "center", flexWrap: "wrap", gap: 8, marginTop: 6 },
  delta: { flexDirection: "row", alignItems: "center", gap: 2, height: 24, paddingHorizontal: 8, borderRadius: radius.full, backgroundColor: "rgba(74, 222, 128, 0.15)" },
  deltaText: { fontSize: 12, fontWeight: "900", color: "#4ade80", fontVariant: ["tabular-nums"] },
  note: { fontSize: 13, fontWeight: "700", color: NAV_MUTED },
});
