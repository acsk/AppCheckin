import React, { useState } from "react";
import { Image, StyleSheet, Text, View } from "react-native";
import { ds } from "./tokens";

export const initials = (name: string = "") => {
  const parts = String(name || "?").trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return "?";
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
};

type Props = { name?: string | null; uri?: string | null; size?: number; ring?: boolean };

// Avatar redondo: foto ou iniciais em ink sobre surface-sunken.
export default function Avatar({ name, uri, size = 40, ring }: Props) {
  const [failedUri, setFailedUri] = useState<string | null>(null);
  const inner = (
    <View style={[styles.base, { width: size, height: size, borderRadius: size / 2 }]}>
      {uri && uri !== failedUri ? (
        <Image
          source={{ uri }}
          style={{ width: size, height: size }}
          onError={() => setFailedUri(uri)}
          accessibilityIgnoresInvertColors
        />
      ) : (
        <Text style={[styles.text, { fontSize: Math.round(size * 0.38) }]}>{initials(name || "")}</Text>
      )}
    </View>
  );
  if (!ring) return inner;
  // Anel brand separado por 3px de surface (perfil do menu lateral).
  return (
    <View style={[styles.ring, { width: size + 10, height: size + 10, borderRadius: (size + 10) / 2 }]}>
      {inner}
    </View>
  );
}

const styles = StyleSheet.create({
  base: {
    backgroundColor: ds.surfaceSunken,
    borderWidth: 1,
    borderColor: ds.line,
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  text: { fontWeight: "800", color: ds.ink },
  ring: {
    borderWidth: 2,
    borderColor: ds.brand,
    padding: 3,
    backgroundColor: ds.surface,
    alignItems: "center",
    justifyContent: "center",
  },
});
