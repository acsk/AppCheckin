import { Feather } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { ds, radius, space } from "./tokens";

type Props = {
  label: string;
  detail?: string | null;
  /** Preço por mês (o número grande, seguido de "/mês"). */
  monthly?: string | null;
  /** Preço mensal sem desconto, riscado. */
  fullMonthly?: string | null;
  /** Usado como número grande quando não há `monthly`. */
  price?: string | null;
  total?: string | null;
  saving?: string | null;
  savingAmount?: string | null;
  /** Selo em uma única opção do grupo. */
  tag?: string | null;
  selected?: boolean;
  onSelect?: () => void;
  children?: React.ReactNode;
};

// Opção de ciclo como rádio grande: preço por mês é o herói, com total,
// preço cheio riscado e economia em reais.
export default function PlanOption({
  label,
  detail,
  monthly,
  fullMonthly,
  price,
  total,
  saving,
  savingAmount,
  tag,
  selected,
  onSelect,
  children,
}: Props) {
  const hero = monthly || price;
  const showTotal = monthly ? total || price : null;
  const save = savingAmount;

  return (
    <View style={tag ? { marginTop: 10 } : null}>
      <View style={[styles.card, selected && styles.cardSelected]}>
        <TouchableOpacity
          onPress={onSelect}
          activeOpacity={0.85}
          accessibilityRole="radio"
          accessibilityState={{ checked: !!selected }}
          accessibilityLabel={`${label}${hero ? `, ${hero}${monthly ? " por mês" : ""}` : ""}${showTotal ? `, total ${showTotal}` : ""}`}
        >
          <View style={[styles.main, tag ? { paddingTop: 20 } : null]}>
            <View style={[styles.radio, selected && styles.radioSelected]}>
              {selected ? <Feather name="check" size={14} color={ds.onBrand} /> : null}
            </View>
            <View style={styles.text}>
              <Text style={styles.label} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.85}>
                {label}
              </Text>
              {detail ? <Text style={styles.sub}>{detail}</Text> : null}
            </View>
            {saving ? <View style={styles.savingBadge}><Text style={styles.savingBadgeText}>{saving}</Text></View> : null}
            <View style={styles.price}>
              {fullMonthly ? <Text style={styles.was}>{fullMonthly}</Text> : null}
              <View style={styles.heroRow}>
                <Text style={[styles.hero, selected && { color: ds.brandInk }]}>{hero}</Text>
                {monthly ? <Text style={styles.unit}>/mês</Text> : null}
              </View>
            </View>
          </View>

        </TouchableOpacity>
        {selected && (showTotal || children) ? <View style={styles.more}>
          {showTotal || save ? (
            <View style={styles.foot}>
              {showTotal ? (
                <Text style={styles.footText}>
                  Total <Text style={styles.footBold}>{showTotal}</Text>
                </Text>
              ) : (
                <View />
              )}
              {save ? (
                <View style={styles.save}>
                  <Feather name="check" size={13} color={ds.success} />
                  <Text style={styles.saveText}>{save}</Text>
                </View>
              ) : null}
            </View>
          ) : null}
          {children}
        </View> : null}
      </View>

      {tag ? (
        <View style={[styles.tag, selected && { backgroundColor: ds.brand }]} pointerEvents="none">
          <Feather name="award" size={13} color={selected ? ds.onBrand : ds.onInverse} />
          <Text style={[styles.tagText, { color: selected ? ds.onBrand : ds.onInverse }]}>{tag}</Text>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: radius.lg,
    backgroundColor: ds.surface,
    borderWidth: 1.5,
    borderColor: ds.line,
    overflow: "hidden",
  },
  cardSelected: { backgroundColor: ds.brandSoft, borderWidth: 2, borderColor: ds.brand },
  main: { flexDirection: "row", alignItems: "center", gap: 3, paddingVertical: 14, paddingHorizontal: 10 },
  radio: {
    width: 22,
    height: 22,
    borderRadius: 11,
    borderWidth: 2,
    borderColor: ds.lineStrong,
    alignItems: "center",
    justifyContent: "center",
  },
  radioSelected: { backgroundColor: ds.brand, borderColor: ds.brand },
  text: { flex: 1, minWidth: 0 },
  label: { fontSize: 15, lineHeight: 20, fontWeight: "800", letterSpacing: -0.2, color: ds.ink },
  sub: { marginTop: 1, fontSize: 12, lineHeight: 15, fontWeight: "600", color: ds.inkMuted },
  price: { alignItems: "flex-end" },
  was: {
    fontSize: 11,
    lineHeight: 14,
    fontWeight: "700",
    color: ds.inkMuted,
    textDecorationLine: "line-through",
    fontVariant: ["tabular-nums"],
  },
  heroRow: { flexDirection: "row", alignItems: "baseline", gap: 2 },
  hero: {
    fontSize: 19,
    lineHeight: 22,
    fontWeight: "900",
    letterSpacing: -0.7,
    color: ds.ink,
    fontVariant: ["tabular-nums"],
  },
  unit: { fontSize: 12, lineHeight: 16, fontWeight: "700", color: ds.inkMuted },
  savingBadge: { paddingHorizontal: 7, paddingVertical: 2, borderRadius: radius.full, backgroundColor: ds.successSoft },
  savingBadgeText: { fontSize: 11, lineHeight: 15, fontWeight: "800", color: ds.success },
  more: { marginHorizontal: 14, paddingTop: 10, paddingBottom: 14, borderTopWidth: 1, borderTopColor: `${ds.brand}40`, gap: space[2] },
  foot: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: space[2],
  },
  footText: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted, fontVariant: ["tabular-nums"] },
  footBold: { color: ds.ink, fontWeight: "800" },
  save: { flexDirection: "row", alignItems: "center", gap: 4, flexShrink: 1 },
  saveText: { fontSize: 13, lineHeight: 18, fontWeight: "800", color: ds.success },
  tag: {
    position: "absolute",
    top: -11,
    left: space[4],
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    height: 22,
    paddingHorizontal: 10,
    borderRadius: radius.full,
    backgroundColor: ds.inverse,
  },
  tagText: { fontSize: 11, lineHeight: 14, fontWeight: "800", letterSpacing: 0.66, textTransform: "uppercase" },
});
