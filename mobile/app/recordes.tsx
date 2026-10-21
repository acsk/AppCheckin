import { useProtectedRoute } from "@/hooks/useProtectedRoute";
import AppHeader from "@/src/components/ds/AppHeader";
import Button from "@/src/components/ds/Button";
import ChipFilter from "@/src/components/ds/ChipFilter";
import HeaderTabs from "@/src/components/ds/HeaderTabs";
import Podium from "@/src/components/ds/Podium";
import RankingRow from "@/src/components/ds/RankingRow";
import RecordHero from "@/src/components/ds/RecordHero";
import RecordRow from "@/src/components/ds/RecordRow";
import Section from "@/src/components/ds/Section";
import Segmented from "@/src/components/ds/Segmented";
import Modal from "@/src/components/ds/Modal";
import { ds, space } from "@/src/components/ds/tokens";
import { useRefreshOnFocus } from "@/src/hooks/useRefreshOnFocus";
import { mobileService } from "@/src/services/mobileService";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import {
  diasDesde,
  fmtData,
  fmtDiferenca,
  fmtMarca,
  fotoRecordeUrl,
  lerProva,
  textoMelhora,
  recordeErro,
  type Prova,
  type RankingItem,
  type RecordesResumo,
  type Recordista,
} from "@/src/utils/recordes";
import { Feather } from "@expo/vector-icons";
import { useFocusEffect } from "@react-navigation/native";
import { useRouter } from "expo-router";
import React, { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

type Aba = "meus" | "escola" | "ranking";
type Categoria = "geral" | "masculino" | "feminino";

const ABAS = [
  { id: "meus", label: "Meus" },
  { id: "escola", label: "Escola" },
  { id: "ranking", label: "Ranking" },
];
const CATEGORIAS = [
  { id: "geral", label: "Geral" },
  { id: "masculino", label: "Masculino" },
  { id: "feminino", label: "Feminino" },
];
/** Marca registrada há poucos dias e sem anterior para comparar ganha o selo "Novo". */
const DIAS_NOVO = 30;

const msgErro = recordeErro;

export default function RecordesScreen() {
  const { isAuthorized } = useProtectedRoute();
  const router = useRouter();
  const [aba, setAba] = useState<Aba>("meus");
  const [estilo, setEstilo] = useState("todos");
  const [categoria, setCategoria] = useState<Categoria>("geral");
  const [resumo, setResumo] = useState<RecordesResumo | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const [registrar, setRegistrar] = useState(false);
  const [provaRegistrar, setProvaRegistrar] = useState("");
  const request = useRef(0);

  const [provaRanking, setProvaRanking] = useState<number | null>(null);
  const [ranking, setRanking] = useState<RankingItem[] | null>(null);
  const [rankingErro, setRankingErro] = useState<string | null>(null);
  const [rankingRetry, setRankingRetry] = useState(0);
  const [rankingLoading, setRankingLoading] = useState(false);

  const carregar = useCallback(async (silencioso = false) => {
    const version = ++request.current;
    if (!silencioso) setLoading(true);
    try {
      const r = await mobileService.getRecordes();
      if (version !== request.current) return;
      setResumo(r);
      setErro(null);
    } catch (e) {
      console.error("Erro ao carregar recordes:", e);
      if (version === request.current) setErro(msgErro(e));
    } finally {
      if (version === request.current) { setLoading(false); setRefreshing(false); }
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      if (isAuthorized) void carregar();
      return () => { request.current++; };
    }, [carregar, isAuthorized]),
  );
  useRefreshOnFocus(() => { if (isAuthorized) return carregar(true); });

  const provas = useMemo<Prova[]>(() => (resumo?.definicoes || []).map(lerProva), [resumo]);
  const porId = useMemo(() => new Map(provas.map((p) => [p.def.id, p])), [provas]);
  const grupos = useMemo(() => {
    const vistos: string[] = [];
    provas.forEach((p) => {
      if (!vistos.includes(p.grupo)) vistos.push(p.grupo);
    });
    return vistos;
  }, [provas]);
  const opcoesEstilo = useMemo(
    () => (grupos.length > 1 ? [{ id: "todos", label: "Todos" }, ...grupos.map((g) => ({ id: g, label: g }))] : []),
    [grupos],
  );
  const gruposVisiveis = estilo === "todos" || !grupos.includes(estilo) ? grupos : [estilo];

  // Ranking: a prova escolhida, ou a primeira com marcas.
  const provaAtual = provaRanking && porId.has(provaRanking) ? provaRanking : (resumo?.escola.geral[0]?.definicao_id ?? provas[0]?.def.id ?? null);

  useEffect(() => {
    if (aba !== "ranking" || !provaAtual) return;
    let ativo = true;
    setRankingLoading(true);
    setRanking(null);
    setRankingErro(null);
    mobileService
      .getRankingRecorde(provaAtual, categoria === "geral" ? null : categoria)
      .then((r: { ranking: RankingItem[] }) => ativo && setRanking(r.ranking || []))
      .catch((e: unknown) => { console.error("Erro ao carregar ranking:", e); if (ativo) setRankingErro(msgErro(e)); })
      .finally(() => ativo && setRankingLoading(false));
    return () => {
      ativo = false;
    };
  }, [aba, provaAtual, categoria, resumo, rankingRetry]);

  const abrirRanking = (defId: number) => {
    setProvaRanking(defId);
    setAba("ranking");
  };

  const eyebrow = useMemo(() => {
    const mods = Array.from(new Set(provas.map((p) => p.def.modalidade_nome).filter(Boolean)));
    return mods.length === 1 ? String(mods[0]) : "Suas melhores marcas";
  }, [provas]);

  const renderMeus = () => {
    if (!resumo) return null;
    const minhas = new Map(resumo.meus.map((m) => [m.definicao_id, m]));
    // Herói: a melhor marca pessoal mais recente.
    const heroi = resumo.meus
      .filter((m) => m.valor != null && m.data)
      .sort((a, b) => String(b.data).localeCompare(String(a.data)))[0];
    const provaHeroi = heroi ? porId.get(heroi.definicao_id) : null;

    if (!resumo.aluno) {
      return <Aviso icon="user-x" titulo="Sem cadastro de aluno" texto="Seus recordes aparecem aqui quando você tiver cadastro de aluno nesta academia." />;
    }

    return (
      <>
        {heroi && provaHeroi ? (
          <RecordHero
            event={provaHeroi.rotulo}
            time={fmtMarca(heroi.valor, provaHeroi.def)}
            date={fmtData(heroi.data)}
            tag={heroi.anterior != null ? "Novo recorde" : "Melhor marca"}
            delta={heroi.anterior != null ? `${fmtDiferenca(heroi.valor!, heroi.anterior, provaHeroi.def)} ${textoMelhora(provaHeroi.def)}` : null}
            note={heroi.posicao ? `${heroi.posicao}º na escola` : null}
            history={heroi.historico}
            lowerIsBetter={provaHeroi.def.direcao === "menor_melhor"}
            onPress={() => router.push({ pathname: "/recorde-prova", params: { id: provaHeroi.def.id } })}
          />
        ) : null}
        {opcoesEstilo.length ? <ChipFilter label="Estilo" options={opcoesEstilo} value={estilo} onChange={setEstilo} /> : null}
        {gruposVisiveis.map((g) => (
          <Section key={g} title={g}>
            {provas
              .filter((p) => p.grupo === g)
              .map((p) => {
                const m = minhas.get(p.def.id);
                const tem = m?.valor != null;
                const novo = tem && m!.anterior == null && diasDesde(m!.data) <= DIAS_NOVO;
                return (
                  <RecordRow
                    key={p.def.id}
                    distance={p.distancia}
                    unit={p.unidadeDist}
                    time={fmtMarca(m?.valor, p.def)}
                    detail={[p.distancia ? null : p.estilo, fmtData(m?.data), m?.posicao ? `${m.posicao}º lugar` : null].filter(Boolean).join(" · ")}
                    delta={tem && m!.anterior != null ? fmtDiferenca(m!.valor!, m!.anterior, p.def) : null}
                    deltaUp={p.def.direcao === "maior_melhor"}
                    badge={novo ? "Novo" : null}
                    empty={!tem}
                    emptyText={p.distancia ? undefined : `${p.estilo} · ainda sem marca`}
                    onPress={() => router.push({ pathname: "/recorde-prova", params: { id: p.def.id } })}
                  />
                );
              })}
          </Section>
        ))}
      </>
    );
  };

  const renderEscola = () => {
    if (!resumo) return null;
    const lista: Recordista[] = resumo.escola[categoria] || [];
    const porDef = new Map(lista.map((r) => [r.definicao_id, r]));
    const comMarca = gruposVisiveis.filter((g) => provas.some((p) => p.grupo === g && porDef.has(p.def.id)));
    return (
      <>
        <Segmented size="sm" label="Categoria" options={CATEGORIAS} value={categoria} onChange={(id) => setCategoria(id as Categoria)} />
        {opcoesEstilo.length ? <ChipFilter label="Estilo" options={opcoesEstilo} value={estilo} onChange={setEstilo} /> : null}
        {comMarca.length ? (
          comMarca.map((g) => (
            <Section key={g} title={g}>
              {provas
                .filter((p) => p.grupo === g && porDef.has(p.def.id))
                .map((p) => {
                  const r = porDef.get(p.def.id)!;
                  return (
                    <RecordRow
                      key={p.def.id}
                      distance={p.distancia}
                      unit={p.unidadeDist}
                      holder={r.nome}
                      self={r.self}
                      time={fmtMarca(r.valor, p.def)}
                      detail={[p.distancia ? null : p.estilo, fmtData(r.data)].filter(Boolean).join(" · ")}
                      onPress={() => abrirRanking(p.def.id)}
                    />
                  );
                })}
            </Section>
          ))
        ) : (
          <Aviso
            icon="award"
            titulo="Nenhum recorde por aqui"
            texto={categoria === "geral" ? "Quando a academia registrar marcas, o recordista de cada prova aparece aqui." : "Ainda não há marcas de alunos desta categoria."}
          />
        )}
      </>
    );
  };

  const renderRanking = () => {
    const prova = provaAtual ? porId.get(provaAtual) : null;
    if (!prova) return null;
    const lista = ranking || [];
    const lider = lista[0];
    const top = lista.filter((r) => r.posicao <= 3);
    const resto = lista.filter((r) => r.posicao > 3);
    return (
      <>
        <ChipFilter label="Prova" options={provas.map((p) => ({ id: String(p.def.id), label: p.rotulo }))} value={String(prova.def.id)} onChange={(id) => setProvaRanking(Number(id))} />
        <Segmented size="sm" label="Categoria" options={CATEGORIAS} value={categoria} onChange={(id) => setCategoria(id as Categoria)} />
        {rankingLoading && !ranking ? (
          <ActivityIndicator color={ds.brand} style={{ marginTop: space[6] }} />
        ) : rankingErro ? (
          <View><Aviso icon="alert-circle" titulo="Não foi possível carregar" texto={rankingErro} /><Button variant="secondary" onPress={() => setRankingRetry(value => value + 1)}>Tentar de novo</Button></View>
        ) : !lista.length ? (
          <Aviso icon="award" titulo="Ninguém nesta prova ainda" texto="O ranking aparece quando os alunos tiverem marcas registradas." />
        ) : (
          <>
            <Podium items={top.map((r) => ({ name: r.nome, value: fmtMarca(r.valor, prova.def), self: r.self, avatarUri: fotoRecordeUrl(r.foto_caminho, getApiUrlRuntime()) }))} />
            {resto.length ? (
              <Section title="Classificação" aside="Melhor marca de cada aluno">
                {resto.map((r) => (
                  <RankingRow
                    key={r.aluno_id}
                    position={r.posicao}
                    name={r.nome}
                    avatarUri={fotoRecordeUrl(r.foto_caminho, getApiUrlRuntime())}
                    value={fmtMarca(r.valor, prova.def)}
                    gap={lider ? `${prova.def.direcao === "menor_melhor" ? "+" : "−"}${fmtDiferenca(r.valor, lider.valor, prova.def)}` : null}
                    self={r.self}
                  />
                ))}
              </Section>
            ) : null}
          </>
        )}
      </>
    );
  };

  const corpo = () => {
    if (!isAuthorized || (loading && !resumo)) {
      return (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={ds.brand} />
          <Text style={styles.centerText}>Carregando recordes…</Text>
        </View>
      );
    }
    if (erro && !resumo) {
      return (
        <View style={styles.center}>
          <Aviso icon="alert-circle" titulo="Não foi possível carregar" texto={erro} />
          <Button variant="secondary" icon="refresh-cw" onPress={() => carregar()}>
            Tentar de novo
          </Button>
        </View>
      );
    }
    if (!provas.length) {
      return (
        <View style={styles.center}>
          <Aviso icon="award" titulo="Sem provas de recorde" texto="A academia ainda não cadastrou provas. Quando cadastrar, suas marcas aparecem aqui." />
        </View>
      );
    }
    return (
      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); void carregar(true); }} tintColor={ds.brand} />}
      >
        {erro ? <View><Text accessibilityRole="alert" style={{ color: ds.danger }}>{erro}</Text><Button variant="secondary" onPress={() => carregar(true)}>Tentar de novo</Button></View> : null}
        {aba === "meus" ? renderMeus() : aba === "escola" ? renderEscola() : renderRanking()}
      </ScrollView>
    );
  };

  return (
    <SafeAreaView style={styles.container} edges={["left", "right"]}>
      <AppHeader
        title="Recordes"
        eyebrow={eyebrow}
        onBack={() => (router.canGoBack() ? router.back() : router.replace("/(tabs)/account"))}
        actions={resumo?.aluno && provas.length ? [{ icon: "plus", label: "Registrar marca", disabled: !isAuthorized || loading || !!erro, onPress: () => { setProvaRegistrar(String(provas[0].def.id)); setRegistrar(true); } }] : []}
        bottom={<HeaderTabs label="Recordes" options={ABAS} value={aba} onChange={(id) => setAba(id as Aba)} />}
      />
      {corpo()}
      {registrar ? <Modal visible title="Registrar marca" icon="award" description="Escolha a prova para registrar sua marca." onClose={() => setRegistrar(false)}
        primary={{ label: "Continuar", onPress: () => { if (provaRegistrar) { setRegistrar(false); router.push({ pathname: "/recorde-prova", params: { id: provaRegistrar, registrar: "1" } }); } } }}>
        <ChipFilter label="Prova" options={provas.map(p => ({ id: String(p.def.id), label: p.rotulo }))} value={provaRegistrar} onChange={setProvaRegistrar} />
      </Modal> : null}
    </SafeAreaView>
  );
}

function Aviso({ icon, titulo, texto }: { icon: React.ComponentProps<typeof Feather>["name"]; titulo: string; texto: string }) {
  return (
    <View style={styles.aviso}>
      <View style={styles.avisoIcon}>
        <Feather name={icon} size={22} color={ds.inkMuted} />
      </View>
      <Text style={styles.avisoTitulo}>{titulo}</Text>
      <Text style={styles.avisoTexto}>{texto}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: ds.ground },
  scroll: { padding: space[4], paddingBottom: space[8], gap: space[5] },
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: space[6], gap: space[4] },
  centerText: { fontSize: 14, fontWeight: "600", color: ds.inkMuted },
  aviso: { alignItems: "center", gap: space[2], paddingVertical: space[6], paddingHorizontal: space[4] },
  avisoIcon: { width: 48, height: 48, borderRadius: 24, alignItems: "center", justifyContent: "center", backgroundColor: ds.surfaceSunken },
  avisoTitulo: { fontSize: 17, lineHeight: 22, fontWeight: "800", color: ds.ink, textAlign: "center" },
  avisoTexto: { fontSize: 14, lineHeight: 20, fontWeight: "500", color: ds.inkMuted, textAlign: "center" },
});
