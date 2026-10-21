import { Feather } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import Badge from "./Badge";
import { ds, radius, space } from "./tokens";

type Props = {
  /** Distância da prova ("50"); sem ela o quadro mostra um troféu e o nome vai no texto de apoio. */
  distance?: string | null;
  unit?: string;
  time: string;
  /** Linha de apoio: data e contexto, ou o nome da prova quando não há distância. */
  detail?: string | null;
  delta?: string | null;
  /** Melhorar = número maior (carga, reps): a seta aponta para cima. */
  deltaUp?: boolean;
  badge?: string | null;
  /** Dono do recorde (aba Escola). */
  holder?: string | null;
  self?: boolean;
  empty?: boolean;
  emptyText?: string;
  onPress?: () => void;
};

// Uma prova: quadro da distância → marca (herói) com data e contexto → melhora ou selo.
export default function RecordRow({ distance, unit = "m", time, detail, delta, deltaUp, badge, holder, self, empty, emptyText, onPress }: Props) {
  const content = (
    <>
      <View style={[styles.dist, empty && styles.distEmpty]}>
        {distance ? (
          <Text style={[styles.distN, empty && styles.muted]}>
            {distance}
            <Text style={styles.distU}>{unit}</Text>
          </Text>
        ) : (
          <Feather name="award" size={20} color={empty ? ds.inkMuted : ds.ink} />
        )}
      </View>
      {holder ? (
        <View style={styles.main}>
          <Text style={[styles.holder, self && { color: ds.brandInk }]} numberOfLines={1}>
            {holder}
          </Text>
          <Text style={styles.small} numberOfLines={1}>
            {[self ? "Você" : null, detail].filter(Boolean).join(" · ")}
          </Text>
        </View>
      ) : (
        <View style={styles.main}>
          <Text style={[styles.time, empty && styles.timeEmpty]} numberOfLines={1}>
            {empty ? "Sem marca" : time}
          </Text>
          <Text style={styles.small} numberOfLines={1}>
            {empty ? emptyText || "Ainda sem marca registrada" : detail}
          </Text>
        </View>
      )}
      {holder ? (
        <Text style={styles.timeRight}>{time}</Text>
      ) : badge ? (
        <Badge tone="brand" icon="award">
          {badge}
        </Badge>
      ) : delta ? (
        <View style={styles.delta}>
          <Feather name={deltaUp ? "arrow-up" : "arrow-down"} size={12} color={ds.success} />
          <Text style={styles.deltaText}>{delta}</Text>
        </View>
      ) : null}
      {onPress ? <Feather name="chevron-right" size={18} color={ds.inkMuted} /> : null}
    </>
  );

  const style = [styles.row, self && { backgroundColor: ds.brandSoft }];
  return onPress ? (
    <TouchableOpacity style={style} onPress={onPress} activeOpacity={0.7} accessibilityRole="button">
      {content}
    </TouchableOpacity>
  ) : (
    <View style={style}>{content}</View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: "row", alignItems: "center", gap: space[3], minHeight: 68, paddingVertical: space[3], paddingHorizontal: space[4] },
  dist: {
    width: 52,
    height: 44,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: ds.surfaceSunken,
    borderWidth: 1,
    borderColor: ds.line,
  },
  distEmpty: { backgroundColor: "transparent", borderWidth: 1.5 },
  distN: { fontSize: 18, lineHeight: 22, fontWeight: "900", letterSpacing: -0.4, color: ds.ink, fontVariant: ["tabular-nums"] },
  distU: { fontSize: 12, fontWeight: "800", color: ds.inkMuted },
  muted: { color: ds.inkMuted },
  main: { flex: 1, minWidth: 0, gap: 1 },
  time: { fontSize: 22, lineHeight: 26, fontWeight: "900", letterSpacing: -0.4, color: ds.ink, fontVariant: ["tabular-nums"] },
  timeEmpty: { fontSize: 15, lineHeight: 20, fontWeight: "800", color: ds.inkMuted },
  timeRight: { fontSize: 20, lineHeight: 26, fontWeight: "900", letterSpacing: -0.4, color: ds.ink, fontVariant: ["tabular-nums"] },
  small: { fontSize: 12, lineHeight: 16, fontWeight: "600", color: ds.inkMuted },
  holder: { fontSize: 14, lineHeight: 18, fontWeight: "800", textTransform: "uppercase", color: ds.ink },
  delta: { flexDirection: "row", alignItems: "center", gap: 2, height: 24, paddingHorizontal: 8, borderRadius: radius.full, backgroundColor: ds.successSoft },
  deltaText: { fontSize: 12, fontWeight: "900", color: ds.success, fontVariant: ["tabular-nums"] },
});
