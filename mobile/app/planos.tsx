import { useProtectedRoute } from "@/hooks/useProtectedRoute";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { usuarioPodeVerSandboxPagamento } from "@/src/utils/pagamentoPix";
import { authService } from "@/src/services/authService";
import { handleUnauthorizedResponse } from "@/src/utils/authHelpers";
import { isSessionExpiredVisible } from "@/src/utils/sessionExpired";
import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useRouter } from "expo-router";
import { useFocusEffect } from "@react-navigation/native";
import React, { useCallback, useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import AppHeader from "@/src/components/ds/AppHeader";
import Button from "@/src/components/ds/Button";
import FreqGrid, { type FreqOption } from "@/src/components/ds/FreqGrid";
import Modal from "@/src/components/ds/Modal";
import PackageCard from "@/src/components/ds/PackageCard";
import Section from "@/src/components/ds/Section";
import Stepper from "@/src/components/ds/Stepper";
import TypeCard from "@/src/components/ds/TypeCard";
import { ds, radius, space, type } from "@/src/components/ds/tokens";
import { usePacotePagamento } from "@/src/hooks/usePacotePagamento";
import { useRefreshOnFocus } from "@/src/hooks/useRefreshOnFocus";

// Planos: Tipo (Individual × Pacotes) → Plano (frequência ou pacote) → Pagamento.
// Individual segue para /plano-detalhes (ciclo e pagamento); pacote revisa e paga aqui mesmo.

interface Plan {
  id: number;
  nome: string;
  descricao: string;
  valor: number;
  valor_formatado: string;
  duracao_dias: number;
  checkins_semanais: number;
  modalidade: { id: number; nome: string };
  is_plano_atual?: boolean;
  pode_migrar?: boolean;
  pode_renovar?: boolean;
  pode_pagar?: boolean;
  label?: string | null;
}

type PacoteVitrine = {
  id: number;
  nome: string;
  descricao: string | null;
  valor_total: number;
  vagas: number;
  plano_nome: string | null;
  modalidade_nome: string | null;
  ciclo_meses: number | null;
  liberado_para_voce: boolean;
  valida_ate: string | null;
  contrato_pendente_id: number | null;
};

type Etapa = "tipo" | "individual" | "pacotes";

const STEPS = ["Tipo", "Plano", "Pagamento"];
const CICLO: Record<number, string> = { 1: "Mensal", 2: "Bimestral", 3: "Trimestral", 4: "Quadrimestral", 6: "Semestral", 12: "Anual" };

const isDevPlanName = (nome?: string | null) => String(nome || "").trim().toLowerCase().includes("dev");

const brl = (v: number) =>
  new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(Number(v) || 0);
/** Preço curto: "R$ 80" quando inteiro. */
const brlCurto = (v: number) => {
  const inteiro = Math.abs(v - Math.round(v)) < 0.005;
  return new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: inteiro ? 0 : 2,
    maximumFractionDigits: 2,
  }).format(Number(v) || 0);
};
// No Android o fetch (OkHttp) pode devolver um GET em cache: pede sempre a resposta nova.
const SEM_CACHE = { "Cache-Control": "no-cache, no-store", Pragma: "no-cache" };
const semCacheUrl = (url: string) => `${url}${url.includes("?") ? "&" : "?"}_=${Date.now()}`;
const dataBr = (v: string) => v.slice(0, 10).split("-").reverse().join("/");

const valorPlano = (p: Plan) => Number(p.valor) || 0;
const isAvulso = (p: Plan) => !p.checkins_semanais && Number(p.duracao_dias) <= 1;
const semLimite = (p: Plan) => !p.checkins_semanais || p.checkins_semanais >= 99;
const porAula = (p: Plan) => (semLimite(p) ? null : valorPlano(p) / (p.checkins_semanais * 4));
const freqTexto = (p: Plan) => (semLimite(p) ? "Livre" : `${p.checkins_semanais}x por semana`);
const porPessoaMes = (p: PacoteVitrine) => p.valor_total / Math.max(1, p.vagas) / Math.max(1, p.ciclo_meses ?? 1);

