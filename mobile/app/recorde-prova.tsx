import { useProtectedRoute } from "@/hooks/useProtectedRoute";
import RecordeMarcaForm from "@/src/components/RecordeMarcaForm";
import AppHeader from "@/src/components/ds/AppHeader";
import Badge from "@/src/components/ds/Badge";
import Button from "@/src/components/ds/Button";
import EvolutionChart from "@/src/components/ds/EvolutionChart";
import Modal from "@/src/components/ds/Modal";
import Section from "@/src/components/ds/Section";
import Segmented from "@/src/components/ds/Segmented";
import { ds, space } from "@/src/components/ds/tokens";
import { mobileService } from "@/src/services/mobileService";
import { fmtData, fmtDiferenca, fmtMarca, fmtValorMetrica, marcasNoPeriodo, MESES, recordeErro, type Marca, type MarcaPayload, type MarcaSalva, type ProvaDetalhe } from "@/src/utils/recordes";
import { useFocusEffect } from "@react-navigation/native";
import { useLocalSearchParams, useRouter } from "expo-router";
import React, { useCallback, useRef, useState } from "react";
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

export default function RecordeProvaScreen() {
  const { isAuthorized } = useProtectedRoute();
  const router = useRouter();
  const params = useLocalSearchParams<{ id?: string; registrar?: string }>();
  const id = Number(params.id);
  const [detalhe, setDetalhe] = useState<ProvaDetalhe | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [periodo, setPeriodo] = useState<"6m" | "tudo">("tudo");
  const [form, setForm] = useState<{ marca?: Marca } | null>(null);
  const [excluir, setExcluir] = useState<Marca | null>(null);
  const [busy, setBusy] = useState(false);
  const [deleteError, setDeleteError] = useState("");
  const [conquista, setConquista] = useState<MarcaSalva | null>(null);
  const [notice, setNotice] = useState("");
  const request = useRef(0);
  const opened = useRef(false);
  const mutation = useRef(false);
  const load = useCallback(async () => {
    const version = ++request.current;
    setLoading(true); setError("");
    try {
      if (!Number.isSafeInteger(id) || id < 1) throw new Error("Selecione uma prova válida.");
      const result: ProvaDetalhe = await mobileService.getProvaRecorde(id);
      if (version !== request.current) return;
      setDetalhe(result);
      if (params.registrar === "1" && result.pode_registrar && !opened.current) {
        opened.current = true; setForm({});
      }
    } catch (err) {
      console.error("Erro ao carregar evolução:", err);
      if (version === request.current) setError(recordeErro(err));
    } finally { if (version === request.current) setLoading(false); }
  }, [id, params.registrar]);
  useFocusEffect(useCallback(() => {
    if (isAuthorized) void load();
    return () => { request.current++; };
  }, [isAuthorized, load]));
  const back = () => {
    if (mutation.current) return;
    if (router.canGoBack()) router.back();
    else router.replace("./recordes");
  };
  const save = async (payload: MarcaPayload) => {
    if (mutation.current) return;
    mutation.current = true;
    try {
      if (form?.marca) {
        await mobileService.atualizarMarcaRecorde(form.marca.id, payload);
        setNotice("Marca atualizada.");
      } else {
        const result: MarcaSalva = await mobileService.criarMarcaRecorde(payload);
        if (result.novo_recorde) setConquista(result);
        else setNotice("Marca registrada.");
      }
      setForm(null);
      await load();
    } finally { mutation.current = false; }
  };
  const remove = async () => {
    if (!excluir || mutation.current) return;
    mutation.current = true; setBusy(true); setDeleteError("");
    try {
      await mobileService.excluirMarcaRecorde(excluir.id);
      setExcluir(null); setNotice("Marca excluída."); await load();
    } catch (err) { console.error("Erro ao excluir marca:", err); setDeleteError(recordeErro(err)); }
    finally { mutation.current = false; setBusy(false); }
  };
  const marks = detalhe?.marcas || [];
  const chronological = marcasNoPeriodo(marks, "tudo");
  const shown = marcasNoPeriodo(marks, periodo);
  const best = marks.find(m => m.id === detalhe?.melhor_id);
  const first = chronological[0];
  return <SafeAreaView style={styles.container} edges={["left", "right", "bottom"]}>
    <AppHeader title={detalhe?.definicao.nome || "Evolução"} eyebrow={`Meus recordes${detalhe?.definicao.modalidade_nome ? ` · ${detalhe.definicao.modalidade_nome}` : ""}`} onBack={back} />
    {!isAuthorized || (loading && !detalhe) ? <ActivityIndicator style={styles.center} color={ds.brand} size="large" /> : <ScrollView contentContainerStyle={styles.scroll} refreshControl={<RefreshControl refreshing={loading} onRefresh={load} tintColor={ds.brand} />}>
      {error ? <View style={styles.message}><Text accessibilityRole="alert" style={styles.error}>{error}</Text><Button accessibilityLabel="Tentar novamente" variant="secondary" icon="refresh-cw" onPress={load}>Tentar novamente</Button></View> : null}
      {notice ? <Text accessibilityRole="alert" style={styles.notice}>{notice}</Text> : null}
      {detalhe ? <>
        <View style={styles.stats}>
          <Stat label="Recorde" value={fmtMarca(best?.valor, detalhe.definicao)} hint={fmtData(best?.data)} />
          <Stat label="Evolução" value={best && first ? fmtDiferenca(first.valor, best.valor, detalhe.definicao) : "—"} hint="desde a primeira marca" />
          <Stat label="Marcas" value={String(marks.length)} hint="registradas" />
        </View>
        <Section title="Evolução" aside={`${shown.length} marcas`}>
          <View style={styles.chart}>
            <Segmented label="Período" size="sm" options={[{ id: "6m", label: "6 meses" }, { id: "tudo", label: "Tudo" }]} value={periodo} onChange={value => setPeriodo(value === "6m" ? "6m" : "tudo")} />
            <EvolutionChart marcas={shown} definicao={detalhe.definicao} melhorId={detalhe.melhor_id} />
          </View>
        </Section>
        <Section title="Histórico" aside="Suas marcas nesta prova">
          {!marks.length ? <View style={styles.chart}><Text style={styles.hint}>Você ainda não tem marcas nesta prova. Registre a primeira para acompanhar sua evolução.</Text></View> : marks.map(m => {
            const metrics = detalhe.metricas.filter(metric => metric.id !== detalhe.definicao.metrica_principal_id && m.valores[String(metric.id)] != null);
            return <View key={m.id} style={styles.mark}>
              <View style={styles.markMain}>
                <View style={styles.date}><Text style={styles.day}>{m.data.slice(8, 10)}</Text><Text style={styles.month}>{MESES[Number(m.data.slice(5, 7)) - 1]} {m.data.slice(0, 4)}</Text></View>
                <View style={styles.markText}><Text style={styles.value}>{fmtMarca(m.valor, detalhe.definicao)}</Text><Text style={styles.hint}>{m.origem === "academia" ? "Registrada pela academia" : "Registrada por você"}</Text>
                  {metrics.map(metric => <Text key={metric.id} style={styles.hint}>{metric.nome}: {fmtValorMetrica(m.valores[String(metric.id)], metric)}</Text>)}
                  {m.observacoes ? <Text style={styles.hint}>{m.observacoes}</Text> : null}
                </View>
                {m.id === detalhe.melhor_id ? <Badge tone="brand">Recorde</Badge> : null}
              </View>
              {m.editavel ? <View style={styles.actions}>
                <Button size="sm" variant="ghost" icon="edit-2" accessibilityLabel={`Editar marca de ${fmtData(m.data)}`} onPress={() => { setNotice(""); setForm({ marca: m }); }}>Editar</Button>
                <Button size="sm" variant="danger" icon="trash-2" accessibilityLabel={`Excluir marca de ${fmtData(m.data)}`} onPress={() => { setDeleteError(""); setExcluir(m); }}>Excluir</Button>
              </View> : null}
            </View>;
          })}
        </Section>
        {!detalhe.pode_registrar ? <Text style={styles.hint}>Seu cadastro de aluno é necessário para registrar marcas.</Text> : null}
      </> : null}
    </ScrollView>}
    {detalhe?.pode_registrar && isAuthorized ? <View style={styles.sticky}><Button block size="lg" accessibilityLabel="Registrar marca" icon="plus" disabled={loading || !!error} onPress={() => { setNotice(""); setForm({}); }}>Registrar marca</Button></View> : null}
    {form && detalhe ? <RecordeMarcaForm detalhe={detalhe} marca={form.marca} onClose={() => setForm(null)} onSave={save} /> : null}
    {excluir && detalhe ? <Modal visible title="Excluir marca?" icon="trash-2" description={`${fmtMarca(excluir.valor, detalhe.definicao)} em ${fmtData(excluir.data)}. Esta ação não pode ser desfeita.`}
      onClose={() => { if (!mutation.current) setExcluir(null); }} primary={{ label: "Confirmar exclusão", icon: "trash-2", onPress: remove, loading: busy }}>
      <Text style={styles.hint}>Seu recorde e sua posição no ranking serão recalculados.</Text>
      {deleteError ? <Text accessibilityRole="alert" style={styles.error}>{deleteError}</Text> : null}
    </Modal> : null}
    {conquista && detalhe ? <Modal visible variant="dialog" title="Novo recorde" eyebrow={detalhe.definicao.nome} icon="award"
      description={conquista.melhor_anterior == null ? "Sua primeira marca nesta prova foi registrada!" : `${fmtDiferenca(conquista.valor!, conquista.melhor_anterior, detalhe.definicao)} de evolução em relação ao recorde anterior.`}
      onClose={() => setConquista(null)} primary={{ label: "Ver evolução", icon: "arrow-right", onPress: () => setConquista(null) }}>
      <View style={styles.stats}><Stat label="Antes" value={fmtMarca(conquista.melhor_anterior, detalhe.definicao)} /><Stat label="Agora" value={fmtMarca(conquista.valor, detalhe.definicao)} /></View>
    </Modal> : null}
  </SafeAreaView>;
}
function Stat({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return <View style={styles.stat}><Text style={styles.statLabel}>{label}</Text><Text style={styles.statValue} adjustsFontSizeToFit numberOfLines={1}>{value}</Text>{hint ? <Text style={styles.hint}>{hint}</Text> : null}</View>;
}
const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: ds.ground },
  center: { flex: 1 },
  scroll: { padding: space[4], gap: space[5], paddingBottom: space[6] },
  stats: { flexDirection: "row", borderRadius: 16, backgroundColor: ds.surface, borderWidth: 1, borderColor: ds.line, overflow: "hidden" },
  stat: { flex: 1, minWidth: 0, padding: space[3], gap: 4 },
  statLabel: { fontSize: 11, textTransform: "uppercase", fontWeight: "800", color: ds.inkMuted },
  statValue: { fontSize: 23, fontWeight: "900", color: ds.ink, fontVariant: ["tabular-nums"] },
  chart: { padding: space[4], gap: space[4] },
  hint: { fontSize: 13, lineHeight: 19, color: ds.inkMuted },
  mark: { borderTopWidth: 1, borderColor: ds.line, padding: space[4], gap: space[2] },
  markMain: { flexDirection: "row", alignItems: "center", gap: space[3] },
  date: { width: 60, alignItems: "center" },
  day: { fontSize: 24, fontWeight: "800", color: ds.ink },
  month: { fontSize: 11, color: ds.inkMuted, fontWeight: "700" },
  markText: { flex: 1, minWidth: 0, gap: 3 },
  value: { fontSize: 24, fontWeight: "900", color: ds.ink, fontVariant: ["tabular-nums"] },
  actions: { flexDirection: "row", justifyContent: "flex-end", gap: space[2] },
  sticky: { borderTopWidth: 1, borderColor: ds.line, backgroundColor: ds.surface, padding: space[4] },
  message: { gap: space[3], padding: space[3], backgroundColor: ds.dangerSoft, borderRadius: 12 },
  error: { color: ds.danger, fontSize: 14, lineHeight: 20, fontWeight: "600" },
  notice: { color: ds.success, backgroundColor: ds.successSoft, padding: space[3], borderRadius: 12, fontWeight: "700" },
});
