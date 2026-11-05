import { Feather } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { ds, space } from "./tokens";

type Props = {
  icon: React.ComponentProps<typeof Feather>["name"];
  title: string;
  description?: string | null;
  /** Faixa de preço, ex.: "R$ 80 a R$ 190/mês". */
  meta?: string | null;
  onPress: () => void;
};

// Primeira escolha de um fluxo (Individual × Pacotes): card grande e tocável com ícone,
// título, uma linha do que é e a faixa de preço.
export default function TypeCard({ icon, title, description, meta, onPress }: Props) {
  return (
    <TouchableOpacity
      activeOpacity={0.85}
      onPress={onPress}
      style={styles.card}
      accessibilityRole="button"
      accessibilityLabel={[title, description, meta].filter(Boolean).join(", ")}
    >
      <View style={styles.icon}>
        <Feather name={icon} size={26} color={ds.brandInk} />
      </View>
      <View style={styles.text}>
        <Text style={styles.title}>{title}</Text>
        {description ? <Text style={styles.desc}>{description}</Text> : null}
        {meta ? <Text style={styles.meta}>{meta}</Text> : null}
      </View>
      <View style={styles.go}>
        <Feather name="chevron-right" size={20} color={ds.onBrand} />
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  card: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[4],
    paddingVertical: space[5],
    paddingHorizontal: space[4],
    borderRadius: 20,
    backgroundColor: ds.surface,
    borderWidth: 1,
    borderColor: ds.line,
  },
  icon: {
    width: 56,
    height: 56,
    borderRadius: 18,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: ds.brandSoft,
    borderWidth: 1,
    borderColor: "#ff7a0033",
  },
  text: { flex: 1, minWidth: 0, gap: 2 },
  title: { fontSize: 19, lineHeight: 24, fontWeight: "900", letterSpacing: -0.2, color: ds.ink },
  desc: { fontSize: 14, lineHeight: 19, fontWeight: "600", color: ds.inkMuted },
  meta: { marginTop: 4, fontSize: 13, lineHeight: 18, fontWeight: "800", color: ds.ink, fontVariant: ["tabular-nums"] },
  go: {
    width: 36,
    height: 36,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: ds.brand,
  },
});
