import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { Feather } from "@expo/vector-icons";
import { useLocalSearchParams, useRouter } from "expo-router";
import React from "react";
import {
  Image,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { colors } from "../src/theme/colors";
import { normalizeUtf8 } from "../src/utils/utf8";

const getPhotoUrl = (foto?: string | null) => {
  if (!foto) return null;
  if (/^https?:\/\//i.test(foto)) return foto;
  return `${getApiUrlRuntime()}${foto}`;
};

export default function CheckinDetalhesScreen() {
  const router = useRouter();
  const params = useLocalSearchParams();

  const alunoNome = normalizeUtf8(String(params.alunoNome || "Aluno"));
  const turmaNome = normalizeUtf8(String(params.turmaNome || "Turma"));
  const horario = normalizeUtf8(String(params.horario || ""));
  const dataAulaRaw = String(params.dataAula || "");
  const presenteRaw = String(params.presente ?? "");
  const presente = presenteRaw === "true" ? true : presenteRaw === "false" ? false : null;
  const presencaConfirmadaEm = normalizeUtf8(
    String(params.presencaConfirmadaEm || ""),
  );
  const dataCheckin = normalizeUtf8(String(params.dataCheckin || ""));
  const foto = typeof params.foto === "string" ? params.foto : "";
  const photoUrl = getPhotoUrl(foto);

  return (
    <SafeAreaView style={styles.container} edges={["top"]}>
      <View style={styles.header}>
        <TouchableOpacity style={styles.backButton} onPress={() => router.back()}>
          <Feather name="arrow-left" size={20} color="#fff" />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Detalhes do Check-in</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.card}>
          <View style={styles.avatarWrap}>
            {photoUrl ? (
              <Image source={{ uri: photoUrl }} style={styles.avatar} />
            ) : (
              <Feather name="user" size={28} color="#9ca3af" />
            )}
          </View>
          <Text style={styles.name}>{alunoNome}</Text>
          <Text style={styles.subtitle}>{turmaNome}</Text>

          {!!horario && (
            <View style={styles.row}>
              <Feather name="clock" size={16} color={colors.textMuted} />
              <Text style={styles.rowText}>{horario}</Text>
            </View>
          )}

          {!!dataAulaRaw && (
            <View style={styles.row}>
              <Feather name="calendar" size={16} color={colors.textMuted} />
              <Text style={styles.rowText}>
                {new Date(dataAulaRaw).toLocaleDateString("pt-BR", {
                  weekday: "long",
                  day: "2-digit",
                  month: "long",
                  year: "numeric",
                })}
              </Text>
            </View>
          )}

          {!!dataCheckin && (
            <Text style={styles.confirmedText}>Check-in em {dataCheckin}</Text>
          )}

          <View style={styles.statusPill}>
            <Text style={styles.statusText}>
              {presente === null
                ? "Presença não confirmada"
                : presente
                  ? "Presente"
                  : "Falta"}
            </Text>
          </View>
          {!!presencaConfirmadaEm && (
            <Text style={styles.confirmedText}>
              Confirmado em {presencaConfirmadaEm}
            </Text>
          )}
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.backgroundSecondary,
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 14,
    backgroundColor: colors.primary,
  },
  backButton: {
    padding: 8,
    borderRadius: 10,
    backgroundColor: "rgba(255,255,255,0.2)",
  },
  headerTitle: {
    color: "#fff",
    fontSize: 18,
    fontWeight: "700",
  },
  content: {
    padding: 16,
  },
  card: {
    backgroundColor: "#fff",
    borderRadius: 18,
    padding: 20,
    alignItems: "center",
    gap: 10,
    borderWidth: 1,
    borderColor: "#eef2f7",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.06,
    shadowRadius: 10,
    elevation: 2,
  },
  avatarWrap: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: "#e5e7eb",
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  avatar: {
    width: "100%",
    height: "100%",
  },
  name: {
    fontSize: 20,
    fontWeight: "800",
    color: colors.text,
    textAlign: "center",
  },
  subtitle: {
    fontSize: 14,
    fontWeight: "600",
    color: colors.textSecondary,
    textAlign: "center",
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  rowText: {
    fontSize: 14,
    color: colors.textSecondary,
    fontWeight: "600",
  },
  statusPill: {
    marginTop: 8,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 999,
    backgroundColor: colors.primary + "15",
  },
  statusText: {
    color: colors.primary,
    fontWeight: "700",
    fontSize: 13,
  },
  confirmedText: {
    fontSize: 12,
    color: colors.textMuted,
  },
});
