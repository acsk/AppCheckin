import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { space } from "./tokens";

export type HeaderTabOption = { id: string; label: string };

type Props = {
  options: HeaderTabOption[];
  value: string;
  onChange: (id: string) => void;
  label?: string;
};

// Abas dentro do AppHeader (`bottom`), sobre o degradê. A ativa fica em grafite.
export default function HeaderTabs({ options, value, onChange, label }: Props) {
  return (
    <View style={styles.wrap}>
      <View style={styles.tabs} accessibilityRole="tablist" accessibilityLabel={label}>
        {options.map((o) => {
          const on = o.id === value;
          return (
            <TouchableOpacity
              key={o.id}
              accessibilityRole="tab"
              accessibilityState={{ selected: on }}
              onPress={() => onChange(o.id)}
              activeOpacity={0.85}
              style={[styles.tab, on && styles.tabOn]}
            >
              <Text style={[styles.text, on && styles.textOn]} numberOfLines={1}>
                {o.label}
              </Text>
            </TouchableOpacity>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { paddingHorizontal: space[4], paddingBottom: space[4] },
  tabs: {
    flexDirection: "row",
    gap: 4,
    padding: 4,
    borderRadius: 14,
    backgroundColor: "rgba(23, 26, 33, 0.14)",
    borderWidth: 1,
    borderColor: "rgba(23, 26, 33, 0.08)",
  },
  tab: { flex: 1, height: 38, alignItems: "center", justifyContent: "center", borderRadius: 10 },
  tabOn: {
    backgroundColor: "#171a21",
    shadowColor: "#171a21",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.35,
    shadowRadius: 8,
    elevation: 3,
  },
  text: { fontSize: 14, fontWeight: "800", color: "#171a21" },
  textOn: { color: "#ffffff" },
});
