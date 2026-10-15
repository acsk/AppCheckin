import { Feather } from "@expo/vector-icons";
import React from "react";
import { StyleSheet, Text, TouchableOpacity, View } from "react-native";
import { ds, radius, space, Tone, toneColors, type } from "./tokens";

type Props = {
  title: React.ReactNode;
  subtitle?: React.ReactNode;
  icon?: React.ComponentProps<typeof Feather>["name"];
  /** Ícone personalizado (ex.: outra família), no lugar de `icon`. */
  iconNode?: React.ReactNode;
  tone?: Extract<Tone, "brand" | "success" | "info"> | null;
  leading?: React.ReactNode;
  trailing?: React.ReactNode;
  chevron?: boolean;
  onPress?: () => void;
  accessibilityLabel?: string;
};

// Linha de lista: quadro de ícone 40px, título 17/700, apoio 13/600, seta opcional.
export default function ListRow({
  title,
  subtitle,
  icon,
  iconNode,
  tone,
  leading,
  trailing,
  chevron,
  onPress,
  accessibilityLabel,
}: Props) {
  const c = tone ? toneColors[tone] : { bg: ds.surfaceSunken, fg: ds.ink };
  const body = (
    <>
      {leading ??
        (icon || iconNode ? (
          <View style={[styles.icon, { backgroundColor: c.bg }]}>
            {iconNode ?? <Feather name={icon!} size={20} color={c.fg} />}
          </View>
        ) : null)}
      <View style={styles.text}>
        {typeof title === "string" ? (
          <Text style={styles.title} numberOfLines={1}>
            {title}
          </Text>
        ) : (
          title
        )}
        {subtitle == null ? null : typeof subtitle === "string" ? (
          <Text style={styles.sub} numberOfLines={2}>
            {subtitle}
          </Text>
        ) : (
          subtitle
        )}
      </View>
      {trailing ? <View style={styles.trail}>{trailing}</View> : null}
      {chevron ?? !!onPress ? <Feather name="chevron-right" size={20} color={ds.inkMuted} /> : null}
    </>
  );

  if (onPress) {
    return (
      <TouchableOpacity
        style={styles.row}
        onPress={onPress}
        activeOpacity={0.7}
        accessibilityRole="button"
        accessibilityLabel={accessibilityLabel ?? (typeof title === "string" ? title : undefined)}
      >
        {body}
      </TouchableOpacity>
    );
  }
  return <View style={styles.row}>{body}</View>;
}

const styles = StyleSheet.create({
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    paddingVertical: space[3],
    paddingHorizontal: space[4],
    minHeight: 64,
  },
  icon: {
    width: 40,
    height: 40,
    borderRadius: radius.sm,
    alignItems: "center",
    justifyContent: "center",
  },
  text: { flex: 1, minWidth: 0 },
  title: { ...type.heading, color: ds.ink },
  sub: { ...type.caption, color: ds.inkMuted },
  trail: { flexDirection: "row", alignItems: "center", gap: space[2] },
});
