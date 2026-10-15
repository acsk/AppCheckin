import { Feather } from "@expo/vector-icons";
import React from "react";
import { ActivityIndicator, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { ds, space } from "./tokens";

type FeatherName = React.ComponentProps<typeof Feather>["name"];

export type InfoChip = { icon?: FeatherName; text: string; tone?: "default" | "dark" };

type Props = {
  items: InfoChip[];
  action?: {
    label: string;
    icon?: FeatherName;
    tone?: "danger" | "default";
    onPress?: () => void;
    loading?: boolean;
    disabled?: boolean;
  } | null;
};

// Pílulas de vidro para o `bottom` do AppHeader (horário, prazo, vagas) e uma ação grafite.
export default function InfoChips({ items, action }: Props) {
  return (
    <View style={styles.row}>
      {items.map((it) => {
        const dark = it.tone === "dark";
        return (
          <View key={it.text} style={[styles.chip, dark && styles.chipDark]}>
            {it.icon ? <Feather name={it.icon} size={15} color={dark ? ds.onInverse : ds.onBrand} /> : null}
            <Text style={[styles.text, dark && { color: ds.onInverse }]}>{it.text}</Text>
          </View>
        );
      })}
      {action ? (
        <TouchableOpacity
          onPress={action.onPress}
          disabled={action.disabled || action.loading}
          accessibilityRole="button"
          accessibilityLabel={action.label}
          style={[styles.chip, styles.action, (action.disabled || action.loading) && { opacity: 0.6 }]}
          activeOpacity={0.85}
        >
          {action.loading ? (
            <ActivityIndicator size="small" color={ds.onInverse} />
          ) : action.icon ? (
            <Feather name={action.icon} size={15} color={action.tone === "danger" ? "#ff8a80" : ds.onInverse} />
          ) : null}
          <Text style={[styles.text, { color: ds.onInverse }]}>{action.label}</Text>
        </TouchableOpacity>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: "row", flexWrap: "wrap", gap: 6, paddingTop: 2, paddingHorizontal: space[4], paddingBottom: space[4] },
  chip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    height: 30,
    paddingHorizontal: 11,
    borderRadius: 999,
    backgroundColor: "rgba(255, 255, 255, 0.28)",
    borderWidth: 1,
    borderColor: "rgba(255, 255, 255, 0.45)",
  },
  chipDark: { backgroundColor: ds.ink, borderColor: ds.ink },
  text: { fontSize: 13, fontWeight: "800", color: ds.onBrand, fontVariant: ["tabular-nums"] },
  action: {
    marginLeft: "auto",
    backgroundColor: ds.ink,
    borderColor: ds.ink,
    shadowColor: ds.ink,
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.35,
    shadowRadius: 10,
    elevation: 4,
  },
});
