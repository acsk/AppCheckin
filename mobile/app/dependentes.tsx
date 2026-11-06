import { useProtectedRoute } from "@/hooks/useProtectedRoute";
import AppHeader from "@/src/components/ds/AppHeader";
import Button from "@/src/components/ds/Button";
import Section, { cardStyle } from "@/src/components/ds/Section";
import TextField from "@/src/components/ds/TextField";
import { ds, radius, space, type } from "@/src/components/ds/tokens";
import { dependenteService } from "@/src/services/dependenteService";
import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import React, { useCallback, useEffect, useRef, useState } from "react";
import { ActivityIndicator, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

type Dependente = {
  usuario_id: number; nome: string; idade: number | null; parentesco: string | null; na_academia: boolean; tem_pin: boolean;
  data_nascimento: string | null; sexo: string | null; cpf: string | null; email: string | null;
};

const SEXO = [
  { value: "feminino", label: "Feminino" },
  { value: "masculino", label: "Masculino" },
  { value: "outro", label: "Outro" },
  { value: "prefiro_nao_informar", label: "Prefiro não informar" },
];
const PARENTESCO = [
  { value: "mae", label: "Mãe" },
  { value: "pai", label: "Pai" },
  { value: "avo", label: "Avó ou avô" },
  { value: "tio", label: "Tia ou tio" },
  { value: "irmao", label: "Irmã ou irmão" },
  { value: "tutor", label: "Tutor legal" },
  { value: "outro", label: "Outro" },
];
const ROTULO_PARENTESCO: Record<string, string> = Object.fromEntries(PARENTESCO.map((p) => [p.value, p.label]));

const VAZIO = { nome: "", nascimento: "", sexo: "", parentesco: "", cpf: "", email: "" };

const digitos = (v: string) => String(v || "").replace(/\D/g, "");
const formatCpf = (v: string) => {
  const d = digitos(v).slice(0, 11);
  if (d.length <= 3) return d;
  if (d.length <= 6) return `${d.slice(0, 3)}.${d.slice(3)}`;
  if (d.length <= 9) return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6)}`;
  return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`;
};
const formatData = (v: string) => {
  const d = digitos(v).slice(0, 8);
  if (d.length <= 2) return d;
  if (d.length <= 4) return `${d.slice(0, 2)}/${d.slice(2)}`;
  return `${d.slice(0, 2)}/${d.slice(2, 4)}/${d.slice(4)}`;
};
/** Idade a partir de DD/MM/AAAA; null se a data não existe ou é futura. */
const idadeDe = (v: string): number | null => {
  const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(v || "");
  if (!m) return null;
  const [dia, mes, ano] = [Number(m[1]), Number(m[2]), Number(m[3])];
  const dt = new Date(ano, mes - 1, dia);
  if (dt.getFullYear() !== ano || dt.getMonth() !== mes - 1 || dt.getDate() !== dia) return null;
  const hoje = new Date();
  if (dt > hoje) return null;
  let idade = hoje.getFullYear() - ano;
  if (hoje.getMonth() < mes - 1 || (hoje.getMonth() === mes - 1 && hoje.getDate() < dia)) idade -= 1;
  return idade;
};
const titulo = (n: string) =>
  String(n || "").toLowerCase().split(/\s+/).map((w, i) => (i > 0 && ["da", "de", "do", "das", "dos", "e"].includes(w) ? w : w.charAt(0).toUpperCase() + w.slice(1))).join(" ");

function Chips({ opcoes, valor, onChange, label, erro }: { opcoes: { value: string; label: string }[]; valor: string; onChange: (v: string) => void; label: string; erro?: string }) {
  return (
    <View>
      <Text style={styles.fieldLabel}>{label}</Text>
      <View style={styles.chips} accessibilityRole="radiogroup" accessibilityLabel={label}>
        {opcoes.map((o) => {
          const on = valor === o.value;
          return (
            <Pressable key={o.value} onPress={() => onChange(on ? "" : o.value)} accessibilityRole="radio" accessibilityState={{ checked: on }} style={[styles.chip, on && styles.chipOn]}>
              {on ? <Feather name="check" size={16} color={ds.onInverse} /> : null}
              <Text style={[styles.chipText, on && { color: ds.onInverse }]}>{o.label}</Text>
            </Pressable>
          );
        })}
      </View>
      {erro ? <Text style={styles.erro}>{erro}</Text> : null}
    </View>
  );
}

