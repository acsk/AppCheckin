import { Feather, MaterialCommunityIcons } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { ds, space } from "./tokens";

const NAV_BG = "#171a21";
const ON_NAV_MUTED = "#a3aab6";

type Props = {
  name: string;
  modality?: string | null;
  detail?: string | null;
  /** "3x", "1" */
  freq: string;
  /** "semana", "dia" */
  freqUnit: string;
  /** "R$ 150" */
  price: string;
  /** padrão "/mês"; "/aula" para avulso */
  unit?: string;
  /** Mostra "A partir de". */
  from?: boolean;
  /** "≈ R$ 12,50" */
  perClass?: string | null;
  features?: string[];
  tag?: string | null;
  current?: boolean;
  actionLabel?: string;
  onSelect?: () => void;
};

// Um plano na lista: a frequência é o herói ("3x / semana"), com preço por mês e
// quanto sai cada aula. O plano atual é um card grafite no topo.
export default function PlanCard({
  name,
  modality,
  detail,
  freq,
  freqUnit,
  price,
  unit = "/mês",
  from,
  perClass,
  features,
  tag,
  current,
  actionLabel,
  onSelect,
}: Props) {
  const cta = actionLabel || (current ? "Ver meu plano" : "Escolher");
  const showTag = tag || current;
  const muted = current ? ON_NAV_MUTED : ds.inkMuted;
  const ink = current ? "#ffffff" : ds.ink;

  return (
    <View style={showTag ? { marginTop: 10 } : null}>
      <TouchableOpacity
        onPress={onSelect}
        activeOpacity={0.88}
        accessibilityRole="button"
        accessibilityLabel={`${name}, ${from ? "a partir de " : ""}${price}${unit}. ${cta}`}
        style={[styles.card, current && styles.cardCurrent]}
      >
        <View style={styles.main}>
          <View style={[styles.freq, current && styles.freqCurrent]} accessibilityElementsHidden>
            <Text style={[styles.freqN, { color: current ? ds.onBrand : ds.brandInk }]}>{freq}</Text>
            <Text style={[styles.freqU, { color: current ? ds.onBrand : ds.brandInk }]}>{freqUnit}</Text>
          </View>
          <View style={styles.info}>
            <Text style={[styles.name, { color: ink }]} numberOfLines={2}>
              {name}
            </Text>
            {modality || detail ? (
              <View style={styles.mod}>
                <MaterialCommunityIcons name="waves" size={14} color={current ? ds.brandYellow : ds.info} />
                <Text style={[styles.modText, { color: current ? ds.brandYellow : ds.info }]} numberOfLines={1}>
                  {[modality, detail].filter(Boolean).join(" · ")}
                </Text>
              </View>
            ) : null}
            {features && features.length ? (
              <View style={styles.feats}>
                {features.map((f) => (
                  <View key={f} style={styles.feat}>
                    <Feather name="check" size={13} color={current ? ds.brand : ds.success} />
                    <Text style={[styles.featText, { color: muted }]}>{f}</Text>
                  </View>
                ))}
              </View>
            ) : null}
          </View>
        </View>

        <View style={[styles.foot, current && { borderTopColor: "rgba(255,255,255,0.15)" }]}>
          <View style={{ flexShrink: 1, minWidth: 0 }}>
            {from ? <Text style={[styles.fromText, { color: muted }]}>A PARTIR DE</Text> : null}
            <View style={styles.amount}>
              <Text style={[styles.price, { color: ink }]}>{price}</Text>
              <Text style={[styles.unit, { color: muted }]}>{unit}</Text>
            </View>
            {perClass ? (
              <Text style={[styles.per, { color: muted }]}>
                <Text style={{ color: current ? ds.brand : ds.brandInk, fontWeight: "900" }}>{perClass}</Text> por aula
              </Text>
            ) : null}
          </View>
          <View style={[styles.cta, current && styles.ctaCurrent]}>
            <Text style={[styles.ctaText, current && { color: NAV_BG }]}>{cta}</Text>
            <Feather name="arrow-right" size={16} color={current ? NAV_BG : ds.onBrand} />
          </View>
        </View>
      </TouchableOpacity>

      {showTag ? (
        <View style={[styles.tag, current && styles.tagCurrent]} pointerEvents="none">
          <Feather name={current ? "check" : "award"} size={13} color={ds.onBrand} />
          <Text style={styles.tagText}>{current ? tag || "Seu plano" : tag}</Text>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 18,
    backgroundColor: ds.surface,
    borderWidth: 1,
    borderColor: ds.line,
    overflow: "hidden",
  },
  cardCurrent: {
    backgroundColor: NAV_BG,
    borderColor: "rgba(255,255,255,0.06)",
    shadowColor: NAV_BG,
    shadowOffset: { width: 0, height: 14 },
    shadowOpacity: 0.35,
    shadowRadius: 18,
    elevation: 8,
  },
  main: { flexDirection: "row", alignItems: "center", gap: space[3], paddingTop: 16, paddingHorizontal: space[4], paddingBottom: 12 },
  freq: {
    width: 58,
    height: 58,
    borderRadius: 16,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: ds.brandSoft,
    borderWidth: 1,
    borderColor: "rgba(255,122,0,0.2)",
  },
  freqCurrent: { backgroundColor: ds.brand, borderColor: ds.brand },
  freqN: { fontSize: 26, lineHeight: 26, fontWeight: "900", letterSpacing: -1, fontVariant: ["tabular-nums"] },
  freqU: { fontSize: 10, lineHeight: 12, fontWeight: "800", letterSpacing: 0.4, textTransform: "uppercase", marginTop: 2 },
  info: { flex: 1, minWidth: 0, gap: 3 },
  name: { fontSize: 18, lineHeight: 22, fontWeight: "900", letterSpacing: -0.2 },
  mod: { flexDirection: "row", alignItems: "center", gap: 4 },
  modText: { fontSize: 13, lineHeight: 18, fontWeight: "700", flexShrink: 1 },
  feats: { flexDirection: "row", flexWrap: "wrap", columnGap: 10, rowGap: 2 },
  feat: { flexDirection: "row", alignItems: "center", gap: 3 },
  featText: { fontSize: 12, lineHeight: 16, fontWeight: "700" },
  foot: {
    flexDirection: "row",
    alignItems: "flex-end",
    justifyContent: "space-between",
    gap: space[3],
    paddingTop: 10,
    paddingHorizontal: space[4],
    paddingBottom: 14,
    borderTopWidth: 1,
    borderTopColor: ds.line,
    borderStyle: "dashed",
  },
  fromText: { fontSize: 11, lineHeight: 14, fontWeight: "800", letterSpacing: 0.66 },
  amount: { flexDirection: "row", alignItems: "baseline", gap: 2 },
  price: { fontSize: 26, lineHeight: 30, fontWeight: "900", letterSpacing: -0.8, fontVariant: ["tabular-nums"] },
  unit: { fontSize: 13, fontWeight: "700" },
  per: { fontSize: 12, lineHeight: 16, fontWeight: "600", fontVariant: ["tabular-nums"] },
  cta: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    height: 40,
    paddingHorizontal: space[4],
    borderRadius: 999,
    backgroundColor: ds.brand,
    shadowColor: ds.brandRed,
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.3,
    shadowRadius: 10,
    elevation: 3,
  },
  ctaCurrent: { backgroundColor: "#ffffff", shadowOpacity: 0, elevation: 0 },
  ctaText: { fontSize: 14, fontWeight: "900", color: ds.onBrand },
  tag: {
    position: "absolute",
    top: -11,
    left: space[4],
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    height: 22,
    paddingHorizontal: 10,
    borderRadius: 999,
    backgroundColor: ds.brand,
  },
  tagCurrent: { backgroundColor: ds.brandYellow },
  tagText: { fontSize: 11, lineHeight: 14, fontWeight: "900", letterSpacing: 0.66, textTransform: "uppercase", color: ds.onBrand },
});
