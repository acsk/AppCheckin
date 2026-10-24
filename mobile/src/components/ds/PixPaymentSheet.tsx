import { Feather } from "@expo/vector-icons";
import React, { useEffect, useRef, useState } from "react";
import { Image, Modal, Platform, Pressable, ScrollView, StyleSheet, Text, useWindowDimensions, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Button from "./Button";
import { ds, radius, space, type } from "./tokens";

export type PixStatus = "waiting" | "checking" | "paid" | "expired";

function uriDoQrPix(qrBase64: string): string {
  const valor = qrBase64.trim().replace(/^data:image\\\/png;base64,/i, "data:image/png;base64,");
  return valor.startsWith("data:") ? valor : `data:image/png;base64,${valor}`;
}

type Props = {
  visible: boolean;
  /** Valor já formatado, ex.: "R$ 150,00". */
  amount: string;
  description?: string | null;
  code?: string | null;
  qrBase64?: string | null;
  expiresAt?: string | null;
  ticketUrl?: string | null;
  status?: PixStatus;
  notice?: string | null;
  onClose: () => void;
  /** Só mostra a confirmação quando a cópia resolve true. */
  onCopy: (code: string) => Promise<boolean>;
  onCheck?: () => void;
  onOpenBank?: () => void;
  onDone?: () => void;
  onRenew?: () => void;
  doneLabel?: string;
};

const fmtLeft = (sec: number) => {
  const s = Math.max(0, Math.floor(sec));
  const days = Math.floor(s / 86400);
  const hours = Math.floor(s / 3600);
  const minutes = Math.floor((s % 3600) / 60);
  const z = (value: number) => String(value).padStart(2, "0");
  if (days) return days === 1 ? "1 dia" : `${days} dias`;
  if (hours) return `${hours}h ${z(minutes)}min`;
  return `${z(minutes)}:${z(s % 60)}`;
};

const secondsUntil = (iso?: string | null) => {
  if (!iso) return null;
  const time = new Date(iso.replace(" ", "T")).getTime();
  return Number.isNaN(time) ? null : Math.round((time - Date.now()) / 1000);
};

// Copia e cola primeiro; QR Code para outro aparelho. O conteúdo rola e as ações ficam fixas.
export default function PixPaymentSheet({
  visible, amount, description, code, qrBase64, expiresAt, ticketUrl,
  status = "waiting", notice, onClose, onCopy, onCheck, onOpenBank, onDone, onRenew,
  doneLabel = "Ver minha assinatura",
}: Props) {
  const insets = useSafeAreaInsets();
  const { width, height } = useWindowDimensions();
  const [copied, setCopied] = useState(false);
  const [copyError, setCopyError] = useState(false);
  const [left, setLeft] = useState<number | null>(() => secondsUntil(expiresAt));
  const copyTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const copyGeneration = useRef(0);

  useEffect(() => {
    setCopied(false);
    setCopyError(false);
    copyGeneration.current += 1;
    return () => {
      copyGeneration.current += 1;
      if (copyTimer.current) clearTimeout(copyTimer.current);
    };
  }, [code, visible]);

  useEffect(() => {
    setLeft(secondsUntil(expiresAt));
    if (!visible || !expiresAt || (status !== "waiting" && status !== "checking")) return;
    const timer = setInterval(() => setLeft(secondsUntil(expiresAt)), 1000);
    return () => clearInterval(timer);
  }, [expiresAt, status, visible]);

  const st: PixStatus = status === "waiting" && left != null && left <= 0 ? "expired" : status;
  const active = st === "waiting" || st === "checking";
  const qrSize = Math.max(72, Math.min(140, width - 88));

  const copy = async () => {
    if (!code || !active) return;
    const generation = ++copyGeneration.current;
    setCopyError(false);
    if (copyTimer.current) clearTimeout(copyTimer.current);
    try {
      const ok = await onCopy(code);
      if (generation !== copyGeneration.current) return;
      setCopied(ok);
      setCopyError(!ok);
      if (ok) copyTimer.current = setTimeout(() => setCopied(false), 2500);
    } catch {
      if (generation !== copyGeneration.current) return;
      setCopied(false);
      setCopyError(true);
    }
  };

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose}>
      <View style={styles.scrim}>
        <Pressable style={StyleSheet.absoluteFill} onPress={onClose} accessibilityRole="button" accessibilityLabel="Fechar pagamento PIX" />
        <View style={[styles.sheet, { maxHeight: Math.min(height * 0.94, height - insets.top - 8) }]} accessibilityViewIsModal accessibilityLabel="Pagamento via PIX">
          <View style={styles.bar}>
            <View style={styles.grabber} />
            <Text style={styles.sheetTitle} maxFontSizeMultiplier={1.3}>{st === "paid" ? "Tudo certo" : "Pagamento via PIX"}</Text>
            <Button variant="ghost" icon="x" accessibilityLabel="Fechar" onPress={onClose} />
          </View>

          <ScrollView style={styles.scroll} contentContainerStyle={styles.scrollContent} bounces={false}>
            <View style={styles.head}>
              <Text style={styles.kvK} maxFontSizeMultiplier={1.3}>TOTAL A PAGAR</Text>
              <Text style={styles.amount} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.7} maxFontSizeMultiplier={1.2}>{amount}</Text>
              {description ? <Text style={styles.meta} maxFontSizeMultiplier={1.3}>{description}</Text> : null}
              {active && left != null ? (
                <View style={styles.expiration}>
                  <Feather name="clock" size={12} color={left < 300 ? ds.danger : ds.inkMuted} />
                  <Text style={[styles.expirationText, left < 300 && { color: ds.danger }]} maxFontSizeMultiplier={1.3}>
                    {left <= 0 ? "Código expirado" : `Expira em ${fmtLeft(left)}`}
                  </Text>
                </View>
              ) : null}
            </View>

            {st === "paid" || st === "expired" ? (
              <View style={styles.done}>
                <View style={[styles.doneMark, { backgroundColor: st === "paid" ? ds.success : ds.dangerSoft }]}>
                  <Feather name={st === "paid" ? "check" : "clock"} size={36} color={st === "paid" ? ds.onSuccess : ds.danger} />
                </View>
                <Text style={styles.doneTitle}>{st === "paid" ? "Pagamento confirmado" : "Código expirado"}</Text>
                <Text style={styles.doneText}>{st === "paid" ? "Seu plano já está ativo. Bons treinos." : "Gere um novo código para pagar. Nada foi cobrado."}</Text>
              </View>
            ) : (
              <>
                <View style={styles.codeBlock}>
                  {code ? <>
                    <Text style={styles.kvK} maxFontSizeMultiplier={1.3}>PIX COPIA E COLA</Text>
                    <Pressable onPress={copy} accessibilityRole="button" accessibilityLabel={copied ? "Código PIX copiado" : "Copiar código PIX"} accessibilityLiveRegion="polite" style={[styles.codeRow, copied && styles.codeRowCopied]}>
                      <Text style={styles.codeText} numberOfLines={1} ellipsizeMode="tail" maxFontSizeMultiplier={1.2}>{code}</Text>
                      <View style={[styles.copyButton, copied && { backgroundColor: ds.success }]}>
                        <Feather name={copied ? "check" : "copy"} size={16} color={copied ? ds.onSuccess : ds.onInverse} />
                        <Text style={[styles.copyText, copied && { color: ds.onSuccess }]} maxFontSizeMultiplier={1.2}>{copied ? "Copiado" : "Copiar"}</Text>
                      </View>
                    </Pressable>
                    {copyError ? <Text style={styles.copyError} accessibilityLiveRegion="polite">Não foi possível copiar. Toque para tentar novamente.</Text> : null}
                  </> : null}
                  <View style={styles.steps}>
                    {(code ? [
                      ["", "Copie", " o código."],
                      ["No app do banco, escolha ", "PIX copia e cola", " e cole."],
                      ["Volte aqui e toque em ", onCheck ? "Já paguei" : "Concluir", "."],
                    ] : [
                      ["Abra o app do banco e escolha ", "Pagar com PIX", "."],
                      ["Em outro aparelho, ", "escaneie o QR Code", " abaixo."],
                      ["Volte aqui e toque em ", onCheck ? "Já paguei" : "Concluir", "."],
                    ]).map(([before, bold, after], index) => (
                      <View key={index} style={styles.step}>
                        <View style={styles.stepN}><Text style={styles.stepNText} maxFontSizeMultiplier={1.2}>{index + 1}</Text></View>
                        <Text style={styles.stepText} maxFontSizeMultiplier={1.4}>{before}<Text style={styles.stepBold}>{bold}</Text>{after}</Text>
                      </View>
                    ))}
                  </View>
                </View>

                {qrBase64 ? (
                  <View style={styles.qrBlock}>
                    <View style={styles.orRow}>
                      <View style={styles.orLine} />
                      <Text style={styles.qrLabel} maxFontSizeMultiplier={1.3}>{code ? "OU ESCANEIE O QR CODE" : "ESCANEIE O QR CODE"}</Text>
                      <View style={styles.orLine} />
                    </View>
                    <View style={styles.qr}>
                      <Image source={{ uri: uriDoQrPix(qrBase64) }} style={{ width: qrSize, height: qrSize }} resizeMode="contain" accessibilityLabel="QR Code do PIX" />
                    </View>
                    <Text style={styles.qrHint} maxFontSizeMultiplier={1.3}>Use a câmera do app do banco em outro celular.</Text>
                  </View>
                ) : null}
                {notice ? <View style={styles.notice} accessibilityLiveRegion="polite"><Feather name="info" size={16} color={ds.inkMuted} /><Text style={styles.noticeText}>{notice}</Text></View> : null}
              </>
            )}
          </ScrollView>

          <View style={[styles.actions, { paddingBottom: space[3] + insets.bottom }]}>
            {st === "paid" ? (
              <Button size="lg" block onPress={onDone ?? onClose}>{doneLabel}</Button>
            ) : st === "expired" ? (
              <Button size="lg" block icon={onRenew ? "refresh-cw" : undefined} onPress={onRenew ?? onClose}>{onRenew ? "Gerar novo código" : "Fechar"}</Button>
            ) : <>
              <Button size="lg" block icon={st === "checking" ? undefined : "check"} loading={st === "checking"} textStyle={styles.checkText} onPress={onCheck ?? onClose}>
                {st === "checking" ? "Verificando pagamento…" : onCheck ? "Já paguei" : "Concluir"}
              </Button>
              {ticketUrl && onOpenBank ? (
                <Pressable onPress={onOpenBank} accessibilityRole="link" style={styles.bankLink}>
                  <Text style={styles.bankLinkText} maxFontSizeMultiplier={1.3}>Abrir o app do banco</Text>
                </Pressable>
              ) : null}
            </>}
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  scrim: { flex: 1, backgroundColor: ds.scrim, justifyContent: "flex-end" },
  sheet: { width: "100%", maxWidth: 560, alignSelf: "center", backgroundColor: ds.surface, borderTopLeftRadius: 24, borderTopRightRadius: 24, overflow: "hidden" },
  bar: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 8, paddingLeft: space[4], paddingRight: space[2], paddingTop: space[3], paddingBottom: space[1], borderBottomWidth: 1, borderBottomColor: ds.line },
  grabber: { position: "absolute", top: 6, left: "50%", marginLeft: -20, width: 40, height: 4, borderRadius: radius.full, backgroundColor: ds.line },
  sheetTitle: { flex: 1, fontSize: 18, lineHeight: 24, fontWeight: "900", color: ds.ink },
  scroll: { flexShrink: 1, flexGrow: 0 },
  scrollContent: { paddingBottom: 8 },
  head: { alignItems: "center", paddingTop: 16, paddingBottom: 12, paddingHorizontal: 16, borderBottomWidth: 1, borderBottomColor: ds.line, gap: 2 },
  kvK: { fontSize: 11, lineHeight: 14, fontWeight: "800", letterSpacing: 0.7, color: ds.inkMuted },
  amount: { alignSelf: "stretch", fontSize: 30, lineHeight: 36, fontWeight: "900", letterSpacing: -0.6, color: ds.ink, fontVariant: ["tabular-nums"], textAlign: "center" },
  meta: { ...type.caption, color: ds.inkMuted, textAlign: "center" },
  expiration: { flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 4, marginTop: 2 },
  expirationText: { fontSize: 12, lineHeight: 16, fontWeight: "700", color: ds.inkMuted, fontVariant: ["tabular-nums"] },
  codeBlock: { paddingHorizontal: 16, paddingVertical: 12, gap: 8, borderBottomWidth: 1, borderBottomColor: ds.line },
  codeRow: { minHeight: 48, flexDirection: "row", alignItems: "center", gap: 8, borderWidth: 1.5, borderColor: ds.line, borderRadius: 12, paddingLeft: 12, paddingRight: 4, paddingVertical: 4, backgroundColor: ds.surfaceSunken },
  codeRowCopied: { borderColor: ds.success, backgroundColor: ds.successSoft },
  codeText: { flex: 1, minWidth: 0, fontFamily: Platform.OS === "ios" ? "Menlo" : "monospace", fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.ink },
  copyButton: { minHeight: 38, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 6, paddingHorizontal: 12, paddingVertical: 6, borderRadius: 9, backgroundColor: ds.inverse },
  copyText: { fontSize: 14, fontWeight: "800", color: ds.onInverse },
  copyError: { fontSize: 12, lineHeight: 16, color: ds.danger },
  steps: { gap: 6, marginTop: 4 },
  step: { flexDirection: "row", gap: 8, alignItems: "flex-start" },
  stepN: { width: 20, height: 20, borderRadius: 10, backgroundColor: ds.inverse, alignItems: "center", justifyContent: "center", marginTop: 1 },
  stepNText: { fontSize: 12, fontWeight: "900", color: ds.onInverse },
  stepText: { flex: 1, fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.inkMuted },
  stepBold: { color: ds.ink, fontWeight: "800" },
  qrBlock: { alignItems: "center", paddingHorizontal: 16, paddingVertical: 12, gap: 10 },
  orRow: { alignSelf: "stretch", flexDirection: "row", alignItems: "center", gap: 12 },
  orLine: { flex: 1, height: 1, backgroundColor: ds.line },
  qrLabel: { flexShrink: 1, fontSize: 11, lineHeight: 14, fontWeight: "800", letterSpacing: 0.7, color: ds.inkMuted, textAlign: "center" },
  qr: { padding: 10, borderRadius: 12, borderWidth: 1, borderColor: ds.line, backgroundColor: "#ffffff" },
  qrHint: { fontSize: 12, lineHeight: 16, fontWeight: "600", color: ds.inkMuted, textAlign: "center" },
  actions: { paddingHorizontal: 16, paddingTop: 12, gap: 4, borderTopWidth: 1, borderTopColor: ds.line, backgroundColor: ds.surface },
  checkText: { flexShrink: 1, fontSize: 16 },
  bankLink: { alignSelf: "center", minHeight: 30, justifyContent: "center", paddingVertical: 4, paddingHorizontal: 8 },
  bankLinkText: { fontSize: 14, lineHeight: 20, fontWeight: "800", color: ds.brandInk, textDecorationLine: "underline", textAlign: "center" },
  notice: { flexDirection: "row", alignItems: "flex-start", gap: 6, paddingHorizontal: 16, paddingVertical: 8 },
  noticeText: { ...type.caption, color: ds.inkMuted, flex: 1 },
  done: { alignItems: "center", gap: 8, paddingVertical: 32, paddingHorizontal: 24 },
  doneMark: { width: 72, height: 72, borderRadius: 36, alignItems: "center", justifyContent: "center", marginBottom: 8 },
  doneTitle: { fontSize: 22, lineHeight: 28, fontWeight: "900", color: ds.ink, textAlign: "center" },
  doneText: { fontSize: 15, lineHeight: 22, color: ds.inkMuted, textAlign: "center" },
});