/** Conta → Dependentes: filhos menores na conta do responsável, sem e-mail nem CPF próprios. */
export default function DependentesScreen() {
  useProtectedRoute();
  const router = useRouter();
  const [lista, setLista] = useState<Dependente[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [erroLista, setErroLista] = useState("");
  const [formAberto, setFormAberto] = useState(false);
  // Dependente em edição (null = cadastro novo)
  const [editando, setEditando] = useState<Dependente | null>(null);
  const scrollRef = useRef<ScrollView>(null);
  const [form, setForm] = useState(VAZIO);
  const [tentou, setTentou] = useState(false);
  const [salvando, setSalvando] = useState(false);
  const [erroForm, setErroForm] = useState("");
  const [entrando, setEntrando] = useState<number | null>(null);
  // PIN do login próprio (e-mail do responsável + PIN)
  const [pinPara, setPinPara] = useState<number | null>(null);
  const [pin, setPin] = useState("");
  const [pinErro, setPinErro] = useState("");
  const [pinOk, setPinOk] = useState("");
  const [pinSalvando, setPinSalvando] = useState(false);

  const abrirPin = (id: number) => {
    setPinPara(pinPara === id ? null : id);
    setPin("");
    setPinErro("");
    setPinOk("");
  };
  const salvarPin = async (d: Dependente) => {
    if (!/^\d{4,6}$/.test(pin)) {
      setPinErro("O PIN tem de 4 a 6 números.");
      return;
    }
    setPinSalvando(true);
    try {
      const r = await dependenteService.definirPin(d.usuario_id, pin);
      setPinPara(null);
      setPinOk(r?.message || "PIN salvo.");
      await carregar();
    } catch (err: any) {
      setPinErro(err?.message || "Não foi possível salvar o PIN.");
    } finally {
      setPinSalvando(false);
    }
  };
  const removerPin = async (d: Dependente) => {
    setPinSalvando(true);
    try {
      await dependenteService.removerPin(d.usuario_id);
      setPinPara(null);
      setPinOk(`${titulo(d.nome).split(" ")[0]} não entra mais sozinho. Você continua usando o perfil por aqui.`);
      await carregar();
    } catch (err: any) {
      setPinErro(err?.message || "Não foi possível remover o acesso.");
    } finally {
      setPinSalvando(false);
    }
  };

  const carregar = useCallback(async () => {
    setErroLista("");
    try {
      const deps = (await dependenteService.listar()) as Dependente[];
      setLista(deps);
      if (!deps.length) setFormAberto(true);
    } catch (e: any) {
      setErroLista(e?.message || "Não foi possível carregar os dependentes.");
    } finally {
      setCarregando(false);
    }
  }, []);

  useEffect(() => {
    void carregar();
  }, [carregar]);

  const set = (k: keyof typeof VAZIO, v: string) => {
    setForm((f) => ({ ...f, [k]: v }));
    if (erroForm) setErroForm("");
  };

  const idade = idadeDe(form.nascimento);
  const erros: Record<string, string> = {};
  if (form.nome.trim().split(/\s+/).filter(Boolean).length < 2) erros.nome = "Escreva nome e sobrenome.";
  if (idade == null) erros.nascimento = "Use dia, mês e ano: 14/03/2015.";
  else if (idade >= 18) erros.nascimento = "Dependente precisa ter menos de 18 anos. Quem é maior faz o próprio cadastro.";
  if (!form.sexo) erros.sexo = "Escolha uma opção.";
  if (form.cpf && digitos(form.cpf).length !== 11) erros.cpf = "O CPF tem 11 números. Deixe em branco se não tiver.";
  if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(form.email.trim())) erros.email = "Confira o e-mail ou deixe em branco.";
  const e = (k: string) => (tentou ? erros[k] : undefined);

  const fecharForm = () => {
    setForm(VAZIO);
    setTentou(false);
    setErroForm("");
    setEditando(null);
    setFormAberto(false);
  };

  const editar = (d: Dependente) => {
    setPinPara(null);
    setEditando(d);
    setForm({
      nome: titulo(d.nome),
      nascimento: d.data_nascimento ? d.data_nascimento.split("-").reverse().join("/") : "",
      sexo: d.sexo || "",
      parentesco: d.parentesco || "",
      cpf: d.cpf ? formatCpf(d.cpf) : "",
      email: d.email || "",
    });
    setTentou(false);
    setErroForm("");
    setFormAberto(true);
    setTimeout(() => scrollRef.current?.scrollToEnd({ animated: true }), 50);
  };

  const salvar = async () => {
    setTentou(true);
    if (Object.keys(erros).length) return;
    setSalvando(true);
    const dados = {
      nome: form.nome.trim().replace(/\s+/g, " "),
      data_nascimento: form.nascimento.split("/").reverse().join("-"),
      sexo: form.sexo,
      parentesco: form.parentesco || "",
      cpf: digitos(form.cpf),
      email: form.email.trim().toLowerCase(),
    };
    try {
      if (editando) {
        await dependenteService.atualizar(editando.usuario_id, dados);
        setPinOk(`Dados de ${titulo(dados.nome).split(" ")[0]} atualizados.`);
      } else {
        await dependenteService.criar(dados);
      }
      fecharForm();
      await carregar();
    } catch (err: any) {
      setErroForm(err?.message || (editando ? "Não foi possível salvar." : "Não foi possível cadastrar."));
    } finally {
      setSalvando(false);
    }
  };

  const usarPerfil = async (d: Dependente) => {
    setEntrando(d.usuario_id);
    try {
      // O layout raiz remonta e leva para o início já no perfil do dependente
      await dependenteService.entrar(d);
    } catch (err: any) {
      setErroLista(err?.message || "Não foi possível abrir o perfil.");
      setEntrando(null);
    }
  };

  return (
    <SafeAreaView style={styles.container} edges={["left", "right"]}>
      <AppHeader title="Dependentes" subtitle="Filhos e menores na sua conta" onBack={() => router.back()} />
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === "ios" ? "padding" : undefined}>
        <ScrollView ref={scrollRef} contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
          <View style={styles.info}>
            <Feather name="info" size={16} color={ds.inkMuted} />
            <Text style={styles.infoText}>
              Crianças não precisam de e-mail nem CPF. Você entra no perfil delas por aqui para fazer check-in e ver planos. As cobranças saem no seu nome e CPF.
            </Text>
          </View>

          {carregando ? (
            <ActivityIndicator color={ds.brand} style={{ marginVertical: space[6] }} />
          ) : lista.length ? (
            <Section title="NA SUA CONTA">
              {lista.map((d) => (
                <View key={d.usuario_id}>
                <View style={styles.dep}>
                  <View style={styles.avatar}>
                    <Text style={styles.avatarText}>{titulo(d.nome).split(" ").map((w) => w[0]).filter(Boolean).slice(0, 2).join("")}</Text>
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.depNome} numberOfLines={1}>{titulo(d.nome)}</Text>
                    <Text style={styles.depSub} numberOfLines={1}>
                      {[d.idade != null ? `${d.idade} ${d.idade === 1 ? "ano" : "anos"}` : null, d.parentesco ? `você: ${ROTULO_PARENTESCO[d.parentesco] || d.parentesco}` : null].filter(Boolean).join(" · ")}
                    </Text>
                  </View>
                  <Button size="sm" variant="dark" icon="log-in" loading={entrando === d.usuario_id} disabled={entrando != null} onPress={() => void usarPerfil(d)}>
                    Usar perfil
                  </Button>
                </View>
                <Pressable style={styles.acesso} onPress={() => abrirPin(d.usuario_id)} accessibilityRole="button">
                  <Feather name={d.tem_pin ? "smartphone" : "lock"} size={15} color={d.tem_pin ? ds.success : ds.inkMuted} />
                  <Text style={styles.acessoText}>
                    {d.tem_pin ? "Entra sozinho com o seu e-mail e PIN · " : "Não entra sozinho · "}
                    <Text style={styles.acessoLink}>{d.tem_pin ? "Trocar PIN" : "Criar PIN"}</Text>
                  </Text>
                </Pressable>
                <Pressable style={styles.acesso} onPress={() => editar(d)} accessibilityRole="button">
                  <Feather name="edit-2" size={15} color={ds.inkMuted} />
                  <Text style={styles.acessoText}>
                    <Text style={styles.acessoLink}>Editar dados</Text>
                  </Text>
                </Pressable>
                {pinPara === d.usuario_id ? (
                  <View style={styles.pinBox}>
                    <Text style={styles.pinInfo}>
                      {titulo(d.nome).split(" ")[0]} vai entrar em “Sou dependente” na tela de login, com o seu e-mail e este PIN. Não use a data de nascimento. Você recebe um aviso a cada acesso, e compras continuam com você.
                    </Text>
                    <TextField label="PIN (4 a 6 números)" icon="lock" keyboardType="number-pad" secureTextEntry maxLength={6} value={pin} error={pinErro || null}
                      onChangeText={(v) => { setPin(v.replace(/\D/g, "").slice(0, 6)); setPinErro(""); }} editable={!pinSalvando} />
                    <View style={styles.pinAcoes}>
                      <Button size="sm" icon="check" loading={pinSalvando} onPress={() => void salvarPin(d)}>Salvar PIN</Button>
                      {d.tem_pin ? <Button size="sm" variant="danger" icon="x" disabled={pinSalvando} onPress={() => void removerPin(d)}>Tirar acesso</Button> : null}
                      <Button size="sm" variant="ghost" disabled={pinSalvando} onPress={() => setPinPara(null)}>Cancelar</Button>
                    </View>
                  </View>
                ) : null}
                </View>
              ))}
            </Section>
          ) : null}
          {erroLista ? <Text style={styles.erro}>{erroLista}</Text> : null}
          {pinOk ? <Text style={styles.ok}>{pinOk}</Text> : null}

          {formAberto ? (
            <Section title={editando ? `EDITAR ${titulo(editando.nome).split(" ")[0].toUpperCase()}` : "NOVO DEPENDENTE"} bare>
              <View style={[cardStyle, styles.fields]}>
                <TextField label="Nome completo" icon="user" placeholder="Como está no documento" autoCapitalize="words" value={form.nome} error={e("nome")} onChangeText={(v) => set("nome", v)} editable={!salvando} />
                <TextField label="Data de nascimento" icon="calendar" placeholder="DD/MM/AAAA" keyboardType="numeric" value={form.nascimento} error={e("nascimento")}
                  hint={idade != null && idade < 18 ? `${idade} ${idade === 1 ? "ano" : "anos"}` : null} onChangeText={(v) => set("nascimento", formatData(v))} editable={!salvando} />
                <Chips label="Sexo" opcoes={SEXO} valor={form.sexo} onChange={(v) => set("sexo", v)} erro={e("sexo")} />
                <Chips label="Você é (opcional)" opcoes={PARENTESCO} valor={form.parentesco} onChange={(v) => set("parentesco", v)} />
                <TextField label="CPF da criança (opcional)" icon="credit-card" placeholder="Deixe em branco se não tiver" keyboardType="numeric" value={form.cpf} error={e("cpf")}
                  onChangeText={(v) => set("cpf", formatCpf(v))} editable={!salvando} />
                <TextField label="E-mail da criança (opcional)" icon="mail" placeholder="Deixe em branco se não tiver" keyboardType="email-address" autoCapitalize="none" autoCorrect={false}
                  value={form.email} error={e("email")} hint="Não use o seu: a criança entra pelo seu perfil." onChangeText={(v) => set("email", v)} editable={!salvando} />
                {erroForm ? <Text style={styles.erro}>{erroForm}</Text> : null}
                <Button block size="lg" icon={editando ? "save" : "user-plus"} loading={salvando} onPress={() => void salvar()}>
                  {editando ? "Salvar alterações" : "Cadastrar dependente"}
                </Button>
                {lista.length ? <Button block variant="ghost" onPress={fecharForm}>Cancelar</Button> : null}
              </View>
            </Section>
          ) : (
            <Button block variant="secondary" icon="user-plus" onPress={() => { setEditando(null); setForm(VAZIO); setFormAberto(true); }}>Adicionar dependente</Button>
          )}
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: ds.ground },
  scroll: { padding: space[4], gap: space[4], paddingBottom: space[8] },
  info: { flexDirection: "row", gap: space[2], alignItems: "flex-start" },
  infoText: { ...type.caption, color: ds.inkMuted, flex: 1 },
  dep: { flexDirection: "row", alignItems: "center", gap: space[3], padding: space[4] },
  avatar: { width: 40, height: 40, borderRadius: 20, backgroundColor: ds.brandSoft, alignItems: "center", justifyContent: "center" },
  avatarText: { fontWeight: "800", color: ds.brandInk },
  depNome: { ...type.heading, color: ds.ink },
  depSub: { ...type.caption, color: ds.inkMuted },
  fields: { padding: space[4], gap: space[4], overflow: "visible" },
  fieldLabel: { ...type.label, color: ds.inkMuted, marginBottom: space[2] },
  chips: { flexDirection: "row", flexWrap: "wrap", gap: space[2] },
  chip: {
    flexGrow: 1, flexBasis: "45%", minHeight: 48, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 6,
    paddingVertical: 6, paddingHorizontal: space[2], borderRadius: radius.md, backgroundColor: ds.surface, borderWidth: 1.5, borderColor: ds.lineStrong,
  },
  chipOn: { backgroundColor: ds.inverse, borderColor: ds.inverse },
  chipText: { fontSize: 15, lineHeight: 18, fontWeight: "700", color: ds.ink, textAlign: "center" },
  erro: { ...type.caption, color: ds.danger },
  ok: { ...type.caption, color: ds.success },
  acesso: { flexDirection: "row", alignItems: "center", gap: space[2], paddingHorizontal: space[4], paddingBottom: space[3], marginTop: -space[2] },
  acessoText: { ...type.caption, color: ds.inkMuted, flex: 1 },
  acessoLink: { color: ds.brandInk, textDecorationLine: "underline" },
  pinBox: { paddingHorizontal: space[4], paddingBottom: space[4], gap: space[3] },
  pinInfo: { ...type.caption, color: ds.ink },
  pinAcoes: { flexDirection: "row", flexWrap: "wrap", gap: space[2] },
});
