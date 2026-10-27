import AppHeader from "@/src/components/ds/AppHeader";
import Badge from "@/src/components/ds/Badge";
import ChipFilter from "@/src/components/ds/ChipFilter";
import { ds, radius, space, type } from "@/src/components/ds/tokens";
import { useRefreshOnFocus } from "@/src/hooks/useRefreshOnFocus";
import { atualizarResumoComunicacoes, chamarComunicacoes, textoPendencias, useResumoComunicacoes } from "@/src/services/comunicacoes";
import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import React, { useCallback, useEffect, useState } from "react";
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

/** Central de Comunicação do aluno (sino do cabeçalho): avisos, comunicados, mensagens e enquetes. */

type Item = {
  id: number;
  tipo: string;
  tipo_nome: string;
  titulo: string;
  descricao: string | null;
  classificacao: string;
  obrigatoria: boolean;
  status: string;
  inicio_em: string | null;
  lida: boolean;
  concluida: boolean;
  pendente: boolean;
};

const FILTROS = [
  { id: "todas", label: "Todas" },
  { id: "nao_lidas", label: "Não lidas" },
  { id: "obrigatorias", label: "Obrigatórias" },
  { id: "enquetes", label: "Enquetes" },
];
const ICONE: Record<string, React.ComponentProps<typeof Feather>["name"]> = { notificacao: "bell", comunicado: "volume-2", mensagem: "message-square", enquete: "bar-chart-2" };
const COR: Record<string, string> = { info: ds.info, alerta: ds.warning, sucesso: ds.success, urgente: ds.danger };
const dataBr = (v: string | null) => (v ? v.slice(0, 10).split("-").reverse().join("/") : "");

