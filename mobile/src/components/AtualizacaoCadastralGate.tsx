import { Feather } from "@expo/vector-icons";
import React, { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { AppState, KeyboardAvoidingView, Modal, Platform, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import Button from "@/src/components/ds/Button";
import CodigoInput from "@/src/components/ds/CodigoInput";
import Section from "@/src/components/ds/Section";
import TextField from "@/src/components/ds/TextField";
import PhoneField from "@/src/components/ds/PhoneField";
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
  forcar_validacao_email?: boolean;
  email_verificado: boolean;
  trocar_senha: boolean;
  senha: { min_caracteres: number; nivel: string; regras: string[] } | null;
};
type PreMatricula = {
  tenant_id: number;
  disponivel: boolean;
  obrigatoria: boolean;
  status: "pendente" | "aprovada" | "reprovada" | null;
  observacao: string | null;
  motivo?: string | null;
  planos: { id: number; descricao: string | null; checkins_semanais: number; modalidade_id: number; modalidade_nome: string; dias_disponiveis?: number }[];
  turmas: { id: number; nome: string; modalidade_id: number; dias_semana: number[]; horario_inicio: string; horario_fim: string }[];
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
  const [preMatricula, setPreMatricula] = useState<PreMatricula | null>(null);
  const [preMatriculaAdiada, setPreMatriculaAdiada] = useState<number | null>(null);
  const [planoPreMatricula, setPlanoPreMatricula] = useState<number | null>(null);
  const [gradePreMatricula, setGradePreMatricula] = useState<{ turma_fixa_id: number; dia_semana: number }[]>([]);
  const [diaVencimento, setDiaVencimento] = useState("");
  const [erroPreMatricula, setErroPreMatricula] = useState("");
  const [salvandoPreMatricula, setSalvandoPreMatricula] = useState(false);
  const [carregandoPreMatricula, setCarregandoPreMatricula] = useState(false);
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
  const versaoBusca = useRef(0);
  const idAtual = useRef<number | null>(null);
  const forcarValidacaoAtual = useRef(false);

  const carregar = useCallback(async () => {
    if (!enabled || buscando.current) return;
    buscando.current = true;
    const versao = ++versaoBusca.current;
    try {
      try {
        const r: any = await api.get("/mobile/atualizacao-cadastral");
        if (versao !== versaoBusca.current) return;
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
        // A campanha pode passar a forçar validação enquanto o formulário já está aberto.
        if (p && idAtual.current === p.id && p.forcar_validacao_email && !forcarValidacaoAtual.current) {
          setCodigo("");
          setCodigoEnviadoPara("");
          setEmailConfirmado("");
          setAviso("");
        }
        forcarValidacaoAtual.current = !!p?.forcar_validacao_email;
        idAtual.current = p?.id ?? null;
        setPendente(p);
      } catch {
        if (versao !== versaoBusca.current) return;
        // Sem conexão ou API antiga: não bloqueia o app por causa disso
      }
      setCarregandoPreMatricula(true);
      try {
        const resposta: any = await api.get("/mobile/pre-matricula");
        if (versao !== versaoBusca.current) return;
        const atual: PreMatricula | null = resposta?.data?.pre_matricula ?? null;
        setPreMatricula(atual);
        if (atual?.status !== "reprovada") {
          setPlanoPreMatricula(null);
          setGradePreMatricula([]);
          setDiaVencimento("");
        }
        setErroPreMatricula("");
      } catch (error: any) {
        if (versao !== versaoBusca.current) return;
        setErroPreMatricula(error?.response?.data?.message || "Não foi possível carregar a pré-matrícula.");
        console.error("[PreMatriculaGate] Falha ao carregar pré-matrícula", error);
      } finally {
        if (versao === versaoBusca.current) setCarregandoPreMatricula(false);
      }
    } finally {
      if (versao === versaoBusca.current) buscando.current = false;
    }
  }, [enabled]);

  useEffect(() => {
    if (!enabled) {
      versaoBusca.current += 1;
      buscando.current = false;
      idAtual.current = null;
      forcarValidacaoAtual.current = false;
      setPendente(null);
      setAdiada(null);
      setPreMatricula(null);
      setPreMatriculaAdiada(null);
      setPlanoPreMatricula(null);
      setGradePreMatricula([]);
      setDiaVencimento("");
      setErroPreMatricula("");
      setCarregandoPreMatricula(false);
      setValores({});
      setErros({});
      setErroGeral("");
      setAviso("");
      setCodigo("");
      setCodigoEnviadoPara("");
      setEmailConfirmado("");
      setSenhaAtual("");
      setNovaSenha("");
      setConfirmaSenha("");
      setSalvando(false);
      setSalvandoPreMatricula(false);
      setEnviandoCodigo(false);
      return undefined;
    }
    void carregar();
    const sub = AppState.addEventListener("change", (s) => {
      if (s === "active") void carregar();
    });
    return () => {
      versaoBusca.current += 1;
      buscando.current = false;
      sub.remove();
    };
  }, [enabled, carregar]);

  const campoEmail = pendente?.campos.find((c) => c.campo === "email");
  const emailFinal = (campoEmail ? valores.email : pendente?.email_atual || "")?.trim().toLowerCase() || "";
  const emailMudou = !!campoEmail && emailFinal !== (pendente?.email_atual || "").toLowerCase();
  const precisaCodigo = !!pendente && (emailMudou || pendente.forcar_validacao_email || (pendente.validar_email && !pendente.email_verificado));
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
    const versao = versaoBusca.current;
    setEnviandoCodigo(true);
    setAviso("");
    setErros((e) => ({ ...e, email: "" }));
    try {
      const r: any = await api.post("/mobile/atualizacao-cadastral/email/codigo", { email: emailFinal });
      if (versao !== versaoBusca.current) return;
      setCodigoEnviadoPara(emailFinal);
      setAviso(r?.data?.message || `Enviamos um código para ${emailFinal}.`);
    } catch (e: any) {
      if (versao !== versaoBusca.current) return;
      setErros((x) => ({ ...x, email: e?.response?.data?.error || e?.message || "Não foi possível enviar o código." }));
    } finally {
      if (versao === versaoBusca.current) setEnviandoCodigo(false);
    }
  };

  const confirmarCodigo = async (valor: string = codigo) => {
    const versao = versaoBusca.current;
    setEnviandoCodigo(true);
    try {
      await api.post("/mobile/atualizacao-cadastral/email/validar", { email: codigoEnviadoPara, codigo: valor });
      if (versao !== versaoBusca.current) return;
      setEmailConfirmado(codigoEnviadoPara);
      setAviso("");
      setErros((x) => ({ ...x, codigo: "", email: "" }));
    } catch (e: any) {
      if (versao !== versaoBusca.current) return;
      setCodigo("");
      setErros((x) => ({ ...x, codigo: e?.response?.data?.error || e?.message || "Código incorreto." }));
    } finally {
      if (versao === versaoBusca.current) setEnviandoCodigo(false);
    }
  };

  const salvar = async () => {
    if (!pendente || salvando) return;
    const versao = versaoBusca.current;
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
      if (versao !== versaoBusca.current) return;
      setPendente(null);
      void carregar();
    } catch (e: any) {
      if (versao !== versaoBusca.current) return;
      const data = e?.response?.data || {};
      setErros(data.campos || {});
      setErroGeral(data.error || e?.message || "Não foi possível salvar. Tente de novo.");
    } finally {
      if (versao === versaoBusca.current) setSalvando(false);
    }
  };

  const salvarPreMatricula = async () => {
    if (!preMatricula || !planoSelecionado || salvandoPreMatricula) return;
    const versao = versaoBusca.current;
    const frequencia = Number(planoSelecionado.checkins_semanais);
    if (gradePreMatricula.length !== frequencia) {
      setErroPreMatricula(`Escolha exatamente ${frequencia} dia(s) e horário(s) por semana.`);
      return;
    }
    if (diaVencimento && (!/^\d{1,2}$/.test(diaVencimento) || Number(diaVencimento) < 1 || Number(diaVencimento) > 31)) {
      setErroPreMatricula("Informe um dia de pagamento entre 1 e 31.");
      return;
    }
    setSalvandoPreMatricula(true);
    setErroPreMatricula("");
    try {
      await api.post("/mobile/pre-matricula", {
        plano_id: planoSelecionado.id,
        grade: gradePreMatricula,
        dia_vencimento: diaVencimento ? Number(diaVencimento) : null,
      });
      if (versao !== versaoBusca.current) return;
      setPreMatricula({ ...preMatricula, status: "pendente" });
      setPlanoPreMatricula(null);
      setGradePreMatricula([]);
      setDiaVencimento("");
    } catch (error: any) {
      if (versao !== versaoBusca.current) return;
      const data = error?.response?.data || {};
      const details = Object.values(data.errors || {}).flat().join(" ");
      setErroPreMatricula(details || data.message || data.error || "Não foi possível enviar a pré-matrícula.");
    } finally {
      if (versao === versaoBusca.current) setSalvandoPreMatricula(false);
    }
  };

  const planoSelecionado = preMatricula?.planos.find((plano) => plano.id === planoPreMatricula) || null;
  const modalidadesPermitidas = planoSelecionado ? preMatricula?.turmas.filter((turma) => turma.modalidade_id === planoSelecionado.modalidade_id) || [] : [];
  const visivelCadastro = enabled && !!pendente && adiada !== pendente.id;
  const statusResolvido = !!preMatricula && ["pendente", "aprovada"].includes(preMatricula.status || "");
  const podeAdiarPreMatricula = !!preMatricula
    && (!preMatricula.obrigatoria || !preMatricula.disponivel || statusResolvido || !preMatricula.planos.length);
  const exigeEnvioAgora = !!preMatricula?.obrigatoria && preMatricula.disponivel && !statusResolvido && !!preMatricula.planos.length;
  const visivelPreMatricula = enabled && !visivelCadastro && !!preMatricula
    && (exigeEnvioAgora || preMatriculaAdiada !== preMatricula.tenant_id);
  const visivelErroPreMatricula = enabled && !visivelCadastro && !preMatricula && !!erroPreMatricula;
  if (!visivelCadastro && !visivelPreMatricula && !visivelErroPreMatricula) return null;

  return <>
    {visivelCadastro && pendente ? (
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
                    ) : c.tipo === "telefone" ? (
                      <PhoneField
                        key={c.campo}
                        label={`${c.label}${c.obrigatorio ? "" : " (opcional)"}`}
                        value={valores[c.campo] || ""}
                        onChangeText={set(c.campo, c.tipo)}
                        error={erros[c.campo] || null}
                      />
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
    ) : null}
    {visivelPreMatricula && preMatricula ? (
      <Modal visible animationType="slide" presentationStyle="fullScreen" onRequestClose={() => { if (podeAdiarPreMatricula) setPreMatriculaAdiada(preMatricula.tenant_id); }}>
        <SafeAreaView style={styles.root} edges={["top", "bottom", "left", "right"]}>
          <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === "ios" ? "padding" : undefined}>
            <ScrollView contentContainerStyle={styles.body} keyboardShouldPersistTaps="handled">
              <View style={styles.head}>
                <View style={styles.icon}><Feather name="clipboard" size={24} color={ds.brandInk} /></View>
                <Text style={styles.title} accessibilityRole="header">Pré-matrícula</Text>
                <Text style={styles.text}>
                  {!preMatricula.disponivel
                    ? preMatricula.motivo || "Esta pré-matrícula não está disponível para sua conta."
                    : preMatricula.status === "pendente"
                      ? "Seu pedido foi recebido e está aguardando análise da academia."
                      : preMatricula.status === "aprovada"
                        ? "Seu pedido foi aprovado. A academia ainda precisa criar sua matrícula; a aprovação não gera cobrança."
                        : "Informe o plano e os dias/horários desejados. A solicitação será analisada pela academia; não cria matrícula, não gera cobrança e não reserva vaga."}
                </Text>
              </View>
              {preMatricula.disponivel && preMatricula.status === "reprovada" ? (
                <View style={styles.alert} accessibilityRole="alert">
                  <Feather name="alert-circle" size={18} color={ds.danger} />
                  <Text style={styles.alertText}>{preMatricula.observacao || "Sua solicitação anterior não foi aprovada. Confira seus dados e envie novamente."}</Text>
                </View>
              ) : null}
              {preMatricula.disponivel && erroPreMatricula ? (
                <View style={styles.alert} accessibilityRole="alert">
                  <Feather name="alert-circle" size={18} color={ds.danger} />
                  <Text style={styles.alertText}>{erroPreMatricula}</Text>
                </View>
              ) : null}
              {preMatricula.disponivel && !statusResolvido ? <Section title="ESCOLHA O PLANO" padded>
                <View style={styles.fields}>
                  {preMatricula.planos.map((plano) => {
                    const selecionado = planoPreMatricula === plano.id;
                    const descricao = String(plano.descricao || "").replace(/\bfree\b/gi, "").trim();
                    return (
                      <View key={plano.id}>
                        <Pressable onPress={() => {
                          setPlanoPreMatricula(plano.id);
                          setGradePreMatricula([]);
                          setErroPreMatricula("");
                        }} accessibilityRole="radio" accessibilityState={{ checked: selecionado }} style={[styles.opt, selecionado && styles.optOn]}>
                          <Text style={[styles.optText, selecionado && { color: ds.onInverse }]}>
                            {plano.checkins_semanais}x por semana · {plano.modalidade_nome}{descricao ? `\n${descricao}` : ""}
                          </Text>
                        </Pressable>
                        {selecionado && plano.dias_disponiveis !== undefined && plano.dias_disponiveis < plano.checkins_semanais ? (
                          <Text style={styles.hint}>
                            Há turmas em {plano.dias_disponiveis} dia(s) diferente(s), mas este plano precisa de {plano.checkins_semanais}. Peça à academia para disponibilizar horários em mais dias.
                          </Text>
                        ) : null}
                      </View>
                    );
                  })}
                  {!preMatricula.planos.length ? <Text style={styles.text}>Não há planos disponíveis para esta pré-matrícula. Fale com a academia.</Text> : null}
                </View>
              </Section> : null}
              {preMatricula.disponivel && !statusResolvido && planoSelecionado ? (
                <Section title="ESCOLHA OS DIAS E HORÁRIOS" padded>
                  <View style={styles.fields}>
                    <Text style={styles.text}>Escolha {planoSelecionado.checkins_semanais} horário(s) em dias diferentes.</Text>
                    {Array.from({ length: 7 }, (_, index) => index + 1).map((dia) => {
                      const opcoes = modalidadesPermitidas.filter((turma) => turma.dias_semana.includes(dia));
                      if (!opcoes.length) return null;
                      const escolhida = gradePreMatricula.find((slot) => slot.dia_semana === dia);
                      return (
                        <View key={dia}>
                          <Text style={styles.label}>{["", "Segunda", "Terça", "Quarta", "Quinta", "Sexta", "Sábado", "Domingo"][dia]}</Text>
                          {opcoes.map((turma) => {
                            const marcada = escolhida?.turma_fixa_id === turma.id;
                            const jaCheia = gradePreMatricula.length >= planoSelecionado.checkins_semanais && !escolhida;
                            return (
                              <Pressable key={turma.id} disabled={jaCheia} onPress={() => {
                                setGradePreMatricula((atual) => {
                                  const semEsteDia = atual.filter((slot) => slot.dia_semana !== dia);
                                  return marcada ? semEsteDia : [...semEsteDia, { turma_fixa_id: turma.id, dia_semana: dia }];
                                });
                                setErroPreMatricula("");
                              }} accessibilityRole="radio" accessibilityState={{ checked: marcada, disabled: jaCheia }} style={[styles.opt, marcada && styles.optOn, jaCheia && { opacity: 0.45 }]}>
                                <Text style={[styles.optText, marcada && { color: ds.onInverse }]}>
                                  {String(turma.horario_inicio).slice(0, 5)} · {turma.nome}
                                </Text>
                              </Pressable>
                            );
                          })}
                        </View>
                      );
                    })}
                    <TextField label="Dia de vencimento (opcional)" value={diaVencimento} onChangeText={setDiaVencimento} keyboardType="number-pad" placeholder="De 1 a 31" />
                  </View>
                </Section>
              ) : null}
              <View style={styles.fields}>
                {preMatricula.disponivel && !statusResolvido ? <Button variant="primary" icon="check" loading={salvandoPreMatricula} disabled={!planoSelecionado || gradePreMatricula.length !== Number(planoSelecionado?.checkins_semanais)} onPress={() => void salvarPreMatricula()}>
                  Enviar pedido para análise
                </Button> : null}
                {podeAdiarPreMatricula ? <Button variant="secondary" onPress={() => setPreMatriculaAdiada(preMatricula.tenant_id)}>
                  {statusResolvido ? "Entendi" : "Fechar"}
                </Button> : null}
              </View>
            </ScrollView>
          </KeyboardAvoidingView>
        </SafeAreaView>
      </Modal>
    ) : null}
    {visivelErroPreMatricula ? (
      <Modal visible animationType="slide" presentationStyle="fullScreen" onRequestClose={() => {}}>
        <SafeAreaView style={styles.root} edges={["top", "bottom", "left", "right"]}>
          <View style={[styles.body, styles.flex]}>
            <View style={styles.head}>
              <View style={styles.icon}><Feather name="alert-circle" size={24} color={ds.danger} /></View>
              <Text style={styles.title} accessibilityRole="header">Não foi possível abrir a pré-matrícula</Text>
              <Text style={styles.text}>{erroPreMatricula}</Text>
            </View>
            <View style={styles.fields}>
              <Button variant="primary" loading={carregandoPreMatricula} onPress={() => void carregar()}>Tentar novamente</Button>
            </View>
          </View>
        </SafeAreaView>
      </Modal>
    ) : null}
  </>;
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
