import React from "react";
import { Image, type ImageSourcePropType, StyleSheet, Text, View } from "react-native";
import { idProvedorPagamento } from "@/src/utils/metodosPagamento";
import { ds } from "./tokens";

// Logos oficiais dos provedores (mesmos arquivos do painel).
const LOGOS: Record<"asaas" | "mercadopago", ImageSourcePropType> = {
  asaas: require("@/assets/images/providers/asaas.png"),
  mercadopago: require("@/assets/images/providers/mercadopago.png"),
};

/** Logo do provedor a partir do id/nome (ou da URL do checkout); null se não houver logo. */
export function logoProvedor(raw?: string | null, url?: string | null): ImageSourcePropType | null {
  const id = idProvedorPagamento(raw, url);
  return id ? LOGOS[id] : null;
}

type Props = { provider?: string | null; url?: string | null; name?: string | null; size?: "md" | "lg" };

// Quadro branco com a logo (design system · ProviderLogo). Sem logo: nome em caixa tracejada.
export default function ProviderLogo({ provider, url, name, size = "md" }: Props) {
  const src = logoProvedor(provider ?? name, url);
  const lg = size === "lg";
  return (
    <View style={[styles.box, lg && styles.lg, !src && styles.empty]} accessibilityLabel={name || undefined}>
      {src ? (
        <Image source={src} style={styles.img} resizeMode="contain" />
      ) : (
        <Text style={styles.emptyText} numberOfLines={2}>
          {name || "Logo"}
        </Text>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  box: {
    width: 72,
    height: 44,
    borderRadius: 12,
    paddingVertical: 6,
    paddingHorizontal: 8,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#ffffff",
    borderWidth: 1,
    borderColor: "rgba(255, 122, 0, 0.33)",
    overflow: "hidden",
  },
  lg: { width: 96, height: 56, borderRadius: 16, paddingVertical: 8, paddingHorizontal: 10 },
  empty: { backgroundColor: ds.surfaceSunken, borderWidth: 1.5, borderStyle: "dashed", borderColor: ds.lineStrong },
  img: { width: "100%", height: "100%" },
  emptyText: {
    fontSize: 10,
    lineHeight: 12,
    fontWeight: "800",
    letterSpacing: 0.4,
    textTransform: "uppercase",
    color: ds.inkMuted,
    textAlign: "center",
  },
});
