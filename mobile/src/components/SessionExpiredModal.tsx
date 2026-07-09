import { colors } from "@/src/theme/colors";
import { MaterialCommunityIcons } from "@expo/vector-icons";
import React from "react";
import {
  Modal,
  Pressable,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";

type Props = {
  visible: boolean;
  message?: string;
  onConfirm: () => void;
};

export default function SessionExpiredModal({
  visible,
  message = "Sua sessão expirou. Por favor, faça login novamente.",
  onConfirm,
}: Props) {
  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onConfirm}
    >
      <Pressable style={styles.overlay} onPress={onConfirm}>
        <Pressable style={styles.card} onPress={(e) => e.stopPropagation()}>
          <View style={styles.iconCircle}>
            <MaterialCommunityIcons
              name="lock-alert-outline"
              size={44}
              color={colors.primary}
            />
          </View>

          <Text style={styles.title}>Sessão expirada</Text>
          <Text style={styles.message}>{message}</Text>

          <TouchableOpacity
            style={styles.button}
            onPress={onConfirm}
            activeOpacity={0.85}
          >
            <Text style={styles.buttonText}>Fazer login</Text>
          </TouchableOpacity>
        </Pressable>
      </Pressable>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.55)",
    justifyContent: "center",
    alignItems: "center",
    padding: 24,
  },
  card: {
    width: "100%",
    maxWidth: 340,
    backgroundColor: "#fff",
    borderRadius: 24,
    padding: 28,
    alignItems: "center",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.15,
    shadowRadius: 24,
    elevation: 8,
  },
  iconCircle: {
    width: 88,
    height: 88,
    borderRadius: 44,
    backgroundColor: "#FFF7ED",
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 16,
  },
  title: {
    fontSize: 22,
    fontWeight: "800",
    color: colors.text,
    textAlign: "center",
    marginBottom: 12,
  },
  message: {
    fontSize: 15,
    lineHeight: 22,
    color: colors.textSecondary,
    textAlign: "center",
    marginBottom: 24,
  },
  button: {
    backgroundColor: colors.primary,
    paddingVertical: 14,
    paddingHorizontal: 32,
    borderRadius: 14,
    minWidth: 180,
    alignItems: "center",
  },
  buttonText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "700",
  },
});
