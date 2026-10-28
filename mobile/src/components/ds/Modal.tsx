import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import React, { useEffect, useRef, useState } from "react";
import { KeyboardAvoidingView, NativeScrollEvent, NativeSyntheticEvent, Modal as NativeModal, Platform, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
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
  avoidKeyboard?: boolean;
  primary: { label: string; icon?: React.ComponentProps<typeof Feather>["name"]; onPress: () => void; loading?: boolean; disabled?: boolean };
  onClose: () => void;
  /** Só libera o botão principal depois de rolar o conteúdo até o fim. */
  exigirLeitura?: boolean;
  /** Sem o botão X no cabeçalho (fecha tocando fora ou no voltar do aparelho). */
  semFechar?: boolean;
  /** Mais larga e com menos margem nas laterais (texto longo). */
  larga?: boolean;
}>;

/** Folga para considerar que chegou ao fim do conteúdo. */
const FIM_LEITURA = 24;

export default function Modal({
  visible, title, description, eyebrow, icon = "alert-triangle",
  variant = "sheet", avoidKeyboard = false, primary, onClose, children, exigirLeitura = false, semFechar = false, larga = false,
}: Props) {
  const close = () => { if (!primary.loading) onClose(); };
  const [leu, setLeu] = useState(!exigirLeitura);
  const medida = useRef({ tela: 0, conteudo: 0 });
  // Conteúdo novo (outro aviso na mesma modal) exige nova leitura
  useEffect(() => { setLeu(!exigirLeitura); medida.current = { tela: 0, conteudo: 0 }; }, [exigirLeitura, title, visible]);

  const medir = (tela?: number, conteudo?: number) => {
    if (!exigirLeitura) return;
    if (tela !== undefined) medida.current.tela = tela;
    if (conteudo !== undefined) medida.current.conteudo = conteudo;
    const { tela: t, conteudo: c } = medida.current;
    // Cabe inteiro na tela: não há o que rolar
    if (t > 0 && c > 0 && c <= t + FIM_LEITURA) setLeu(true);
  };
  const rolar = ({ nativeEvent: e }: NativeSyntheticEvent<NativeScrollEvent>) => {
    if (exigirLeitura && e.layoutMeasurement.height + e.contentOffset.y >= e.contentSize.height - FIM_LEITURA) setLeu(true);
  };
  return (
    <NativeModal visible={visible} transparent animationType="fade" onRequestClose={close}>
      <KeyboardAvoidingView style={styles.keyboard} enabled={avoidKeyboard && Platform.OS !== "web"} behavior={Platform.OS === "ios" ? "padding" : "height"}>
      <SafeAreaView style={[styles.overlay, variant === "sheet" && styles.sheetOverlay, larga && styles.overlayLarga]}>
        <Pressable style={StyleSheet.absoluteFill} onPress={close} accessibilityLabel="Fechar modal" accessibilityRole="button" />
        <View style={[styles.card, larga && styles.cardLarga]} accessibilityViewIsModal accessibilityLabel={title}>
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
            {semFechar ? null : (
              <Pressable style={styles.close} onPress={close} disabled={primary.loading} accessibilityRole="button" accessibilityLabel="Fechar">
                <Feather name="x" size={20} color={ds.ink} />
              </Pressable>
            )}
          </LinearGradient>
          <ScrollView style={styles.scroll} contentContainerStyle={[styles.body, larga && styles.bodyLarga]} nestedScrollEnabled keyboardShouldPersistTaps="handled"
            onScroll={exigirLeitura ? rolar : undefined} scrollEventThrottle={100}
            onLayout={(e) => medir(e.nativeEvent.layout.height)} onContentSizeChange={(_, h) => medir(undefined, h)}>
            {description ? <Text style={styles.description}>{description}</Text> : null}
            {children}
          </ScrollView>
          <View style={[styles.footer, larga && styles.footerLarga]}>
            {!leu ? <Text style={styles.hint}>Role até o fim para continuar.</Text> : null}
            <Button block size="lg" accessibilityLabel={primary.label} icon={primary.icon} disabled={primary.disabled || !leu} loading={primary.loading} onPress={primary.onPress}>
              {primary.label}
            </Button>
          </View>
        </View>
      </SafeAreaView>
      </KeyboardAvoidingView>
    </NativeModal>
  );
}

const styles = StyleSheet.create({
  keyboard: { flex: 1 },
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
  footer: { gap: space[2], borderTopWidth: 1, borderTopColor: ds.line, padding: space[5] },
  hint: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted, textAlign: "center" },
  overlayLarga: { padding: space[2] },
  cardLarga: { maxWidth: 680 },
  bodyLarga: { paddingHorizontal: space[4] },
  footerLarga: { paddingHorizontal: space[4] },
});
