import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import React from "react";
import { Modal as NativeModal, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { BrandFacets } from "./AppHeader";
import Button from "./Button";
import { ds, space } from "./tokens";

type Props = React.PropsWithChildren<{
  visible: boolean;
  title: string;
  description?: string;
  eyebrow?: string;
  icon?: React.ComponentProps<typeof Feather>["name"];
  variant?: "sheet" | "dialog";
  primary: { label: string; icon?: React.ComponentProps<typeof Feather>["name"]; onPress: () => void; loading?: boolean };
  onClose: () => void;
}>;

export default function Modal({
  visible, title, description, eyebrow, icon = "alert-triangle",
  variant = "sheet", primary, onClose, children,
}: Props) {
  const close = () => { if (!primary.loading) onClose(); };
  return (
    <NativeModal visible={visible} transparent animationType="fade" onRequestClose={close}>
      <SafeAreaView style={[styles.overlay, variant === "sheet" && styles.sheetOverlay]}>
        <Pressable style={StyleSheet.absoluteFill} onPress={close} accessibilityLabel="Fechar modal" accessibilityRole="button" />
        <View style={styles.card} accessibilityViewIsModal accessibilityLabel={title}>
          {variant === "sheet" && <View style={styles.handle} />}
          <LinearGradient colors={[ds.surface, ds.brandSoft]} start={{ x: 0, y: 1 }} end={{ x: 1, y: 0 }} style={styles.header}>
            <View style={styles.facets} pointerEvents="none"><BrandFacets /></View>
            <LinearGradient colors={[ds.brandYellow, ds.brand, ds.brandRed]} style={styles.icon}>
              <Feather name={icon} size={24} color={ds.onBrand} />
            </LinearGradient>
            <View style={styles.heading}>
              {eyebrow ? <Text style={styles.eyebrow}>{eyebrow}</Text> : null}
              <Text accessibilityRole="header" style={styles.title}>{title}</Text>
            </View>
            <Pressable style={styles.close} onPress={close} disabled={primary.loading} accessibilityRole="button" accessibilityLabel="Fechar">
              <Feather name="x" size={20} color={ds.ink} />
            </Pressable>
          </LinearGradient>
          <ScrollView style={styles.scroll} contentContainerStyle={styles.body} nestedScrollEnabled>
            {description ? <Text style={styles.description}>{description}</Text> : null}
            {children}
          </ScrollView>
          <View style={styles.footer}>
            <Button block size="lg" icon={primary.icon} loading={primary.loading} onPress={primary.onPress}>
              {primary.label}
            </Button>
          </View>
        </View>
      </SafeAreaView>
    </NativeModal>
  );
}

const styles = StyleSheet.create({
  overlay: { flex: 1, backgroundColor: ds.scrim, justifyContent: "center", alignItems: "center", padding: space[4] },
  sheetOverlay: { justifyContent: "flex-end", padding: space[2] },
  card: { width: "100%", maxWidth: 440, maxHeight: "94%", backgroundColor: ds.surface, borderRadius: 28, borderWidth: 1, borderColor: ds.line, overflow: "hidden" },
  handle: { alignSelf: "center", width: 40, height: 5, borderRadius: 3, backgroundColor: ds.lineStrong, opacity: 0.5, marginTop: 8 },
  header: { flexDirection: "row", alignItems: "center", gap: space[4], padding: space[5] },
  facets: { ...StyleSheet.absoluteFillObject, opacity: 0.07 },
  icon: { width: 48, height: 48, borderRadius: 16, alignItems: "center", justifyContent: "center" },
  heading: { flex: 1, minWidth: 0, gap: 4 },
  eyebrow: { fontSize: 11, lineHeight: 16, fontWeight: "800", letterSpacing: 0.8, textTransform: "uppercase", color: ds.brandInk },
  title: { fontSize: 22, lineHeight: 26, fontWeight: "900", letterSpacing: -0.4, color: ds.ink },
  close: { width: 40, height: 40, alignSelf: "flex-start", alignItems: "center", justifyContent: "center", borderRadius: 20, backgroundColor: ds.surfaceSunken },
  scroll: { flexShrink: 1 },
  body: { paddingHorizontal: space[5], paddingBottom: space[4], gap: space[3] },
  description: { fontSize: 15, lineHeight: 22, fontWeight: "500", color: ds.inkMuted },
  footer: { borderTopWidth: 1, borderTopColor: ds.line, padding: space[5] },
});
