import api from "@/src/services/api";
import { authService } from "@/src/services/authService";
import { ds } from "@/src/components/ds/tokens";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import React, { useEffect, useState } from "react";
import { Platform, Pressable, StyleSheet, Text, View } from "react-native";

export const ACESSO_ALUNO_KEY = "@appcheckin:acesso_aluno";

type Acesso = { id: number; expira_em: string; nome: string; academia: string };

/** Faixa do "ver app como aluno" (aberto pelo painel): avisa que é só visualização e encerra o acesso. */
export default function AcessoAlunoBanner() {
  const [acesso, setAcesso] = useState<Acesso | null>(null);
  const [saindo, setSaindo] = useState(false);

  useEffect(() => {
    AsyncStorage.getItem(ACESSO_ALUNO_KEY)
      .then((v) => setAcesso(v ? (JSON.parse(v) as Acesso) : null))
      .catch(() => setAcesso(null));
  }, []);

  if (!acesso) return null;

  const sair = async () => {
    setSaindo(true);
    try {
      await api.post("/suporte/aluno/encerrar");
    } catch {
      // Já expirado ou encerrado: só limpa a sessão local
    }
    await authService.logout();
    if (Platform.OS === "web") {
      window.close();
      window.location.replace("/");
    }
  };

  const ate = acesso.expira_em ? acesso.expira_em.slice(11, 16) : "";
  return (
    <View style={styles.faixa} accessibilityRole="alert">
      <Feather name="eye" size={16} color={ds.onBrand} />
      <Text style={styles.texto} numberOfLines={2}>
        Visualizando como <Text style={styles.forte}>{acesso.nome}</Text>
        {acesso.academia ? ` · ${acesso.academia}` : ""} · só leitura{ate ? ` · até ${ate}` : ""}
      </Text>
      <Pressable style={styles.sair} onPress={() => void sair()} disabled={saindo} accessibilityRole="button">
        <Text style={styles.sairTexto}>{saindo ? "Saindo…" : "Sair"}</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  faixa: { flexDirection: "row", alignItems: "center", gap: 8, paddingHorizontal: 16, paddingVertical: 8, backgroundColor: ds.brandRed },
  texto: { flex: 1, color: ds.onBrand, fontSize: 13 },
  forte: { fontWeight: "800" },
  sair: { paddingHorizontal: 12, paddingVertical: 6, borderRadius: 999, backgroundColor: "rgba(255,255,255,0.22)" },
  sairTexto: { color: ds.onBrand, fontWeight: "800", fontSize: 13 },
});
