import { ds } from "@/src/components/ds/tokens";
import { dependenteService, onTrocaDePerfil } from "@/src/services/dependenteService";
import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import React, { useEffect, useState } from "react";
import { Alert, Platform, Pressable, StyleSheet, Text, View } from "react-native";

type Perfil = { usuario_id: number; nome: string; responsavel_nome?: string; login_proprio?: boolean };

const primeiroNome = (n?: string) => {
  const p = String(n || "").trim().split(/\s+/)[0] || "";
  return p ? p.charAt(0).toUpperCase() + p.slice(1).toLowerCase() : "";
};

/** Faixa no topo enquanto o responsável usa o app no perfil de um dependente. */
export default function PerfilDependenteBanner() {
  const [perfil, setPerfil] = useState<Perfil | null>(null);
  const [voltando, setVoltando] = useState(false);
  const router = useRouter();

  useEffect(() => {
    void dependenteService.perfilAtual().then(setPerfil);
    return onTrocaDePerfil((p: Perfil | null) => setPerfil(p));
  }, []);

  if (!perfil) return null;

  // Login próprio da criança (PIN): não há perfil do responsável para voltar, só sair
  if (perfil.login_proprio) {
    return (
      <View style={styles.faixa} accessibilityRole="alert">
        <Feather name="user" size={16} color={ds.onInverse} />
        <Text style={styles.texto} numberOfLines={2}>
          Olá, <Text style={styles.forte}>{primeiroNome(perfil.nome)}</Text>
          {perfil.responsavel_nome ? ` · responsável: ${primeiroNome(perfil.responsavel_nome)}` : ""}
        </Text>
        <Pressable style={styles.voltar} onPress={() => router.push("/logout")} accessibilityRole="button">
          <Text style={styles.voltarTexto}>Sair</Text>
        </Pressable>
      </View>
    );
  }

  const voltar = async () => {
    setVoltando(true);
    try {
      await dependenteService.voltar();
    } catch (e: any) {
      const msg = e?.message || "Não foi possível voltar. Entre de novo.";
      if (Platform.OS === "web") window.alert(msg);
      else Alert.alert("Perfil", msg);
    } finally {
      setVoltando(false);
    }
  };

  return (
    <View style={styles.faixa} accessibilityRole="alert">
      <Feather name="users" size={16} color={ds.onInverse} />
      <Text style={styles.texto} numberOfLines={2}>
        Perfil de <Text style={styles.forte}>{primeiroNome(perfil.nome)}</Text> · check-ins e planos são dele
      </Text>
      <Pressable style={styles.voltar} onPress={() => void voltar()} disabled={voltando} accessibilityRole="button"
        accessibilityLabel={`Voltar para o perfil de ${primeiroNome(perfil.responsavel_nome) || "responsável"}`}>
        <Text style={styles.voltarTexto}>{voltando ? "Voltando…" : `Voltar${perfil.responsavel_nome ? ` para ${primeiroNome(perfil.responsavel_nome)}` : ""}`}</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  faixa: { flexDirection: "row", alignItems: "center", gap: 8, paddingHorizontal: 16, paddingVertical: 8, backgroundColor: ds.inverse },
  texto: { flex: 1, color: ds.onInverse, fontSize: 13 },
  forte: { fontWeight: "800" },
  voltar: { paddingHorizontal: 12, paddingVertical: 6, borderRadius: 999, backgroundColor: "rgba(255,255,255,0.18)" },
  voltarTexto: { color: ds.onInverse, fontWeight: "800", fontSize: 13 },
});
