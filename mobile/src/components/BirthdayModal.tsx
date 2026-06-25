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
  nome: string;
  idade?: number | null;
  onClose: () => void;
};

function primeiroNome(nome: string): string {
  const partes = nome.trim().split(/\s+/);
  return partes[0] || nome;
}

export default function BirthdayModal({
  visible,
  nome,
  idade,
  onClose,
}: Props) {
  const nomeCurto = primeiroNome(nome);

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onClose}
    >
      <Pressable style={styles.overlay} onPress={onClose}>
        <Pressable style={styles.card} onPress={(e) => e.stopPropagation()}>
          <View style={styles.iconCircle}>
            <MaterialCommunityIcons
              name="cake-variant"
              size={48}
              color={colors.primary}
            />
          </View>

          <Text style={styles.title}>Feliz Aniversário!</Text>
          <Text style={styles.subtitle}>
            Parabéns, {nomeCurto}!
            {typeof idade === "number" && idade > 0 ? ` 🎉 ${idade} anos` : ""}
          </Text>
          <Text style={styles.message}>
            A equipe deseja um dia incrível para você. Aproveite o treino de
            hoje com muita energia!
          </Text>

          <TouchableOpacity style={styles.button} onPress={onClose}>
            <Text style={styles.buttonText}>Obrigado!</Text>
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
    fontSize: 24,
    fontWeight: "800",
    color: colors.text,
    textAlign: "center",
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 18,
    fontWeight: "700",
    color: colors.primary,
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
    minWidth: 160,
    alignItems: "center",
  },
  buttonText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "700",
  },
});
