import React from "react";
import { StyleProp, StyleSheet, Text, View, ViewStyle } from "react-native";
import { ds, radius, space, type } from "./tokens";

type Props = {
  title?: string | null;
  aside?: React.ReactNode;
  /** Sem card: os filhos vão direto sobre o fundo (ex.: lista de cards). */
  bare?: boolean;
  /** Card com padding interno, sem divisórias entre filhos. */
  padded?: boolean;
  children?: React.ReactNode;
  style?: StyleProp<ViewStyle>;
};

// Bloco da tela: rótulo em MAIÚSCULAS fora do card, card surface com borda line,
// filhos separados por divisória.
export default function Section({ title, aside, bare, padded, children, style }: Props) {
  const items = React.Children.toArray(children).filter(Boolean);
  return (
    <View style={[styles.section, style]}>
      {title || aside ? (
        <View style={styles.head}>
          {title ? <Text style={styles.title}>{title}</Text> : <View />}
          {typeof aside === "string" ? <Text style={styles.aside}>{aside}</Text> : aside}
        </View>
      ) : null}
      {bare ? (
        children
      ) : (
        <View style={[styles.card, padded && { padding: space[4] }]}>
          {padded
            ? children
            : items.map((child, i) => (
                <View key={i} style={i > 0 ? styles.divided : undefined}>
                  {child}
                </View>
              ))}
        </View>
      )}
    </View>
  );
}

export const cardStyle = {
  backgroundColor: ds.surface,
  borderWidth: 1,
  borderColor: ds.line,
  borderRadius: radius.lg,
  overflow: "hidden" as const,
};

const styles = StyleSheet.create({
  section: { gap: space[2] },
  head: {
    flexDirection: "row",
    alignItems: "baseline",
    justifyContent: "space-between",
    gap: space[3],
    paddingHorizontal: space[1],
  },
  title: { ...type.label, color: ds.inkMuted },
  aside: { ...type.caption, color: ds.inkMuted },
  card: cardStyle,
  divided: { borderTopWidth: 1, borderTopColor: ds.line },
});