export default function PlanosScreen() {
  const router = useRouter();
  useProtectedRoute();

  const [hasPermission, setHasPermission] = useState<boolean | null>(null);
  const [planos, setPlanos] = useState<Plan[]>([]);
  const [pacotes, setPacotes] = useState<PacoteVitrine[]>([]);
  const [creditoDisponivel, setCreditoDisponivel] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [apiUrl, setApiUrl] = useState("");
  // null = automática: Tipo quando há os dois, senão direto na lista que existe
  const [etapaEscolhida, setEtapaEscolhida] = useState<Etapa | null>(null);
  const [planoId, setPlanoId] = useState<number | null>(null);
  const [pacoteId, setPacoteId] = useState<number | null>(null);
  const [aviso, setAviso] = useState<{ title: string; message: string; tone: "danger" | "success" | "brand" } | null>(null);

  const mostrarAviso = (title: string, message: string, tone: "danger" | "success" | "brand" = "danger") =>
    setAviso({ title, message, tone });

  useEffect(() => {
    const checkPermission = async () => {
      try {
        const token = await AsyncStorage.getItem("@appcheckin:token");
        const user = token ? await authService.getCurrentUser() : null;
        if (!token || !user) {
          if (!isSessionExpiredVisible()) {
            if (token) await AsyncStorage.removeItem("@appcheckin:token");
            router.replace("/(auth)/login");
          }
          setHasPermission(false);
          return;
        }
        setHasPermission(true);
      } catch {
        setHasPermission(false);
      }
    };
    void checkPermission();
  }, [router]);

  const fetchPacotes = useCallback(async (baseUrl: string, token: string) => {
    try {
      const response = await fetch(semCacheUrl(`${baseUrl}/mobile/pacotes/disponiveis`), {
        cache: "no-store",
        headers: { Authorization: `Bearer ${token}`, ...SEM_CACHE },
      });
      const json: any = await response.json().catch(() => ({}));
      setPacotes(response.ok && Array.isArray(json?.data?.pacotes) ? json.data.pacotes : []);
    } catch {
      setPacotes([]);
    }
  }, []);

  const fetchPlanos = useCallback(async (baseUrl: string, opts?: { silent?: boolean }) => {
    try {
      if (!opts?.silent) setLoading(true);
      setError(null);
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) throw new Error("Token não encontrado");

      const [response] = await Promise.all([
        fetch(semCacheUrl(`${baseUrl}/mobile/planos-disponiveis`), {
          cache: "no-store",
          headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json", ...SEM_CACHE },
        }),
        fetchPacotes(baseUrl, token),
      ]);
      if (!response.ok) {
        if (await handleUnauthorizedResponse(response)) return;
        throw new Error(`HTTP ${response.status}: ${await response.text()}`);
      }
      const data = await response.json();
      if (!data.success || !data.data?.planos) throw new Error(data.error || "Falha ao carregar planos");

      setCreditoDisponivel(Number(data.data.credito_disponivel || 0));
      const user = await authService.getCurrentUser();
      const admin = usuarioPodeVerSandboxPagamento(user);
      setPlanos(admin ? data.data.planos : data.data.planos.filter((p: Plan) => !isDevPlanName(p?.nome)));
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erro ao carregar planos");
    } finally {
      setLoading(false);
    }
  }, [fetchPacotes]);

  // Lê a API toda vez que a tela entra em foco (a aba fica montada no Android e não recarregava).
  // A primeira leitura mostra o carregando; as seguintes atualizam em silêncio.
  const jaCarregou = useRef(false);
  useFocusEffect(
    useCallback(() => {
      if (hasPermission !== true) return;
      const url = getApiUrlRuntime();
      setApiUrl(url);
      void fetchPlanos(url, { silent: jaCarregou.current });
      jaCarregou.current = true;
    }, [hasPermission, fetchPlanos]),
  );

  // Volta do segundo plano com a tela aberta
  useRefreshOnFocus(() => (apiUrl ? fetchPlanos(apiUrl, { silent: true }) : undefined));

  // Retorno do checkout (cartão) por deep link
  useEffect(() => {
    const handleDeepLink = ({ url }: { url: string }) => {
      if (!/pagamento\/(pendente|aprovado)|mobile\.appcheckin\.com\.br\/pagamento/.test(url)) return;
      const status = new URL(url).searchParams.get("collection_status");
      if (status === "approved") {
        mostrarAviso("Pagamento aprovado", "Seu pagamento foi aprovado. Sua matrícula está ativa.", "success");
        if (apiUrl) setTimeout(() => void fetchPlanos(apiUrl, { silent: true }), 2000);
      } else if (status === "pending") {
        mostrarAviso("Pagamento em análise", "Você recebe a confirmação em breve.", "brand");
      } else if (status === "rejected") {
        mostrarAviso("Pagamento recusado", "Tente de novo com outra forma de pagamento.");
      }
    };
    Linking.getInitialURL().then((url) => url && handleDeepLink({ url }));
    const sub = Linking.addEventListener("url", handleDeepLink);
    return () => sub.remove();
  }, [apiUrl, fetchPlanos]);

  const pagamentoPacote = usePacotePagamento({
    onPago: () => {
      if (apiUrl) void fetchPlanos(apiUrl, { silent: true });
    },
    onErro: (m) => mostrarAviso("Pacote", m),
  });

  // ---- Dados derivados ----
  const planoAtual = planos.find((p) => p.is_plano_atual) || null;
  const semanais = planos
    .filter((p) => !isAvulso(p))
    .sort((a, b) => (a.checkins_semanais || 999) - (b.checkins_semanais || 999) || valorPlano(a) - valorPlano(b));
  const avulsos = planos.filter((p) => isAvulso(p));
  const temIndividual = planos.length > 0;
  const temPacotes = pacotes.length > 0;
  const etapaPadrao: Etapa = temIndividual && temPacotes ? "tipo" : temPacotes ? "pacotes" : "individual";
  const etapa: Etapa = etapaEscolhida ?? etapaPadrao;
  const podeVoltarParaTipo = temIndividual && temPacotes && etapa !== "tipo";

  // Selo só para um fato verificável: menor preço por aula
  const idMenorPorAula =
    semanais.filter((p) => porAula(p) != null).length > 1
      ? semanais.filter((p) => porAula(p) != null).sort((a, b) => (porAula(a) as number) - (porAula(b) as number))[0]?.id
      : undefined;

  // Seleção padrão: o primeiro plano que não é o atual; o primeiro pacote
  const planoSel = semanais.find((p) => p.id === planoId) ?? semanais.find((p) => !p.is_plano_atual) ?? semanais[0] ?? null;
  const pacoteSel = pacotes.find((p) => p.id === pacoteId) ?? pacotes[0] ?? null;

  const modalidades = Array.from(new Set(planos.map((p) => p.modalidade?.nome).filter(Boolean) as string[]));

  const handleBack = () => {
    if (podeVoltarParaTipo) {
      setEtapaEscolhida("tipo");
      return;
    }
    router.replace("/(tabs)/checkin");
  };

  const abrirPlano = (p: Plan) => router.push({ pathname: "/plano-detalhes", params: { id: p.id } });

  const pagarPacote = (p: PacoteVitrine) => {
    const meses = p.ciclo_meses ?? 1;
    pagamentoPacote.iniciar({
      titulo: p.nome,
      valor: p.valor_total,
      detalhe: `${p.vagas} pessoas · ${CICLO[meses] ?? `${meses} meses`}`,
      contratoId: p.contrato_pendente_id,
      pacoteId: p.id,
    });
  };

  const titulo = etapa === "individual" ? "Individual" : etapa === "pacotes" ? "Pacotes" : "Planos";
  const header = (
    <AppHeader
      title={titulo}
      eyebrow={etapa === "tipo" ? modalidades.join(" · ") || undefined : ["Planos", modalidades.join(" · ")].filter(Boolean).join(" · ")}
      onBack={handleBack}
      actions={[{ icon: "file-text", label: "Minhas assinaturas", onPress: () => router.push("/minhas-assinaturas") }]}
      bottom={<Stepper tone="brand" steps={STEPS} current={etapa === "tipo" ? 0 : 1} />}
    />
  );

  if (hasPermission === null || loading) {
    return (
      <SafeAreaView style={styles.container} edges={["left", "right"]}>
        {header}
        <View style={styles.center}>
          <ActivityIndicator size="large" color={ds.brand} />
          <Text style={styles.muted}>Carregando planos…</Text>
        </View>
      </SafeAreaView>
    );
  }

  if (hasPermission === false) {
    return (
      <SafeAreaView style={styles.container} edges={["left", "right"]}>
        <AppHeader title="Planos" onBack={() => router.replace("/(tabs)/checkin")} />
        <View style={styles.center}>
          <Feather name="lock" size={48} color={ds.danger} />
          <Text style={styles.emptyTitle}>Acesso restrito</Text>
          <Text style={styles.muted}>Não foi possível validar sua sessão. Faça login novamente.</Text>
          <Button onPress={() => router.replace("/(auth)/login")}>Fazer login</Button>
        </View>
      </SafeAreaView>
    );
  }

  const creditoBanner =
    creditoDisponivel > 0 ? (
      <View style={styles.credito}>
        <Feather name="gift" size={18} color={ds.success} />
        <Text style={styles.creditoText}>
          Você tem {brl(creditoDisponivel)} de crédito. Ele é abatido automaticamente do valor do novo plano.
        </Text>
      </View>
    ) : null;

  // ---- Etapa 1 · Tipo ----
  const renderTipo = () => {
    const precos = semanais.filter((p) => !p.is_plano_atual).map(valorPlano).filter((v) => v > 0);
    const faixa = precos.length
      ? Math.min(...precos) === Math.max(...precos)
        ? `${brlCurto(Math.min(...precos))}/mês`
        : `${brlCurto(Math.min(...precos))} a ${brlCurto(Math.max(...precos))}/mês`
      : null;
    const menorPacote = pacotes.length ? Math.min(...pacotes.map(porPessoaMes)) : 0;
    return (
      <>
        {planoAtual ? (
          <View style={styles.atual}>
            <View style={{ flex: 1, minWidth: 0 }}>
              <Text style={styles.atualK}>Seu plano</Text>
              <Text style={styles.atualV} numberOfLines={1}>
                {`${freqTexto(planoAtual)} · ${brl(valorPlano(planoAtual))}/mês`}
              </Text>
              {planoAtual.label ? <Text style={styles.atualSub}>{planoAtual.label}</Text> : null}
            </View>
            <TouchableOpacity style={styles.atualBtn} onPress={() => abrirPlano(planoAtual)} accessibilityRole="button">
              <Text style={styles.atualBtnText}>{planoAtual.pode_renovar || planoAtual.pode_pagar ? "Renovar" : "Ver"}</Text>
            </TouchableOpacity>
          </View>
        ) : null}
        {creditoBanner}
        <View>
          <Text style={styles.lead}>Que tipo de plano?</Text>
          <Text style={styles.leadSub}>Depois você escolhe o plano e paga com PIX ou cartão.</Text>
        </View>
        <View style={styles.stack}>
          <TypeCard icon="user" title="Individual" description="Só para você." meta={faixa} onPress={() => setEtapaEscolhida("individual")} />
          <TypeCard
            icon="users"
            title="Pacotes"
            description="Família ou dependentes, num pagamento só."
            meta={menorPacote > 0 ? `a partir de ${brlCurto(menorPacote)} por pessoa/mês` : null}
            onPress={() => setEtapaEscolhida("pacotes")}
          />
        </View>
      </>
    );
  };

  // ---- Etapa 2 · Individual ----
  const opcaoFreq = (p: Plan): FreqOption => {
    const pa = porAula(p);
    return {
      id: p.id,
      freq: semLimite(p) ? "∞" : `${p.checkins_semanais}x`,
      unit: semLimite(p) ? "livre" : "por semana",
      price: brlCurto(valorPlano(p)),
      perClass: pa ? `≈ ${brl(pa)}` : null,
      tag: p.id === idMenorPorAula ? "Menor por aula" : null,
      current: !!p.is_plano_atual,
    };
  };
  const renderIndividual = () => {
    const porModalidade = modalidades.length > 1;
    const grupos = porModalidade
      ? modalidades.map((m) => ({ titulo: m, itens: semanais.filter((p) => p.modalidade?.nome === m) })).filter((g) => g.itens.length)
      : [{ titulo: "Quantas aulas por semana?", itens: semanais }];
    return (
      <>
        {etapaEscolhida == null ? creditoBanner : null}
        {grupos.map((g) => (
          <Section key={g.titulo} title={g.titulo} aside="Preço por mês" bare>
            <FreqGrid options={g.itens.map(opcaoFreq)} value={planoSel?.id ?? null} onChange={(id) => setPlanoId(Number(id))} />
          </Section>
        ))}
        {avulsos.map((p) => (
          <TouchableOpacity key={p.id} style={styles.aside} onPress={() => abrirPlano(p)} accessibilityRole="button">
            <Feather name="calendar" size={20} color={ds.ink} />
            <Text style={styles.asideText}>
              Pagar por aula? <Text style={styles.asideBold}>{`${p.nome} ${brlCurto(valorPlano(p))}`}</Text>
            </Text>
            <Feather name="chevron-right" size={18} color={ds.inkMuted} />
          </TouchableOpacity>
        ))}
      </>
    );
  };

  // ---- Etapa 2 · Pacotes ----
  const renderPacotes = () => (
    <>
      {etapaEscolhida == null ? creditoBanner : null}
      <Section title="Escolha o pacote" bare>
        <View style={styles.stack} accessibilityRole="radiogroup" accessibilityLabel="Pacotes">
          {pacotes.map((p) => {
            const meses = p.ciclo_meses ?? 1;
            return (
              <PackageCard
                key={p.id}
                name={p.nome}
                detail={[p.plano_nome, p.modalidade_nome, CICLO[meses] ?? `${meses} meses`].filter(Boolean).join(" · ")}
                perPerson={brl(porPessoaMes(p))}
                people={`${p.vagas} ${p.vagas === 1 ? "pessoa" : "pessoas"}`}
                total={meses === 1 ? `${brl(p.valor_total)}/mês` : `${brl(p.valor_total)} (${CICLO[meses] ?? `${meses} meses`})`}
                who={p.descricao}
                badge={p.contrato_pendente_id ? "Pagamento pendente" : p.liberado_para_voce ? "Liberado para você" : null}
                selected={pacoteSel?.id === p.id}
                onSelect={() => setPacoteId(p.id)}
              />
            );
          })}
        </View>
      </Section>
      <View style={styles.note}>
        <Feather name="info" size={18} color={ds.info} />
        <Text style={styles.noteText}>
          Você paga o pacote e a academia adiciona os outros participantes depois.
          {pacoteSel?.valida_ate ? ` Disponível até ${dataBr(pacoteSel.valida_ate)}.` : ""}
        </Text>
      </View>
    </>
  );

  // ---- Rodapé com resumo e "Continuar" ----
  const renderResumo = () => {
    if (etapa === "individual" && planoSel) {
      const atual = !!planoSel.is_plano_atual;
      const renovar = atual && !!(planoSel.pode_renovar || planoSel.pode_pagar);
      return (
        <View style={styles.summary}>
          <View style={styles.summaryText}>
            <Text style={styles.summaryLabel} numberOfLines={1}>
              {`${freqTexto(planoSel)} · ${atual ? "seu plano atual" : planoSel.modalidade?.nome || planoSel.nome}`}
            </Text>
            <Text style={styles.summaryTotal} numberOfLines={1}>{`${brl(valorPlano(planoSel))}/mês`}</Text>
          </View>
          <Button size="lg" variant={atual && !renovar ? "secondary" : "primary"} iconRight="arrow-right" onPress={() => abrirPlano(planoSel)}>
            {atual ? (renovar ? "Renovar" : "Meu plano") : planoSel.pode_migrar ? "Trocar" : "Continuar"}
          </Button>
        </View>
      );
    }
    if (etapa === "pacotes" && pacoteSel) {
      return (
        <View style={styles.summary}>
          <View style={styles.summaryText}>
            <Text style={styles.summaryLabel} numberOfLines={1}>{`${pacoteSel.nome} · ${pacoteSel.vagas} pessoas`}</Text>
            <Text style={styles.summaryTotal} numberOfLines={1}>{brl(pacoteSel.valor_total)}</Text>
          </View>
          <Button size="lg" iconRight="arrow-right" onPress={() => pagarPacote(pacoteSel)}>
            {pacoteSel.contrato_pendente_id ? "Pagar" : "Continuar"}
          </Button>
        </View>
      );
    }
    return null;
  };

  return (
    <SafeAreaView style={styles.container} edges={["left", "right"]}>
      {header}

      {error ? (
        <View style={styles.center}>
          <Feather name="alert-circle" size={48} color={ds.danger} />
          <Text style={styles.emptyTitle}>Erro ao carregar planos</Text>
          <Text style={styles.muted}>{error}</Text>
          <Button icon="refresh-cw" onPress={() => void fetchPlanos(apiUrl)}>Tentar novamente</Button>
          {error.toLowerCase().includes("tenant") ? (
            <Button variant="secondary" icon="log-in" onPress={() => router.replace("/(auth)/login")}>Fazer login</Button>
          ) : null}
        </View>
      ) : !temIndividual && !temPacotes ? (
        <View style={styles.center}>
          <Feather name="inbox" size={48} color={ds.lineStrong} />
          <Text style={styles.emptyTitle}>Nenhum plano disponível</Text>
          <Text style={styles.muted}>Não há planos disponíveis no momento.</Text>
        </View>
      ) : (
        <>
          <ScrollView contentContainerStyle={styles.scroll} showsVerticalScrollIndicator={false}>
            {etapa === "tipo" ? renderTipo() : etapa === "pacotes" ? renderPacotes() : renderIndividual()}
          </ScrollView>
          {renderResumo()}
        </>
      )}

      {pagamentoPacote.elemento}

      <Modal
        visible={!!aviso}
        variant="dialog"
        icon={aviso?.tone === "success" ? "check" : aviso?.tone === "danger" ? "alert-circle" : "clock"}
        title={aviso?.title ?? ""}
        description={aviso?.message}
        primary={{ label: "OK, entendi", onPress: () => setAviso(null) }}
        onClose={() => setAviso(null)}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: ds.ground },
  scroll: { padding: space[4], gap: space[5], paddingBottom: space[8] },
  stack: { gap: space[3] },
  center: { flex: 1, alignItems: "center", justifyContent: "center", gap: space[3], padding: space[6] },
  muted: { ...type.caption, color: ds.inkMuted, textAlign: "center" },
  emptyTitle: { ...type.heading, color: ds.ink },
  lead: { fontSize: 22, lineHeight: 28, fontWeight: "900", letterSpacing: -0.2, color: ds.ink },
  leadSub: { marginTop: 4, fontSize: 14, lineHeight: 20, fontWeight: "500", color: ds.inkMuted },
  atual: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    paddingVertical: space[3],
    paddingHorizontal: space[4],
    borderRadius: radius.lg,
    backgroundColor: "#171a21",
  },
  atualK: { fontSize: 11, lineHeight: 14, fontWeight: "800", letterSpacing: 0.88, textTransform: "uppercase", color: ds.brandYellow },
  atualV: { fontSize: 15, lineHeight: 20, fontWeight: "800", color: "#ffffff" },
  atualSub: { fontSize: 12, lineHeight: 16, fontWeight: "600", color: "#a3aab6" },
  atualBtn: { height: 34, paddingHorizontal: space[3], borderRadius: radius.full, backgroundColor: "#ffffff", justifyContent: "center" },
  atualBtnText: { fontSize: 13, fontWeight: "800", color: "#171a21" },
  credito: { flexDirection: "row", gap: space[2], alignItems: "center", padding: space[3], borderRadius: radius.md, backgroundColor: ds.successSoft },
  creditoText: { flex: 1, fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.ink },
  aside: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    paddingVertical: space[3],
    paddingHorizontal: space[4],
    borderRadius: 14,
    borderWidth: 1,
    borderColor: ds.line,
  },
  asideText: { flex: 1, fontSize: 14, lineHeight: 19, fontWeight: "600", color: ds.inkMuted },
  asideBold: { fontWeight: "800", color: ds.ink },
  note: { flexDirection: "row", gap: space[3], alignItems: "flex-start", paddingVertical: space[3], paddingHorizontal: space[4], borderRadius: radius.lg, backgroundColor: ds.infoSoft },
  noteText: { flex: 1, fontSize: 13, lineHeight: 18, fontWeight: "600", color: ds.ink },
  summary: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[3],
    paddingVertical: space[3],
    paddingHorizontal: space[4],
    backgroundColor: ds.surface,
    borderTopWidth: 1,
    borderTopColor: ds.line,
  },
  summaryText: { flex: 1, minWidth: 0 },
  summaryLabel: { fontSize: 12, lineHeight: 16, fontWeight: "700", color: ds.inkMuted },
  summaryTotal: { fontSize: 22, lineHeight: 26, fontWeight: "900", letterSpacing: -0.4, color: ds.ink, fontVariant: ["tabular-nums"] },
});
