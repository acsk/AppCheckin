import { Feather } from "@expo/vector-icons";
import React, { useEffect, useMemo, useState } from "react";
import {
  Image,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  useWindowDimensions,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Badge from "./Badge";
import Button from "./Button";
import { ds, radius, space, type } from "./tokens";

export type PixStatus = "waiting" | "checking" | "paid" | "expired";

function uriDoQrPix(qrBase64: string): string {
  const valor = qrBase64.trim().replace(/^data:image\\\/png;base64,/i, "data:image/png;base64,");
  if (valor.startsWith("data:")) {
    return valor;
  }
  return `data:image/png;base64,${valor}`;
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
  /** Mensagem de apoio (ex.: pagamento ainda pendente). */
  notice?: string | null;
  onClose: () => void;
  /** Copia o código; deve resolver true se copiou. */
  onCopy: (code: string) => Promise<boolean>;
  /** "Já paguei": consulta o pagamento. Sem ele, o botão principal só fecha. */
  onCheck?: () => void;
  onOpenBank?: () => void;
  onDone?: () => void;
  onRenew?: () => void;
  doneLabel?: string;
};

const fmtLeft = (sec: number) => {
  const s = Math.max(0, Math.floor(sec));
  const hh = Math.floor(s / 3600);
  const mm = Math.floor((s % 3600) / 60);
  const ss = s % 60;
  const z = (v: number) => (v < 10 ? "0" : "") + v;
  return (hh ? z(hh) + ":" : "") + z(mm) + ":" + z(ss);
};

const secondsUntil = (iso?: string | null) => {
  if (!iso) return null;
  const t = new Date(iso.replace(" ", "T")).getTime();
  if (Number.isNaN(t)) return null;
  return Math.round((t - Date.now()) / 1000);
};

// Pagamento via PIX como folha inferior, no modelo "TelasLoginPix" do design system:
// valor com prazo → QR + três passos → copia e cola → ações.
export default function PixPaymentSheet({
  visible,
  amount,
  description,
  code,
  qrBase64,
  expiresAt,
  ticketUrl,
  status = "waiting",
  notice,
  onClose,
  onCopy,
  onCheck,
  onOpenBank,
  onDone,
  onRenew,
  doneLabel = "Ver minha assinatura",
}: Props) {
  const insets = useSafeAreaInsets();
  const { width, height } = useWindowDimensions();
  const [copied, setCopied] = useState(false);
  const [left, setLeft] = useState<number | null>(() => secondsUntil(expiresAt));

  useEffect(() => {
    setLeft(secondsUntil(expiresAt));
    if (!expiresAt || status !== "waiting") return;
    const t = setInterval(() => setLeft(secondsUntil(expiresAt)), 1000);
    return () => clearInterval(t);
  }, [expiresAt, status, visible]);

  const st: PixStatus = status === "waiting" && left != null && left <= 0 ? "expired" : status;
  const qrSize = Math.min(152, Math.round(width * 0.36));
  const narrow = width < 360;

  const badge = useMemo(() => {
    if (st === "paid") return <Badge tone="success" icon="check">Pago</Badge>;
    if (st === "expired") return <Badge tone="danger" icon="x">Expirado</Badge>;
    if (left != null) {
      return (
        <Badge tone={left < 300 ? "danger" : "warning"} icon="clock">
          {`Expira em ${fmtLeft(left)}`}
        </Badge>
      );
    }
    return null;
  }, [st, left]);

  const copy = async () => {
    if (!code) return;
    const ok = await onCopy(code);
    if (ok) {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose}>
      <View style={styles.scrim}>
        <Pressable style={StyleSheet.absoluteFill} onPress={onClose} accessibilityLabel="Fechar" />
        <View
          style={[styles.sheet, { maxHeight: height * 0.94, paddingBottom: insets.bottom }]}
          accessibilityViewIsModal
          accessibilityLabel="Pagamento via PIX"
        >
          <View style={styles.bar}>
            <View style={styles.grabber} />
            <Text style={styles.sheetTitle}>{st === "paid" ? "Tudo certo" : "Pagamento via PIX"}</Text>
            <Button variant="ghost" icon="x" accessibilityLabel="Fechar" onPress={onClose} />
          </View>

          <ScrollView bounces={false}>
            {/* Valor */}
            <View style={styles.head}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text style={styles.kvK}>TOTAL A PAGAR</Text>
                <Text style={styles.amount}>{amount}</Text>
                {description ? <Text style={styles.meta}>{description}</Text> : null}
              </View>
              {badge}
            </View>

            {st === "paid" ? (
              <>
                <View style={styles.done}>
                  <View style={[styles.doneMark, { backgroundColor: ds.success }]}>
                    <Feather name="check" size={36} color={ds.onSuccess} />
                  </View>
                  <Text style={styles.doneTitle}>Pagamento confirmado</Text>
                  <Text style={styles.doneText}>Seu plano já está ativo. Bons treinos.</Text>
                </View>
                <View style={styles.actions}>
                  <Button size="lg" block onPress={onDone ?? onClose}>
                    {doneLabel}
                  </Button>
                </View>
              </>
            ) : st === "expired" ? (
              <>
                <View style={styles.done}>
                  <View style={[styles.doneMark, { backgroundColor: ds.dangerSoft }]}>
                    <Feather name="clock" size={32} color={ds.danger} />
                  </View>
                  <Text style={styles.doneTitle}>Código expirado</Text>
                  <Text style={styles.doneText}>Gere um novo código para pagar. Nada foi cobrado.</Text>
                </View>
                <View style={styles.actions}>
                  <Button size="lg" block icon="refresh" onPress={onRenew ?? onClose}>
                    {onRenew ? "Gerar novo código" : "Fechar"}
                  </Button>
                </View>
              </>
            ) : (
              <>
                {/* QR + passos */}
                <View style={[styles.qrRow, narrow && { flexDirection: "column", alignItems: "stretch" }]}>
                  {qrBase64 ? (
                    <View style={[styles.qr, { width: qrSize + 20, height: qrSize + 20 }, narrow && { alignSelf: "center" }]}>
                      <Image
                        source={{ uri: uriDoQrPix(qrBase64) }}
                        style={{ width: qrSize, height: qrSize }}
                        accessibilityLabel="QR Code do PIX"
                      />
                    </View>
                  ) : null}
                  <View style={styles.steps}>
                    {[
                      ["Abra o app do seu banco e escolha ", "Pagar com PIX", "."],
                      ["Escaneie o QR Code ou use o ", "copia e cola", "."],
                      ["Volte aqui e toque em ", onCheck ? "Já paguei" : "Concluir", "."],
                    ].map((s, i) => (
                      <View key={i} style={styles.step}>
                        <View style={styles.stepN}>
                          <Text style={styles.stepNText}>{i + 1}</Text>
                        </View>
                        <Text style={styles.stepText}>
                          {s[0]}
                          <Text style={styles.stepBold}>{s[1]}</Text>
                          {s[2]}
                        </Text>
                      </View>
                    ))}
                  </View>
                </View>

                {/* Copia e cola */}
                {code ? (
                  <View style={styles.codeBlock}>
                    <Text style={styles.kvK}>PIX COPIA E COLA</Text>
                    <View style={styles.codeRow}>
                      <Text style={styles.codeText} numberOfLines={1} ellipsizeMode="middle" selectable>
                        {code}
                      </Text>
                      <Button
                        variant={copied ? "secondary" : "dark"}
                        size="sm"
                        icon={copied ? "check" : "copy"}
                        onPress={copy}
                        accessibilityLiveRegion="polite"
                      >
                        {copied ? "Copiado" : "Copiar"}
                      </Button>
                    </View>
                  </View>
                ) : null}

                {/* Ações */}
                <View style={styles.actions}>
                  {onCheck ? (
                    <Button
                      size="lg"
                      block
                      icon={st === "checking" ? undefined : "check"}
                      loading={st === "checking"}
                      onPress={onCheck}
                    >
                      {st === "checking" ? "Verificando pagamento…" : "Já paguei"}
                    </Button>
                  ) : (
                    <Button size="lg" block icon="check" onPress={onClose}>
                      Concluir
                    </Button>
                  )}
                  {ticketUrl && onOpenBank ? (
                    <Button variant="secondary" block icon="external-link" onPress={onOpenBank}>
                      Abrir no app do banco
                    </Button>
                  ) : null}
                  {notice ? (
                    <View style={styles.notice} accessibilityLiveRegion="polite">
                      <Feather name="info" size={16} color={ds.inkMuted} />
                      <Text style={styles.noticeText}>{notice}</Text>
                    </View>
                  ) : null}
                </View>
              </>
            )}
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  scrim: { flex: 1, backgroundColor: ds.scrim, justifyContent: "flex-end" },
  sheet: {
    width: "100%",
    maxWidth: 560,
    alignSelf: "center",
    backgroundColor: ds.surface,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    overflow: "hidden",
  },
  bar: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingLeft: space[4],
    paddingRight: space[2],
    paddingTop: space[3],
    paddingBottom: space[1],
    borderBottomWidth: 1,
    borderBottomColor: ds.line,
  },
  grabber: {
    position: "absolute",
    top: 6,
    left: "50%",
    marginLeft: -20,
    width: 40,
    height: 4,
    borderRadius: radius.full,
    backgroundColor: ds.line,
  },
  sheetTitle: { fontSize: 18, lineHeight: 24, fontWeight: "900", color: ds.ink },
  head: {
    flexDirection: "row",
    alignItems: "flex-start",
    justifyContent: "space-between",
    gap: space[3],
    padding: space[4],
    borderBottomWidth: 1,
    borderBottomColor: ds.line,
  },
  kvK: { fontSize: 11, lineHeight: 14, fontWeight: "800", letterSpacing: 0.66, color: ds.inkMuted },
  amount: {
    fontSize: 34,
    lineHeight: 38,
    fontWeight: "900",
    letterSpacing: -1,
    color: ds.ink,
    fontVariant: ["tabular-nums"],
    marginVertical: 2,
  },
  meta: { ...type.caption, color: ds.inkMuted },
  qrRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[4],
    padding: space[4],
    borderBottomWidth: 1,
    borderBottomColor: ds.line,
  },
  qr: {
    padding: 10,
    borderRadius: radius.md,
    backgroundColor: "#ffffff",
    borderWidth: 1,
    borderColor: ds.line,
    alignItems: "center",
    justifyContent: "center",
  },
  steps: { flex: 1, gap: space[3] },
  step: { flexDirection: "row", gap: space[2], alignItems: "flex-start" },
  stepN: {
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: ds.inverse,
    alignItems: "center",
    justifyContent: "center",
  },
  stepNText: { fontSize: 12, fontWeight: "900", color: ds.onInverse },
  stepText: { flex: 1, fontSize: 14, lineHeight: 19, fontWeight: "500", color: ds.inkMuted },
  stepBold: { color: ds.ink, fontWeight: "800" },
  codeBlock: {
    padding: space[4],
    gap: space[2],
    borderBottomWidth: 1,
    borderBottomColor: ds.line,
  },
  codeRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[2],
    paddingVertical: 6,
    paddingRight: 6,
    paddingLeft: space[3],
    borderRadius: radius.md,
    backgroundColor: ds.surfaceSunken,
    borderWidth: 1,
    borderColor: ds.line,
  },
  codeText: { flex: 1, minWidth: 0, fontSize: 14, fontWeight: "600", color: ds.ink },
  actions: { padding: space[4], gap: space[2] },
  notice: { flexDirection: "row", alignItems: "flex-start", gap: 6, marginTop: space[1] },
  noticeText: { ...type.caption, color: ds.inkMuted, flex: 1 },
  done: {
    alignItems: "center",
    gap: space[2],
    paddingVertical: space[8],
    paddingHorizontal: space[6],
    borderBottomWidth: 1,
    borderBottomColor: ds.line,
  },
  doneMark: {
    width: 72,
    height: 72,
    borderRadius: 36,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: space[2],
  },
  doneTitle: { fontSize: 22, lineHeight: 28, fontWeight: "900", color: ds.ink, textAlign: "center" },
  doneText: { fontSize: 15, lineHeight: 22, color: ds.inkMuted, textAlign: "center" },
});
