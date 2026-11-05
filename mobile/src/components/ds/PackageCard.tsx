import { Feather } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { ds, space } from "./tokens";

type Props = {
  name: string;
  /** "2x por semana · Natação · Mensal" */
  detail?: string | null;
  /** "R$ 117,00" */
  perPerson: string;
  /** "2 pessoas" */
  people?: string | null;
  /** "R$ 234,00/mês" */
  total?: string | null;
  /** Quem pode entrar. */
  who?: string | null;
  badge?: string | null;
  selected?: boolean;
  onSelect?: () => void;
};

// Um pacote para o grupo: preço por pessoa em destaque, total e quem pode entrar.
export default function PackageCard({ name, detail, perPerson, people, total, who, badge, selected, onSelect }: Props) {
  return (
    <TouchableOpacity
      activeOpacity={0.85}
      onPress={onSelect}
      accessibilityRole="radio"
      accessibilityState={{ checked: !!selected }}
      accessibilityLabel={[name, detail, `${perPerson} por pessoa por mês`, people, total].filter(Boolean).join(", ")}
      style={[styles.card, selected && styles.cardOn]}
    >
      <View style={styles.top}>
        <View style={styles.icon}>
          <Feather name="users" size={22} color={ds.info} />
        </View>
        <View style={styles.name}>
          <Text style={styles.nameText}>{name}</Text>
          {detail ? <Text style={styles.detail}>{detail}</Text> : null}
        </View>
        <View style={[styles.radio, selected && styles.radioOn]}>
          {selected ? <Feather name="check" size={13} color={ds.onBrand} /> : null}
        </View>
      </View>
      {badge ? (
        <View style={styles.badge}>
          <Text style={styles.badgeText}>{badge}</Text>
        </View>
      ) : null}
      <View>
        <Text>
          <Text style={styles.price}>{perPerson}</Text>
          <Text style={styles.priceUnit}> por pessoa/mês</Text>
        </Text>
        <Text style={styles.sub}>{[people, total ? `total ${total}` : null].filter(Boolean).join(" · ")}</Text>
      </View>
      {who ? (
        <View style={styles.who}>
          <Feather name="check" size={14} color={ds.success} style={{ marginTop: 2 }} />
          <Text style={styles.whoText}>{who}</Text>
        </View>
      ) : null}
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  card: {
    gap: space[3],
    padding: space[4],
    borderRadius: 18,
    backgroundColor: ds.surface,
    borderWidth: 1.5,
    borderColor: ds.line,
  },
  cardOn: { backgroundColor: ds.brandSoft, borderWidth: 2, borderColor: ds.brand },
  top: { flexDirection: "row", alignItems: "center", gap: space[3] },
  icon: { width: 44, height: 44, borderRadius: 14, alignItems: "center", justifyContent: "center", backgroundColor: ds.infoSoft },
  name: { flex: 1, minWidth: 0 },
  nameText: { fontSize: 16, lineHeight: 20, fontWeight: "800", color: ds.ink },
  detail: { fontSize: 12, lineHeight: 16, fontWeight: "600", color: ds.inkMuted },
  radio: { width: 22, height: 22, borderRadius: 999, borderWidth: 2, borderColor: ds.lineStrong, alignItems: "center", justifyContent: "center" },
  radioOn: { backgroundColor: ds.brand, borderColor: ds.brand },
  badge: { alignSelf: "flex-start", backgroundColor: ds.infoSoft, borderRadius: 999, paddingHorizontal: space[2], paddingVertical: 2 },
  badgeText: { fontSize: 11, fontWeight: "800", color: ds.info },
  price: { fontSize: 24, lineHeight: 28, fontWeight: "900", letterSpacing: -0.4, color: ds.ink, fontVariant: ["tabular-nums"] },
  priceUnit: { fontSize: 13, fontWeight: "700", color: ds.inkMuted },
  sub: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted, fontVariant: ["tabular-nums"] },
  who: { flexDirection: "row", alignItems: "flex-start", gap: 6, paddingTop: space[3], borderTopWidth: 1, borderStyle: "dashed", borderTopColor: ds.line },
  whoText: { flex: 1, fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.ink },
});
