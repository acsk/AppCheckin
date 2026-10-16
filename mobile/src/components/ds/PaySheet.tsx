import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import React, { useEffect, useState } from "react";
import {
  Modal as NativeModal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { createPortal } from "react-dom";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { BrandFacets } from "./AppHeader";
import Button from "./Button";
import PixIcon from "./PixIcon";
import { ds, space } from "./tokens";

type FeatherName = React.ComponentProps<typeof Feather>["name"];

export type PayResumoLinha = { label: string; valor: string; destaque?: boolean };
export type PayResumo = {
  titulo?: string | null;
  subtitulo?: string | null;
  valor?: string | null;
  linhas?: PayResumoLinha[] | null;
};

type Props = React.PropsWithChildren<{
  visible: boolean;
  /** Ícone do quadro: um nome do Feather, ou "pix" para o símbolo oficial. */
  icon: FeatherName | "pix";
  /** Substitui o quadro do ícone (ex.: logo do provedor). */
  media?: React.ReactNode;
  eyebrow: string;
  title: string;
  description?: string | null;
  resumo?: PayResumo | null;
  /** Linha de segurança acima do botão (ex.: dados do cartão ficam com o provedor). */
  secureNote?: string | null;
  primary: { label: string; icon?: FeatherName; iconRight?: FeatherName; onPress: () => void; disabled?: boolean; loading?: boolean };
  onClose: () => void;
}>;

// Folha inferior de pagamento no modelo "Modal" do design system: quadro do ícone em degradê →
// eyebrow → título → descrição → resumo do valor → conteúdo → nota de segurança → botão principal.
// Na web usa portal no body (fica acima de tudo) e ignora toques no fundo nos primeiros 450 ms.
export default function PaySheet({
  visible,
  icon,
  media,
  eyebrow,
  title,
  description,
  resumo,
  secureNote,
  primary,
  onClose,
  children,
}: Props) {
  const insets = useSafeAreaInsets();
  const [backdropPronto, setBackdropPronto] = useState(false);

  useEffect(() => {
    if (!visible) {
      setBackdropPronto(false);
      return;
    }
    const t = setTimeout(() => setBackdropPronto(true), 450);
    return () => clearTimeout(t);
  }, [visible]);

  if (!visible) return null;

  const fecharFundo = () => {
    if (backdropPronto && !primary.loading) onClose();
  };

  const conteudo = (
    <View style={[styles.overlay, Platform.OS === "web" ? styles.overlayWeb : null]} pointerEvents="box-none">
      <Pressable style={StyleSheet.absoluteFill} onPress={fecharFundo} accessibilityLabel="Fechar" />
      <View style={[styles.sheet, { paddingBottom: Math.max(insets.bottom, space[4]) }]} accessibilityViewIsModal accessibilityLabel={title}>
        <View style={styles.handle} />
        <LinearGradient colors={[ds.surface, ds.brandSoft]} start={{ x: 0, y: 1 }} end={{ x: 1, y: 0 }} style={styles.header}>
          <View style={styles.facets} pointerEvents="none">
            <BrandFacets />
          </View>
          {media ?? (
            <LinearGradient
              colors={[ds.brandYellow, ds.brand, ds.brandRed]}
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 1 }}
              style={styles.iconTile}
            >
              {icon === "pix" ? <PixIcon size={22} color={ds.onBrand} /> : <Feather name={icon} size={22} color={ds.onBrand} />}
            </LinearGradient>
          )}
          <View style={styles.heading}>
            <Text style={styles.eyebrow}>{eyebrow}</Text>
            <Text style={styles.title} accessibilityRole="header">
              {title}
            </Text>
          </View>
          <Pressable
            style={styles.close}
            onPress={onClose}
            disabled={primary.loading}
            accessibilityRole="button"
            accessibilityLabel="Fechar"
          >
            <Feather name="x" size={20} color={ds.ink} />
          </Pressable>
        </LinearGradient>

        <ScrollView style={styles.scroll} contentContainerStyle={styles.body} bounces={false}>
          {description ? <Text style={styles.description}>{description}</Text> : null}
          {resumo && (resumo.titulo || resumo.valor || (resumo.linhas && resumo.linhas.length > 0)) ? (
            <View style={[styles.paysum, resumo.linhas?.length ? styles.paysumStacked : null]}>
              {resumo.titulo || resumo.subtitulo ? (
                <View style={{ flex: 1, minWidth: 0 }}>
                  {resumo.titulo ? (
                    <Text style={styles.paysumTitle} numberOfLines={1}>
                      {resumo.titulo}
                    </Text>
                  ) : null}
                  {resumo.subtitulo ? (
                    <Text style={styles.paysumSub} numberOfLines={1}>
                      {resumo.subtitulo}
                    </Text>
                  ) : null}
                </View>
              ) : null}
              {resumo.linhas && resumo.linhas.length > 0 ? (
                <View style={styles.paysumLines}>
                  {resumo.linhas.map((linha) => (
                    <View key={linha.label} style={styles.paysumLine}>
                      <Text style={linha.destaque ? styles.paysumLineLabelStrong : styles.paysumLineLabel}>
                        {linha.label}
                      </Text>
                      <Text style={linha.destaque ? styles.paysumValue : styles.paysumLineValor}>
                        {linha.valor}
                      </Text>
                    </View>
                  ))}
                </View>
              ) : resumo.valor ? (
                <Text style={styles.paysumValue}>{resumo.valor}</Text>
              ) : null}
            </View>
          ) : null}
          {children}
        </ScrollView>

        <View style={styles.footer}>
          {secureNote ? (
            <View style={styles.secure}>
              <Feather name="lock" size={14} color={ds.success} style={{ marginTop: 1 }} />
              <Text style={styles.secureText}>{secureNote}</Text>
            </View>
          ) : null}
          <Button
            block
            size="lg"
            icon={primary.icon}
            iconRight={primary.iconRight}
            loading={primary.loading}
            disabled={primary.disabled}
            onPress={primary.onPress}
          >
            {primary.label}
          </Button>
        </View>
      </View>
    </View>
  );

  if (Platform.OS === "web" && typeof document !== "undefined") {
    return createPortal(conteudo, document.body);
  }

  return (
    <NativeModal visible transparent animationType="slide" presentationStyle="overFullScreen" onRequestClose={onClose}>
      {conteudo}
    </NativeModal>
  );
}