export default function Comunicacoes() {
  const router = useRouter();
  const resumo = useResumoComunicacoes();
  const [filtro, setFiltro] = useState("todas");
  const [itens, setItens] = useState<Item[] | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [atualizando, setAtualizando] = useState(false);

  const carregar = useCallback(async () => {
    setErro(null);
    try {
      const [d] = await Promise.all([chamarComunicacoes("GET", `?filtro=${filtro}`), atualizarResumoComunicacoes()]);
      setItens(d?.comunicacoes || []);
    } catch (e: any) {
      setErro(e?.message || "Não foi possível carregar.");
    }
  }, [filtro]);
  useEffect(() => { setItens(null); void carregar(); }, [carregar]);
  useRefreshOnFocus(() => carregar());

  const pend = resumo.total_pendencias;

  return (
    <SafeAreaView style={styles.container} edges={["left", "right"]}>
      <AppHeader title="Comunicação" eyebrow={resumo.nao_lidas ? `${resumo.nao_lidas} não lida${resumo.nao_lidas === 1 ? "" : "s"}` : "Avisos da academia"}
        onBack={() => (router.canGoBack() ? router.back() : router.replace("/(tabs)/account"))} />
      <ScrollView contentContainerStyle={styles.scroll}
        refreshControl={<RefreshControl refreshing={atualizando} onRefresh={async () => { setAtualizando(true); await carregar(); setAtualizando(false); }} />}>
        {pend ? (
          <View style={styles.pend} accessibilityRole="summary">
            <View style={styles.pendTopo}>
              <Feather name="lock" size={16} color={ds.warning} />
              <Text style={styles.pendTitulo}>{textoPendencias(pend)}</Text>
            </View>
            <Text style={styles.pendTexto}>O check-in fica indisponível até você concluir {pend === 1 ? "essa pendência" : "todas"}.</Text>
            {resumo.pendencias.map((p) => (
              <Pressable key={p.id} style={styles.pendItem} accessibilityRole="button" onPress={() => router.push(`/comunicacao/${p.id}`)}>
                <Feather name={p.acao_exigida === "responder" ? "bar-chart-2" : "file-text"} size={16} color={ds.ink} />
                <Text style={styles.pendItemTexto} numberOfLines={1}>{p.titulo}</Text>
                <Text style={styles.pendAcao}>{p.acao_exigida === "responder" ? "Responder" : "Confirmar"}</Text>
                <Feather name="chevron-right" size={16} color={ds.inkMuted} />
              </Pressable>
            ))}
          </View>
        ) : null}

        <ChipFilter label="Filtrar" options={FILTROS.map((f) => ({ ...f, count: f.id === "nao_lidas" ? resumo.nao_lidas || null : f.id === "obrigatorias" ? pend || null : null }))} value={filtro} onChange={setFiltro} />

        {itens === null && !erro ? <ActivityIndicator color={ds.brand} style={{ marginTop: space[6] }} /> : null}
        {erro ? <Text style={styles.vazio}>{erro}</Text> : null}
        {itens && !itens.length ? (
          <View style={styles.vazioBox}>
            <Feather name="bell-off" size={22} color={ds.inkMuted} />
            <Text style={styles.vazio}>{filtro === "todas" ? "Nenhuma comunicação por aqui." : "Nada neste filtro."}</Text>
          </View>
        ) : null}
        <View style={{ gap: space[2] }}>
          {(itens || []).map((c) => (
            <Pressable key={c.id} style={[styles.item, !c.lida && styles.itemNova]} accessibilityRole="button"
              accessibilityLabel={`${c.tipo_nome}: ${c.titulo}${c.lida ? "" : ", não lida"}${c.pendente ? ", pendente" : ""}`}
              onPress={() => router.push(`/comunicacao/${c.id}`)}>
              <View style={[styles.icone, { backgroundColor: ds.surfaceSunken }]}>
                <Feather name={ICONE[c.tipo] || "bell"} size={18} color={COR[c.classificacao] || ds.info} />
              </View>
              <View style={{ flex: 1, minWidth: 0, gap: 2 }}>
                <Text style={[styles.titulo, !c.lida && { fontWeight: "900" }]} numberOfLines={2}>{c.titulo}</Text>
                {c.descricao ? <Text style={styles.sub} numberOfLines={2}>{c.descricao}</Text> : null}
                <View style={styles.badges}>
                  <Text style={styles.sub}>{c.tipo_nome} · {dataBr(c.inicio_em)}</Text>
                  {c.pendente ? <Badge tone="warning" icon="lock">Pendente</Badge> : c.obrigatoria && c.concluida ? <Badge tone="success" icon="check">Concluída</Badge> : null}
                  {c.status === "encerrada" ? <Badge tone="neutral">Encerrada</Badge> : null}
                </View>
              </View>
              {!c.lida ? <View style={styles.ponto} accessibilityElementsHidden /> : null}
            </Pressable>
          ))}
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: ds.ground },
  scroll: { padding: space[4], gap: space[4], paddingBottom: space[8] },
  pend: { gap: space[2], padding: space[4], borderRadius: radius.lg, backgroundColor: ds.warningSoft, borderWidth: 1, borderColor: ds.warning },
  pendTopo: { flexDirection: "row", alignItems: "center", gap: space[2] },
  pendTitulo: { flex: 1, ...type.heading, fontSize: 15, color: ds.warning },
  pendTexto: { ...type.caption, color: ds.ink },
  pendItem: { flexDirection: "row", alignItems: "center", gap: space[2], minHeight: 44, paddingHorizontal: space[3], borderRadius: radius.md, backgroundColor: ds.surface },
  pendItemTexto: { flex: 1, ...type.caption, fontSize: 14, color: ds.ink },
  pendAcao: { ...type.caption, color: ds.brandInk, fontWeight: "800" },
  item: { flexDirection: "row", alignItems: "flex-start", gap: space[3], padding: space[4], borderRadius: radius.lg, backgroundColor: ds.surface, borderWidth: 1, borderColor: ds.line },
  itemNova: { borderColor: ds.brand },
  icone: { width: 36, height: 36, borderRadius: 11, alignItems: "center", justifyContent: "center" },
  titulo: { ...type.heading, fontSize: 15, color: ds.ink },
  sub: { ...type.caption, color: ds.inkMuted },
  badges: { flexDirection: "row", flexWrap: "wrap", alignItems: "center", gap: space[2], marginTop: 2 },
  ponto: { width: 10, height: 10, borderRadius: 5, backgroundColor: ds.brand, marginTop: 4 },
  vazioBox: { alignItems: "center", gap: space[2], padding: space[6] },
  vazio: { ...type.body, color: ds.inkMuted, textAlign: "center" },
});
