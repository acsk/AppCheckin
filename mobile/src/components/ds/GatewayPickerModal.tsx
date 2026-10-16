import { Feather } from "@expo/vector-icons";
import React, { useEffect, useState } from "react";
import {
  Modal,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { createPortal } from "react-dom";
import type { PixGateway } from "@/src/utils/pagamentoPix";
import { ds, space } from "./tokens";

type Props = {
  visible: boolean;
  gateways: PixGateway[];
  onSelect: (id: string) => void;
  onCancel: () => void;
};

export default function GatewayPickerModal({
  visible,
  gateways,
  onSelect,
  onCancel,
}: Props) {
  const [backdropPronto, setBackdropPronto] = useState(false);

  useEffect(() => {
    if (!visible) {
      setBackdropPronto(false);
      return;
    }
    const timer = setTimeout(() => setBackdropPronto(true), 450);
    return () => clearTimeout(timer);
  }, [visible]);

  if (!visible) {
    return null;
  }

  const fecharFundo = () => {
    if (backdropPronto) onCancel();
  };

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
        onPress={fecharFundo}
        accessibilityLabel="Fechar escolha de pagamento"
      />
      <View style={styles.card} accessibilityViewIsModal>
        <Text style={styles.eyebrow}>Pagamento PIX</Text>
        <Text style={styles.title}>Como deseja pagar?</Text>
          <Text style={styles.subtitle}>
            {gateways.some((gateway) => gateway.environment === "sandbox")
              ? "Sandbox só aparece para Admin. Aluno vê apenas produção."
              : "Escolha Mercado Pago ou Asaas."}
          </Text>
        <View style={styles.list}>
          {gateways.map((gateway) => (
            <TouchableOpacity
              key={gateway.id}
              style={styles.option}
              onPress={() => onSelect(gateway.id)}
              activeOpacity={0.8}
            >
              <View style={styles.optionIcon}>
                <Feather name="credit-card" size={18} color={ds.brandInk} />
              </View>
              <View style={styles.optionText}>
                <Text style={styles.optionTitle}>{gateway.nome}</Text>
                <Text style={styles.optionHint}>
                  {gateway.environment === "sandbox"
                    ? "Ambiente de testes"
                    : "Produção"}
                </Text>
              </View>
              <Feather name="chevron-right" size={18} color={ds.inkMuted} />
            </TouchableOpacity>
          ))}
        </View>
        <TouchableOpacity style={styles.cancel} onPress={onCancel}>
          <Text style={styles.cancelText}>Cancelar</Text>
        </TouchableOpacity>
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
      onRequestClose={onCancel}
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
    marginBottom: space[2],
  },
  list: {
    gap: space[2],
  },
  option: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    padding: space[4],
    borderRadius: 16,
    borderWidth: 1,
    borderColor: ds.line,
    backgroundColor: ds.surfaceSunken,
  },
  optionIcon: {
    width: 40,
    height: 40,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: ds.brandSoft,
  },
  optionText: {
    flex: 1,
  },
  optionTitle: {
    fontSize: 16,
    fontWeight: "700",
    color: ds.ink,
  },
  optionHint: {
    marginTop: 2,
    fontSize: 12,
    color: ds.inkMuted,
  },
  cancel: {
    alignItems: "center",
    paddingVertical: space[3],
  },
  cancelText: {
    fontSize: 15,
    fontWeight: "600",
    color: ds.inkMuted,
  },
});
