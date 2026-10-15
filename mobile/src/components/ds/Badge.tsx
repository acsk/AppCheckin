import { Feather } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, Text, View } from "react-native";
import { radius, Tone, toneColors } from "./tokens";

type Props = {
  tone?: Tone;
  icon?: React.ComponentProps<typeof Feather>["name"];
  children?: React.ReactNode;
};

// Badge em pílula: texto de estado sobre o -soft correspondente, sempre com palavra.
export default function Badge({ tone = "neutral", icon, children }: Props) {
  const c = toneColors[tone];
  return (
    <View style={[styles.badge, { backgroundColor: c.bg }]}>
      {icon ? <Feather name={icon} size={14} color={c.fg} /> : null}
      <Text style={[styles.text, { color: c.fg }]} numberOfLines={1}>
        {children}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    flexDirection: "row",
    alignItems: "center",
    alignSelf: "flex-start",
    gap: 4,
    height: 24,
    paddingHorizontal: 10,
    borderRadius: radius.full,
  },
  text: {
    fontSize: 12,
    lineHeight: 16,
    fontWeight: "800",
    letterSpacing: 0.48,
    textTransform: "uppercase",
  },
});
