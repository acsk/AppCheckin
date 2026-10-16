import { Feather } from "@expo/vector-icons";
import React from "react";
import {
  Modal,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { createPortal } from "react-dom";
import { nomeProvedorPagamento } from "@/src/utils/metodosPagamento";
import Button from "./Button";
import { ds, radius, space } from "./tokens";

type Props = {
  visible: boolean;
  url: string | null;
  provider?: string | null;
  metodo?: string | null;
  onRedirect: () => void;
  onClose: () => void;
};

function rotuloMetodo(metodo?: string | null): string {
  const id = String(metodo || "").toLowerCase();
  if (id === "boleto") return "boleto";
  if (id === "pix") return "PIX";
  return "cartão";
}

export default function CheckoutRedirectModal({
  visible,
  url,
  provider,
  metodo,
  onRedirect,
  onClose,
}: Props) {
  if (!visible) {
    return null;
  }

  const nome = nomeProvedorPagamento(provider, url);
  const metodoLabel = rotuloMetodo(metodo);

  const conteudo = (
    <View
      style={[
        styles.overlay,
        Platform.OS === "web" ? styles.overlayWeb : null,
      ]}
      pointerEvents="box-none"
    >
      <Pressable
        style={StyleSheet.absoluteFill}
        onPress={onClose}
        accessibilityLabel="Fechar"
      />
      <View style={styles.card} accessibilityViewIsModal>
        <Text style={styles.eyebrow}>Pagamento</Text>
        <Text style={styles.title}>Continuar no {nome}</Text>
        <Text style={styles.subtitle}>
          O pagamento com {metodoLabel} será concluído no {nome}. Toque no botão
          para abrir o checkout.
        </Text>
        <View style={styles.providerCard}>
          <View style={styles.providerIcon}>
            <Feather name="credit-card" size={18} color={ds.brandInk} />
          </View>
          <View style={styles.providerText}>
            <Text style={styles.providerK}>Provedor</Text>
            <Text style={styles.providerV}>{nome}</Text>
          </View>
        </View>
        <Button
          block
          size="lg"
          icon="arrow-right"
          onPress={onRedirect}
          disabled={!url}
        >
          Ir para o {nome}
        </Button>
        <Button variant="ghost" block onPress={onClose}>
          Agora não
        </Button>
      </View>
    </View>
  );

  if (Platform.OS === "web" && typeof document !== "undefined") {
    return createPortal(conteudo, document.body);
  }

  return (
    <Modal
      visible
      transparent
      animationType="fade"
      presentationStyle="overFullScreen"
      onRequestClose={onClose}
    >
      {conteudo}
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: ds.scrim,
    justifyContent: "center",
    alignItems: "center",
    padding: space[5],
  },
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
  card: {
    width: "100%",
    maxWidth: 420,
    backgroundColor: ds.surface,
    borderRadius: 24,
    borderWidth: 1,
    borderColor: ds.line,
    padding: space[5],
    gap: space[3],
    zIndex: 2,
  },
  eyebrow: {
    fontSize: 11,
    fontWeight: "800",
    letterSpacing: 0.8,
    textTransform: "uppercase",
    color: ds.brandInk,
  },
  title: {
    fontSize: 22,
    fontWeight: "900",
    color: ds.ink,
  },
  subtitle: {
    fontSize: 14,
    lineHeight: 20,
    color: ds.inkMuted,
  },
  providerCard: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    padding: space[4],
    marginVertical: space[2],
    borderRadius: radius.md,
    backgroundColor: ds.surfaceSunken,
    borderWidth: 1,
    borderColor: ds.line,
  },
  providerIcon: {
    width: 40,
    height: 40,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: ds.brandSoft,
  },
  providerText: { flex: 1, gap: 2 },
  providerK: {
    fontSize: 11,
    fontWeight: "800",
    letterSpacing: 0.66,
    textTransform: "uppercase",
    color: ds.inkMuted,
  },
  providerV: { fontSize: 18, fontWeight: "800", color: ds.ink },
});
