import { Feather } from "@expo/vector-icons";
import React, { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { AppState, KeyboardAvoidingView, Modal, Platform, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import Button from "@/src/components/ds/Button";
import CodigoInput from "@/src/components/ds/CodigoInput";
import Section from "@/src/components/ds/Section";
import TextField from "@/src/components/ds/TextField";
import { ds, radius, space, type } from "@/src/components/ds/tokens";
import api from "@/src/services/api";

/**
 * Atualização cadastral pedida pelo super admin: cobre o app com os campos a revisar, a confirmação
 * do e-mail por código e a troca de senha. Campanha que não bloqueia aceita "Agora não" (volta na
 * próxima abertura do app).
 */

type Campo = { campo: string; label: string; tipo: string; obrigatorio: boolean; valor: string | null };
type Pendente = {
  id: number;
  titulo: string;
  mensagem: string | null;
  bloquear_app: boolean;
  campos: Campo[];
  email_atual: string | null;
  validar_email: boolean;
  email_verificado: boolean;
  trocar_senha: boolean;
  senha: { min_caracteres: number; nivel: string; regras: string[] } | null;
};

const SEXOS = [
  { value: "feminino", label: "Feminino" },
  { value: "masculino", label: "Masculino" },
  { value: "outro", label: "Outro" },
  { value: "prefiro_nao_informar", label: "Prefiro não informar" },
];

const digitos = (v: string) => String(v || "").replace(/\D/g, "");
const MASCARA: Record<string, (v: string) => string> = {
  cpf: (v) => {
    const d = digitos(v).slice(0, 11);
    return d.replace(/(\d{3})(\d)/, "$1.$2").replace(/(\d{3})(\d)/, "$1.$2").replace(/(\d{3})(\d{1,2})$/, "$1-$2");
  },
  telefone: (v) => {
    const d = digitos(v).slice(0, 11);
    if (d.length <= 2) return d ? `(${d}` : "";
    if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
    if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
    return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
  },
  cep: (v) => {
    const d = digitos(v).slice(0, 8);
    return d.length > 5 ? `${d.slice(0, 5)}-${d.slice(5)}` : d;
  },
  data: (v) => {
    const d = digitos(v).slice(0, 8);
    if (d.length <= 2) return d;
    if (d.length <= 4) return `${d.slice(0, 2)}/${d.slice(2)}`;
    return `${d.slice(0, 2)}/${d.slice(2, 4)}/${d.slice(4)}`;
  },
  uf: (v) => v.replace(/[^a-zA-Z]/g, "").slice(0, 2).toUpperCase(),
};

/** Valor do banco para o campo da tela (data AAAA-MM-DD vira DD/MM/AAAA). */
const paraTela = (c: Campo) => {
  const v = c.valor == null ? "" : String(c.valor);
  if (c.tipo === "data") return v ? v.slice(0, 10).split("-").reverse().join("/") : "";
  return MASCARA[c.tipo] ? MASCARA[c.tipo](v) : v;
};
const paraApi = (tipo: string, v: string) => {
  if (tipo === "data") {
    const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(v);
    return m ? `${m[3]}-${m[2]}-${m[1]}` : v;
  }
  return v.trim();
};

/** Mesmas regras de App\Support\PoliticaSenha, para mostrar o que falta enquanto digita. */
const faltasSenha = (senha: string, nivel: string, min: number) => {
  const f: string[] = [];
  if (senha.length < min) f.push(`Pelo menos ${min} caracteres`);
  const letra = /\p{L}/u.test(senha);
  const numero = /\d/.test(senha);
  if (nivel === "media" && (!letra || !numero)) f.push("Letras e números");
  if (nivel === "forte") {
    if (!/\p{Lu}/u.test(senha)) f.push("Uma letra maiúscula");
    if (!/\p{Ll}/u.test(senha)) f.push("Uma letra minúscula");
    if (!numero) f.push("Um número");
    if (!/[^\p{L}\d\s]/u.test(senha)) f.push("Um símbolo (ex.: ! @ # $)");
  }
  return f;
};

export default function AtualizacaoCadastralGate({ enabled }: { enabled: boolean }) {
  const [pendente, setPendente] = useState<Pendente | null>(null);
  const [adiada, setAdiada] = useState<number | null>(null);
  const [valores, setValores] = useState<Record<string, string>>({});
  const [erros, setErros] = useState<Record<string, string>>({});
  const [erroGeral, setErroGeral] = useState("");
  const [salvando, setSalvando] = useState(false);
  const [codigo, setCodigo] = useState("");
  const [codigoEnviadoPara, setCodigoEnviadoPara] = useState("");
  const [emailConfirmado, setEmailConfirmado] = useState("");
  const [enviandoCodigo, setEnviandoCodigo] = useState(false);
  const [aviso, setAviso] = useState("");
  const [senhaAtual, setSenhaAtual] = useState("");
  const [novaSenha, setNovaSenha] = useState("");
  const [confirmaSenha, setConfirmaSenha] = useState("");
  const buscando = useRef(false);
  const idAtual = useRef<number | null>(null);

  const carregar = useCallback(async () => {
    if (!enabled || buscando.current) return;
    buscando.current = true;
    try {
      const r: any = await api.get("/mobile/atualizacao-cadastral");
      const p: Pendente | null = r?.data?.pendente ?? null;
      // Outra campanha (ou a primeira): começa o formulário do zero com os valores atuais
      if (p && idAtual.current !== p.id) {
        setValores(Object.fromEntries(p.campos.map((c) => [c.campo, paraTela(c)])));
        setErros({});
        setErroGeral("");
        setCodigo("");
        setCodigoEnviadoPara("");
        setEmailConfirmado("");
        setSenhaAtual("");
        setNovaSenha("");
        setConfirmaSenha("");
      }
      idAtual.current = p?.id ?? null;
      setPendente(p);
    } catch {
      // Sem conexão ou API antiga: não bloqueia o app por causa disso
    } finally {
      buscando.current = false;
    }
  }, [enabled]);

  useEffect(() => {
    if (!enabled) {
      setPendente(null);
      return undefined;
    }
    void carregar();
    const sub = AppState.addEventListener("change", (s) => {
      if (s === "active") void carregar();
    });
    return () => sub.remove();
  }, [enabled, carregar]);

  const campoEmail = pendente?.campos.find((c) => c.campo === "email");
  const emailFinal = (campoEmail ? valores.email : pendente?.email_atual || "")?.trim().toLowerCase() || "";
  const emailMudou = !!campoEmail && emailFinal !== (pendente?.email_atual || "").toLowerCase();
  const precisaCodigo = !!pendente && (emailMudou || (pendente.validar_email && !pendente.email_verificado));
  const emailOk = !precisaCodigo || emailConfirmado === emailFinal;
  const faltas = useMemo(
    () => (pendente?.senha ? faltasSenha(novaSenha, pendente.senha.nivel, pendente.senha.min_caracteres) : []),
    [novaSenha, pendente?.senha],
  );

  const set = (campo: string, tipo: string) => (v: string) => {
    setValores((p) => ({ ...p, [campo]: MASCARA[tipo] ? MASCARA[tipo](v) : v }));
    if (erros[campo]) setErros((e) => ({ ...e, [campo]: "" }));
  };

  const enviarCodigo = async () => {
    setEnviandoCodigo(true);
    setAviso("");
    setErros((e) => ({ ...e, email: "" }));
    try {
      const r: any = await api.post("/mobile/atualizacao-cadastral/email/codigo", { email: emailFinal });
      setCodigoEnviadoPara(emailFinal);
      setAviso(r?.data?.message || `Enviamos um código para ${emailFinal}.`);
    } catch (e: any) {
      setErros((x) => ({ ...x, email: e?.response?.data?.error || e?.message || "Não foi possível enviar o código." }));
    } finally {
      setEnviandoCodigo(false);
    }
  };

  const confirmarCodigo = async (valor: string = codigo) => {
    setEnviandoCodigo(true);
    try {
      await api.post("/mobile/atualizacao-cadastral/email/validar", { email: codigoEnviadoPara, codigo: valor });
      setEmailConfirmado(codigoEnviadoPara);
      setAviso("");
      setErros((x) => ({ ...x, codigo: "", email: "" }));
    } catch (e: any) {
      setCodigo("");
      setErros((x) => ({ ...x, codigo: e?.response?.data?.error || e?.message || "Código incorreto." }));
    } finally {
      setEnviandoCodigo(false);
    }
  };

  const salvar = async () => {
    if (!pendente || salvando) return;
    const locais: Record<string, string> = {};
    pendente.campos.forEach((c) => {
      if (c.obrigatorio && !String(valores[c.campo] || "").trim()) locais[c.campo] = "Campo obrigatório.";
    });
    if (!emailOk) locais.email = "Confirme o e-mail com o código.";
    if (pendente.trocar_senha) {
      if (!senhaAtual) locais.senha_atual = "Informe a senha atual.";
      if (faltas.length) locais.nova_senha = "A senha ainda não atende às regras.";
      else if (novaSenha !== confirmaSenha) locais.confirma = "As senhas não são iguais.";
    }
    setErros(locais);
    if (Object.keys(locais).length) {
      setErroGeral("Confira os campos destacados.");
      return;
    }

    setSalvando(true);
    setErroGeral("");
    try {
      await api.post(`/mobile/atualizacao-cadastral/${pendente.id}`, {
        dados: Object.fromEntries(pendente.campos.map((c) => [c.campo, paraApi(c.tipo, valores[c.campo] || "")])),
        senha_atual: pendente.trocar_senha ? senhaAtual : undefined,
        nova_senha: pendente.trocar_senha ? novaSenha : undefined,
      });
      setPendente(null);
      void carregar();
    } catch (e: any) {
      const data = e?.response?.data || {};
      setErros(data.campos || {});
      setErroGeral(data.error || e?.message || "Não foi possível salvar. Tente de novo.");
    } finally {
      setSalvando(false);
    }
  };

  const visivel = !!pendente && adiada !== pendente.id;
  if (!visivel || !pendente) return null;

  return (
    <Modal visible animationType="slide" presentationStyle="fullScreen" onRequestClose={() => { if (!pendente.bloquear_app) setAdiada(pendente.id); }}>
      <SafeAreaView style={styles.root} edges={["top", "bottom", "left", "right"]}>
        <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === "ios" ? "padding" : undefined}>
          <ScrollView contentContainerStyle={styles.body} keyboardShouldPersistTaps="handled">
            <View style={styles.head}>
              <View style={styles.icon}><Feather name="user-check" size={24} color={ds.brandInk} /></View>
              <Text style={styles.title} accessibilityRole="header">{pendente.titulo}</Text>
              {pendente.mensagem ? <Text style={styles.text}>{pendente.mensagem}</Text> : null}
            </View>

            {erroGeral ? (
              <View style={styles.alert} accessibilityRole="alert">
                <Feather name="alert-circle" size={18} color={ds.danger} />
                <Text style={styles.alertText}>{erroGeral}</Text>
              </View>
            ) : null}

            {pendente.campos.length ? (
              <Section title="SEUS DADOS" padded>
                <View style={styles.fields}>
                  {pendente.campos.map((c) =>
                    c.tipo === "sexo" ? (
                      <View key={c.campo}>
                        <Text style={styles.label}>{c.label}{c.obrigatorio ? "" : " (opcional)"}</Text>
                        <View style={styles.opts}>
                          {SEXOS.map((o) => {
                            const on = valores.sexo === o.value;
                            return (
                              <Pressable key={o.value} onPress={() => set("sexo", "sexo")(o.value)} accessibilityRole="radio" accessibilityState={{ checked: on }} style={[styles.opt, on && styles.optOn]}>
                                <Text style={[styles.optText, on && { color: ds.onInverse }]}>{o.label}</Text>
                              </Pressable>
                            );
                          })}
                        </View>
                        {erros.sexo ? <Text style={styles.err}>{erros.sexo}</Text> : null}
                      </View>
                    ) : (
                      <TextField
                        key={c.campo}
                        label={`${c.label}${c.obrigatorio ? "" : " (opcional)"}`}
                        value={valores[c.campo] || ""}
                        onChangeText={set(c.campo, c.tipo)}
                        error={erros[c.campo] || null}
                        placeholder={c.tipo === "data" ? "DD/MM/AAAA" : undefined}
                        keyboardType={c.tipo === "email" ? "email-address" : ["cpf", "telefone", "cep", "data"].includes(c.tipo) ? "number-pad" : "default"}
                        autoCapitalize={c.tipo === "email" ? "none" : c.tipo === "uf" ? "characters" : "words"}
                        autoCorrect={false}
                      />
                    ),
                  )}
                </View>
              </Section>
            ) : null}

            {precisaCodigo ? (
              <Section title="CONFIRME SEU E-MAIL" padded>
                <View style={styles.fields}>
                  {emailConfirmado === emailFinal ? (
                    <View style={styles.ok}>
                      <Feather name="check-circle" size={18} color={ds.success} />
                      <Text style={styles.okText}>{emailFinal} confirmado</Text>
                    </View>
                  ) : (
                    <>
                      <Text style={styles.text}>Vamos enviar um código de 6 dígitos para <Text style={styles.strong}>{emailFinal || "seu e-mail"}</Text>.</Text>
                      {erros.email ? <Text style={styles.err}>{erros.email}</Text> : null}
                      <Button variant="secondary" icon="mail" loading={enviandoCodigo && codigoEnviadoPara !== emailFinal} disabled={!emailFinal} onPress={enviarCodigo}>
                        {codigoEnviadoPara === emailFinal ? "Reenviar código" : "Enviar código"}
                      </Button>
                      {aviso ? <Text style={styles.hint}>{aviso}</Text> : null}
                      {codigoEnviadoPara === emailFinal ? (
                        <>
                          <CodigoInput
                            value={codigo}
                            onChange={(v) => {
                              setCodigo(v);
                              if (erros.codigo) setErros((x) => ({ ...x, codigo: "" }));
                            }}
                            onCompleto={(v) => void confirmarCodigo(v)}
                            error={erros.codigo || null}
                            disabled={enviandoCodigo}
                            autoFocus
                          />
                          <Button variant="primary" icon="check" loading={enviandoCodigo} disabled={codigo.length !== 6} onPress={() => void confirmarCodigo()}>Confirmar e-mail</Button>
                        </>
                      ) : null}
                    </>
                  )}
                </View>
              </Section>
            ) : null}

            {pendente.trocar_senha && pendente.senha ? (
              <Section title="NOVA SENHA" padded>
                <View style={styles.fields}>
                  <TextField label="Senha atual" password value={senhaAtual} onChangeText={setSenhaAtual} error={erros.senha_atual || null} hint="Se você nunca trocou, é o seu CPF (só números)." />
                  <TextField label="Nova senha" password value={novaSenha} onChangeText={setNovaSenha} error={erros.nova_senha || null} />
                  <View style={styles.rules}>
                    {pendente.senha.regras.map((r) => {
                      const falta = faltas.includes(r);
                      return (
                        <View key={r} style={styles.rule}>
                          <Feather name={falta || !novaSenha ? "circle" : "check-circle"} size={14} color={falta || !novaSenha ? ds.inkMuted : ds.success} />
                          <Text style={styles.ruleText}>{r}</Text>
                        </View>
                      );
                    })}
                  </View>
                  <TextField label="Confirme a nova senha" password value={confirmaSenha} onChangeText={setConfirmaSenha} error={erros.confirma || null} />
                </View>
              </Section>
            ) : null}
          </ScrollView>

          <View style={styles.foot}>
            <Button variant="primary" size="lg" block icon="save" loading={salvando} onPress={salvar}>Salvar e continuar</Button>
            {pendente.bloquear_app ? null : (
              <Button variant="ghost" block disabled={salvando} onPress={() => setAdiada(pendente.id)}>Agora não</Button>
            )}
          </View>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: ds.ground },
  flex: { flex: 1 },
  body: { padding: space[4], gap: space[5], width: "100%", maxWidth: 560, alignSelf: "center" },
  head: { gap: space[2], paddingTop: space[2] },
  icon: { width: 48, height: 48, borderRadius: 16, alignItems: "center", justifyContent: "center", backgroundColor: ds.brandSoft },
  title: { ...type.title, color: ds.ink },
  text: { ...type.body, color: ds.inkMuted },
  strong: { fontWeight: "800", color: ds.ink },
  hint: { ...type.caption, color: ds.inkMuted },
  fields: { gap: space[4] },
  label: { ...type.label, color: ds.inkMuted, marginBottom: space[2] },
  opts: { flexDirection: "row", flexWrap: "wrap", gap: space[2] },
  opt: { flexGrow: 1, flexBasis: "45%", minHeight: 44, alignItems: "center", justifyContent: "center", borderRadius: radius.md, borderWidth: 1.5, borderColor: ds.lineStrong, backgroundColor: ds.surface, paddingHorizontal: space[2] },
  optOn: { backgroundColor: ds.inverse, borderColor: ds.inverse },
  optText: { fontSize: 15, fontWeight: "700", color: ds.ink, textAlign: "center" },
  err: { ...type.caption, color: ds.danger, marginTop: 4 },
  alert: { flexDirection: "row", gap: space[2], padding: space[3], borderRadius: radius.lg, backgroundColor: ds.dangerSoft },
  alertText: { flex: 1, fontSize: 14, fontWeight: "700", color: ds.danger },
  ok: { flexDirection: "row", alignItems: "center", gap: space[2] },
  okText: { ...type.body, fontWeight: "700", color: ds.success },
  rules: { gap: 6 },
  rule: { flexDirection: "row", alignItems: "center", gap: 8 },
  ruleText: { ...type.caption, color: ds.inkMuted },
  foot: { padding: space[4], gap: space[2], backgroundColor: ds.surface, borderTopWidth: 1, borderTopColor: ds.line },
});
