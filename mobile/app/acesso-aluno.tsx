import { ACESSO_ALUNO_KEY } from "@/src/components/AcessoAlunoBanner";
import { authService } from "@/src/services/authService";
import { ds } from "@/src/components/ds/tokens";
import AsyncStorage from "@react-native-async-storage/async-storage";
import React, { useEffect, useState } from "react";
import { ActivityIndicator, Platform, StyleSheet, Text, View } from "react-native";

type Sessao = {
  token: string;
  user: Record<string, unknown> & { nome?: string };
  tenants: { tenant: { id: number; nome?: string; slug?: string } }[];
  acesso: { id: number; expira_em: string };
};

/** O painel manda a sessão no fragmento (#d=base64url do JSON): não passa pelo servidor nem pelos logs. */
function lerSessao(): Sessao | null {
  try {
    const d = new URLSearchParams(window.location.hash.slice(1)).get("d");
    if (!d) return null;
    const json = decodeURIComponent(escape(atob(d.replace(/-/g, "+").replace(/_/g, "/"))));
    const s = JSON.parse(json) as Sessao;
    return s?.token && s?.tenants?.[0]?.tenant?.id ? s : null;
  } catch {
    return null;
  }
}

/**
 * "Ver app como aluno" (só app web): troca a sessão deste navegador pela do aluno, em modo
 * visualização, e recarrega o app. A faixa AcessoAlunoBanner mostra o modo e o botão Sair.
 */
export default function AcessoAlunoScreen() {
  const [erro, setErro] = useState("");

  useEffect(() => {
    if (Platform.OS !== "web") {
      setErro("Abra pelo painel, no navegador.");
      return;
    }
    const s = lerSessao();
    window.history.replaceState(null, "", window.location.pathname);
    if (!s) {
      setErro("Link de acesso inválido. Abra de novo pelo painel.");
      return;
    }
    void (async () => {
      await authService.logout();
      const tenant = s.tenants[0].tenant;
      await AsyncStorage.multiSet([
        ["@appcheckin:token", s.token],
        ["@appcheckin:user", JSON.stringify(s.user)],
        ["@appcheckin:tenants", JSON.stringify(s.tenants)],
        ["@appcheckin:current_tenant", JSON.stringify(tenant)],
        ["@appcheckin:tenant_id", String(tenant.id)],
        ["@appcheckin:tenant_slug", tenant.slug || ""],
        ["@appcheckin:tenant_nome", tenant.nome || ""],
        [ACESSO_ALUNO_KEY, JSON.stringify({ id: s.acesso.id, expira_em: s.acesso.expira_em, nome: s.user.nome || "", academia: tenant.nome || "" })],
      ]);
      // Recarrega inteiro: nada da sessão anterior fica em memória
      window.location.replace("/");
    })();
  }, []);

  return (
    <View style={styles.root}>
      {erro ? <Text style={styles.texto}>{erro}</Text> : <><ActivityIndicator color={ds.brand} /><Text style={styles.texto}>Abrindo o app do aluno…</Text></>}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, alignItems: "center", justifyContent: "center", gap: 12, padding: 24, backgroundColor: ds.ground },
  texto: { color: ds.ink, fontSize: 15, textAlign: "center" },
});
