import { ds, type } from "@/src/components/ds/tokens";
import React from "react";
import { Linking, StyleSheet, Text, type TextStyle, type StyleProp } from "react-native";

/** Texto de comunicação: preserva parágrafos e deixa endereços https:// clicáveis. */
export default function ComunicacaoTexto({ children, style, numberOfLines }: { children?: string | null; style?: StyleProp<TextStyle>; numberOfLines?: number }) {
  if (!children) return null;
  const partes = children.split(/(https?:\/\/[^\s]+)/g);

  return (
    <Text style={[styles.texto, style]} numberOfLines={numberOfLines}>
      {partes.map((p, i) =>
        /^https?:\/\//.test(p) ? (
          <Text key={i} style={styles.link} accessibilityRole="link" onPress={() => void Linking.openURL(p)}>
            {p}
          </Text>
        ) : (
          p
        ),
      )}
    </Text>
  );
}

const styles = StyleSheet.create({
  texto: { ...type.body, color: ds.ink },
  link: { color: ds.brandInk, fontWeight: "700", textDecorationLine: "underline" },
});
