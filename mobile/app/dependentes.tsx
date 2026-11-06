import { useProtectedRoute } from "@/hooks/useProtectedRoute";
import AppHeader from "@/src/components/ds/AppHeader";
import Button from "@/src/components/ds/Button";
import Section, { cardStyle } from "@/src/components/ds/Section";
import TextField from "@/src/components/ds/TextField";
import { ds, radius, space, type } from "@/src/components/ds/tokens";
import { dependenteService } from "@/src/services/dependenteService";
import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import React, { useCallback, useEffect, useState } from "react";
import { ActivityIndicator, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

type Dependente = { usuario_id: number; nome: string; idade: number | null; parentesco: string | null; na_academia: boolean };

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
  const [form, setForm] = useState(VAZIO);
  const [tentou, setTentou] = useState(false);
  const [salvando, setSalvando] = useState(false);
  const [erroForm, setErroForm] = useState("");
  const [entrando, setEntrando] = useState<number | null>(null);

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

  const salvar = async () => {
    setTentou(true);
    if (Object.keys(erros).length) return;
    setSalvando(true);
    try {
      await dependenteService.criar({
        nome: form.nome.trim().replace(/\s+/g, " "),
        data_nascimento: form.nascimento.split("/").reverse().join("-"),
        sexo: form.sexo,
        parentesco: form.parentesco || undefined,
        cpf: digitos(form.cpf) || undefined,
        email: form.email.trim().toLowerCase() || undefined,
      });
      setForm(VAZIO);
      setTentou(false);
      setFormAberto(false);
      await carregar();
    } catch (err: any) {
      setErroForm(err?.message || "Não foi possível cadastrar.");
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
        <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
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
                <View key={d.usuario_id} style={styles.dep}>
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
              ))}
            </Section>
          ) : null}
          {erroLista ? <Text style={styles.erro}>{erroLista}</Text> : null}

          {formAberto ? (
            <Section title="NOVO DEPENDENTE" bare>
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
                <Button block size="lg" icon="user-plus" loading={salvando} onPress={() => void salvar()}>Cadastrar dependente</Button>
                {lista.length ? <Button block variant="ghost" onPress={() => { setFormAberto(false); setTentou(false); }}>Cancelar</Button> : null}
              </View>
            </Section>
          ) : (
            <Button block variant="secondary" icon="user-plus" onPress={() => setFormAberto(true)}>Adicionar dependente</Button>
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
});