/** Lista numerada curta (ex.: o que acontece no checkout externo). */
export function PaySteps({ steps }: { steps: React.ReactNode[] }) {
  return (
    <View style={styles.steps}>
      {steps.map((s, i) => (
        <View key={i} style={styles.step}>
          <View style={styles.stepN}>
            <Text style={styles.stepNText}>{i + 1}</Text>
          </View>
          <Text style={styles.stepText}>{s}</Text>
        </View>
      ))}
    </View>
  );
}

export const payStyles = StyleSheet.create({
  bold: { color: ds.ink, fontWeight: "800" },
});

const styles = StyleSheet.create({
  overlay: { flex: 1, backgroundColor: ds.scrim, justifyContent: "flex-end", alignItems: "center" },
  overlayWeb: {
    position: "fixed" as const,
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    zIndex: 2147483000,
    width: "100%",
    height: "100%",
  },
  sheet: {
    width: "100%",
    maxWidth: 560,
    maxHeight: "92%",
    backgroundColor: ds.surface,
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    overflow: "hidden",
    zIndex: 2,
  },
  handle: {
    position: "absolute",
    top: 8,
    left: "50%",
    marginLeft: -20,
    width: 40,
    height: 4,
    borderRadius: 2,
    backgroundColor: ds.line,
    zIndex: 3,
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    paddingTop: space[6],
    paddingBottom: space[4],
    paddingHorizontal: space[5],
    overflow: "hidden",
  },
  facets: { ...StyleSheet.absoluteFillObject, opacity: 0.5 },
  iconTile: { width: 48, height: 48, borderRadius: 16, alignItems: "center", justifyContent: "center" },
  heading: { flex: 1, minWidth: 0 },
  eyebrow: { fontSize: 11, lineHeight: 14, fontWeight: "800", letterSpacing: 0.88, textTransform: "uppercase", color: ds.brandInk },
  title: { fontSize: 22, lineHeight: 28, fontWeight: "900", letterSpacing: -0.3, color: ds.ink },
  close: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "rgba(23, 26, 33, 0.06)",
  },
  scroll: { flexGrow: 0 },
  body: { paddingHorizontal: space[5], paddingBottom: space[3], gap: space[3] },
  description: { fontSize: 15, lineHeight: 22, fontWeight: "500", color: ds.inkMuted },
  paysum: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    paddingVertical: space[3],
    paddingHorizontal: space[4],
    borderRadius: 16,
    backgroundColor: ds.surfaceSunken,
  },
  paysumTitle: { fontSize: 15, lineHeight: 20, fontWeight: "800", color: ds.ink },
  paysumSub: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted },
  paysumStacked: { flexDirection: "column", alignItems: "stretch", gap: space[3] },
  paysumLines: { gap: 8, width: "100%" },
  paysumLine: { flexDirection: "row", justifyContent: "space-between", alignItems: "center", gap: space[3] },
  paysumLineLabel: { fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted, flex: 1 },
  paysumLineLabelStrong: { fontSize: 14, lineHeight: 18, fontWeight: "800", color: ds.ink, flex: 1 },
  paysumLineValor: { fontSize: 14, lineHeight: 18, fontWeight: "700", color: ds.ink, fontVariant: ["tabular-nums"] },
  paysumValue: { fontSize: 20, lineHeight: 24, fontWeight: "900", letterSpacing: -0.4, color: ds.ink, fontVariant: ["tabular-nums"] },
  footer: { paddingHorizontal: space[5], paddingTop: space[2], gap: space[2] },
  secure: { flexDirection: "row", alignItems: "flex-start", gap: 8, marginBottom: space[2] },
  secureText: { flex: 1, fontSize: 12, lineHeight: 16, fontWeight: "600", color: ds.inkMuted },
  steps: { gap: space[3], paddingTop: 4, paddingHorizontal: 4 },
  step: { flexDirection: "row", gap: space[2], alignItems: "flex-start" },
  stepN: { width: 22, height: 22, borderRadius: 11, backgroundColor: ds.ink, alignItems: "center", justifyContent: "center" },
  stepNText: { fontSize: 12, fontWeight: "900", color: ds.surface },
  stepText: { flex: 1, fontSize: 14, lineHeight: 19, fontWeight: "500", color: ds.inkMuted },
});
