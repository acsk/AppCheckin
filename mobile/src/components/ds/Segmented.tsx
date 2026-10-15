import { Feather } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { ds, radius } from "./tokens";

export type SegmentedOption = {
  id: string;
  label: string;
  icon?: React.ComponentProps<typeof Feather>["name"];
  count?: number | null;
};

type Props = {
  options: SegmentedOption[];
  value: string;
  onChange: (id: string) => void;
  /** Rótulo acessível do grupo. */
  label?: string;
  size?: "sm" | "md";
};

// Controle segmentado do design system: a opção ativa vira um cartão surface
// e o contador dela fica brand. Até 3 opções, rótulos de uma palavra.
export default function Segmented({ options, value, onChange, label, size = "md" }: Props) {
  return (
    <View style={styles.ctl} accessibilityRole="tablist" accessibilityLabel={label}>
      {options.map((o) => {
        const on = o.id === value;
        return (
          <TouchableOpacity
            key={o.id}
            activeOpacity={0.8}
            onPress={() => onChange(o.id)}
            accessibilityRole="tab"
            accessibilityState={{ selected: on }}
            style={[styles.opt, size === "sm" && styles.optSm, on && styles.optOn]}
          >
            {o.icon ? <Feather name={o.icon} size={16} color={on ? ds.ink : ds.inkMuted} /> : null}
            <Text style={[styles.text, size === "sm" && styles.textSm, on && styles.textOn]} numberOfLines={1}>
              {o.label}
            </Text>
            {o.count != null ? (
              <View style={[styles.count, on && styles.countOn]}>
                <Text style={styles.countText}>{o.count}</Text>
              </View>
            ) : null}
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  ctl: {
    flexDirection: "row",
    gap: 4,
    padding: 4,
    borderRadius: radius.md,
    backgroundColor: ds.surfaceSunken,
    borderWidth: 1,
    borderColor: ds.line,
  },
  opt: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    height: 40,
    paddingHorizontal: 12,
    borderRadius: 9,
  },
  optSm: { height: 34 },
  optOn: { backgroundColor: ds.surface, borderWidth: 1, borderColor: ds.line },
  text: { fontSize: 14, fontWeight: "800", color: ds.inkMuted },
  textSm: { fontSize: 13 },
  textOn: { color: ds.ink },
  count: {
    minWidth: 20,
    height: 20,
    paddingHorizontal: 6,
    borderRadius: radius.full,
    backgroundColor: ds.line,
    alignItems: "center",
    justifyContent: "center",
  },
  countOn: { backgroundColor: ds.brand },
  countText: { fontSize: 11, fontWeight: "900", color: ds.ink, fontVariant: ["tabular-nums"] },
});
