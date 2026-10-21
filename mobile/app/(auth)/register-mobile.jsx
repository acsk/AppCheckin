import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import { useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import {
  SafeAreaView,
  useSafeAreaInsets,
} from "react-native-safe-area-context";
import AppHeader from "../../src/components/ds/AppHeader";
import Button from "../../src/components/ds/Button";
import Section, { cardStyle } from "../../src/components/ds/Section";
import SelectRow from "../../src/components/ds/SelectRow";
import Stepper from "../../src/components/ds/Stepper";
import TextField from "../../src/components/ds/TextField";
import { ds, radius, space, type } from "../../src/components/ds/tokens";
import urlsConfig from "../../src/config/urls";
import { authService } from "../../src/services/authService";

// Cadastro do aluno em três etapas (SignupScreen do design system):
// academia → dados e contato → endereço (opcional).

const INITIAL_FORM = {
  nome: "",
  email: "",
  cpf: "",
  data_nascimento: "",
  sexo: "",
  telefone: "",
  mesmoWhatsapp: true,
  whatsapp: "",
  cep: "",
  logradouro: "",
  numero: "",
  complemento: "",
  bairro: "",
  cidade: "",
  estado: "",
};

/** Valores aceitos pela apiV2 em alunos.sexo (App\Support\SexoAluno). */
const SEXO_OPCOES = [
  { value: "feminino", label: "Feminino" },
  { value: "masculino", label: "Masculino" },
  { value: "outro", label: "Outro" },
  { value: "prefiro_nao_informar", label: "Prefiro não informar" },
];

const ETAPAS = ["Academia", "Seus dados", "Endereço"];
const CAMPOS_DA_ETAPA = [
  ["tenant_id"],
  ["nome", "cpf", "data_nascimento", "sexo", "email", "telefone", "whatsapp"],
  ["cep", "logradouro", "numero", "cidade", "estado"],
];
const CAMPOS_ENDERECO = ["cep", "logradouro", "numero", "complemento", "bairro", "cidade", "estado"];

const digitos = (value) => String(value || "").replace(/\D/g, "");

const formatCpf = (value) => {
  const d = digitos(value).slice(0, 11);
  if (d.length <= 3) return d;
  if (d.length <= 6) return `${d.slice(0, 3)}.${d.slice(3)}`;
  if (d.length <= 9) return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6)}`;
  return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`;
};

const formatPhone = (value) => {
  const d = digitos(value).slice(0, 11);
  if (d.length <= 2) return d ? `(${d}` : "";
  if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
  if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
  return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
};

const formatCep = (value) => {
  const d = digitos(value).slice(0, 8);
  return d.length > 5 ? `${d.slice(0, 5)}-${d.slice(5)}` : d;
};

const formatDate = (value) => {
  const d = digitos(value).slice(0, 8);
  if (d.length <= 2) return d;
  if (d.length <= 4) return `${d.slice(0, 2)}/${d.slice(2)}`;
  return `${d.slice(0, 2)}/${d.slice(2, 4)}/${d.slice(4)}`;
};

const isValidCpf = (value) => {
  if (!value || value.length !== 11 || /^(\d)\1{10}$/.test(value)) return false;
  for (let t = 9; t < 11; t += 1) {
    let sum = 0;
    for (let i = 0; i < t; i += 1) sum += Number(value[i]) * (t + 1 - i);
    if (((sum * 10) % 11) % 10 !== Number(value[t])) return false;
  }
  return true;
};

const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);

/** Idade a partir de DD/MM/AAAA; null se a data não existe, é futura ou passa de 110 anos. */
const idadeDe = (value) => {
  const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value || "");
  if (!m) return null;
  const dia = Number(m[1]);
  const mes = Number(m[2]);
  const ano = Number(m[3]);
  const dt = new Date(ano, mes - 1, dia);
  if (dt.getFullYear() !== ano || dt.getMonth() !== mes - 1 || dt.getDate() !== dia) return null;
  const hoje = new Date();
  if (dt > hoje) return null;
  let idade = hoje.getFullYear() - ano;
  if (hoje.getMonth() < mes - 1 || (hoje.getMonth() === mes - 1 && hoje.getDate() < dia)) idade -= 1;
  return idade > 110 ? null : idade;
};

function Checkbox({ checked, label, onChange }) {
  return (
    <Pressable
      onPress={() => onChange(!checked)}
      accessibilityRole="checkbox"
      accessibilityState={{ checked }}
      style={styles.check}
    >
      <View style={[styles.checkBox, checked && styles.checkBoxOn]}>
        {checked ? <Feather name="check" size={14} color={ds.onInverse} /> : null}
      </View>
      <Text style={styles.checkLabel}>{label}</Text>
    </Pressable>
  );
}

export default function RegisterMobileScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const scrollRef = useRef(null);
  const numeroRef = useRef(null);
  const lastCepRequestedRef = useRef("");

  const [etapa, setEtapa] = useState(0);
  const [form, setForm] = useState(INITIAL_FORM);
  const [tenantId, setTenantId] = useState(null);
  const [tenants, setTenants] = useState([]);
  const [tenantsLoading, setTenantsLoading] = useState(true);
  const [busca, setBusca] = useState("");
  const [tentou, setTentou] = useState({});
  const [errosServidor, setErrosServidor] = useState({});
  const [formError, setFormError] = useState("");
  const [loading, setLoading] = useState(false);
  const [cepStatus, setCepStatus] = useState("idle");

  const cpfDigits = digitos(form.cpf);
  const cepDigits = digitos(form.cep);
  const idade = idadeDe(form.data_nascimento);
  const temEndereco = CAMPOS_ENDERECO.some((k) => String(form[k] || "").trim());

  const set = (field, value) => {
    setForm((prev) => ({ ...prev, [field]: value }));
    if (errosServidor[field]) setErrosServidor((prev) => ({ ...prev, [field]: undefined }));
    if (formError) setFormError("");
  };

  const erros = useMemo(() => {
    const e = {};
    if (!tenantId) e.tenant_id = "Escolha a academia onde você treina.";
    if (form.nome.trim().split(/\s+/).filter(Boolean).length < 2) e.nome = "Escreva nome e sobrenome.";
    if (!isValidEmail(form.email.trim())) e.email = "Confira o e-mail. Exemplo: nome@email.com";
    if (!isValidCpf(cpfDigits)) {
      e.cpf = cpfDigits.length < 11 ? "O CPF tem 11 números." : "Esse CPF não é válido. Confira os números.";
    }
    if (idade == null) e.data_nascimento = "Use dia, mês e ano: 14/03/1994.";
    if (!form.sexo) e.sexo = "Escolha uma opção.";
    if (digitos(form.telefone).length < 10) e.telefone = "Informe DDD e número.";
    if (!form.mesmoWhatsapp && digitos(form.whatsapp).length < 10) e.whatsapp = "Informe DDD e número do WhatsApp.";
    if (temEndereco) {
      if (cepDigits.length !== 8) e.cep = "O CEP tem 8 números.";
      if (!form.logradouro.trim()) e.logradouro = "Informe a rua.";
      if (!form.numero.trim()) e.numero = 'Informe o número ou "s/n".';
      if (!form.cidade.trim()) e.cidade = "Informe a cidade.";
      if (!/^[A-Za-z]{2}$/.test(form.estado.trim())) e.estado = "Sigla com 2 letras.";
    }
    return e;
  }, [form, tenantId, cpfDigits, cepDigits, idade, temEndereco]);

  // Erros de formato aparecem depois da primeira tentativa de avançar; os do servidor, sempre.
  const erro = (k) => errosServidor[k] || (tentou[etapa] ? erros[k] : undefined);

  useEffect(() => {
    if (Platform.OS !== "web" || typeof document === "undefined") return undefined;
    document.body?.setAttribute("data-recaptcha-visible", "true");
    return () => {
      document.body?.setAttribute("data-recaptcha-visible", "false");
    };
  }, []);

  const ensureRecaptchaEnterpriseLoaded = async (siteKey) => {
    if (Platform.OS !== "web") return;
    if (typeof window === "undefined" || typeof document === "undefined") {
      throw new Error("reCAPTCHA indisponível fora do navegador");
    }

    const hasEnterprise = () =>
      Boolean(
        window.grecaptcha?.enterprise &&
        typeof window.grecaptcha.enterprise.execute === "function",
      );

    if (hasEnterprise()) return;

    const scriptSrc = `https://www.google.com/recaptcha/enterprise.js?render=${siteKey}`;
    const existingScript = document.querySelector(`script[src="${scriptSrc}"]`);

    await new Promise((resolve, reject) => {
      let attempts = 0;
      const maxAttempts = 120;

      const checkReady = () => {
        if (hasEnterprise()) {
          resolve(true);
          return;
        }
        attempts += 1;
        if (attempts >= maxAttempts) {
          reject(new Error("Timeout ao carregar reCAPTCHA"));
          return;
        }
        setTimeout(checkReady, 100);
      };

      if (!existingScript) {
        const script = document.createElement("script");
        script.src = scriptSrc;
        script.async = true;
        script.defer = true;
        script.onerror = () => {
          reject(new Error("Falha ao carregar script do reCAPTCHA"));
        };
        document.head.appendChild(script);
      }

      checkReady();
    });
  };

  const getRecaptchaToken = async () => {
    if (Platform.OS !== "web") return null;

    const siteKey = urlsConfig?.recaptcha?.siteKey;
    if (!siteKey) {
      throw new Error("Site key do reCAPTCHA não configurada");
    }

    await ensureRecaptchaEnterpriseLoaded(siteKey);

    const enterprise = window.grecaptcha?.enterprise;
    if (!enterprise || typeof enterprise.execute !== "function") {
      throw new Error("reCAPTCHA indisponível no momento. Tente novamente.");
    }

    await new Promise((resolve) => enterprise.ready(resolve));
    return enterprise.execute(siteKey, { action: "REGISTER" });
  };

  useEffect(() => {
    let isMounted = true;

    (async () => {
      try {
        const response = await authService.getPublicTenants();
        const raw = Array.isArray(response?.data?.tenants)
          ? response.data.tenants
          : Array.isArray(response?.tenants)
            ? response.tenants
            : [];

        const lista = raw
          .map((t) => ({
            id: Number(t?.id),
            nome: t?.nome || "Academia",
            local: [t?.cidade, t?.estado].filter(Boolean).join(", "),
          }))
          .filter((t) => Number.isFinite(t.id) && t.id > 0)
          .sort((a, b) => a.nome.localeCompare(b.nome, "pt-BR"));

        if (!isMounted) return;
        setTenants(lista);
        if (lista.length === 1) setTenantId(lista[0].id);
      } catch (error) {
        if (!isMounted) return;
        setFormError(error?.message || "Não foi possível carregar a lista de academias. Tente novamente.");
      } finally {
        if (isMounted) setTenantsLoading(false);
      }
    })();

    return () => {
      isMounted = false;
    };
  }, []);

  useEffect(() => {
    if (cepDigits.length < 8) {
      lastCepRequestedRef.current = "";
      setCepStatus("idle");
      return;
    }
    if (lastCepRequestedRef.current === cepDigits) return;
    lastCepRequestedRef.current = cepDigits;
    setCepStatus("loading");

    (async () => {
      try {
        const response = await fetch(`https://viacep.com.br/ws/${cepDigits}/json/`);
        const data = await response.json();
        if (data?.erro === true || data?.erro === "true") {
          setCepStatus("notfound");
          return;
        }
        setForm((prev) => ({
          ...prev,
          logradouro: data.logradouro || prev.logradouro,
          bairro: data.bairro || prev.bairro,
          cidade: data.localidade || prev.cidade,
          estado: data.uf || prev.estado,
        }));
        setCepStatus("found");
        setTimeout(() => numeroRef.current?.focus?.(), 0);
      } catch {
        setCepStatus("notfound");
      }
    })();
  }, [cepDigits]);

  const irPara = (n) => {
    setEtapa(n);
    scrollRef.current?.scrollTo?.({ y: 0, animated: false });
  };

  const voltar = () => {
    if (etapa > 0) {
      irPara(etapa - 1);
      return;
    }
    if (router.canGoBack?.()) {
      router.back();
      return;
    }
    router.replace("/(auth)/login");
  };

  const buildPayload = () => {
    const payload = {
      nome: form.nome.trim().replace(/\s+/g, " "),
      email: form.email.trim().toLowerCase(),
      cpf: cpfDigits,
      tenant_id: Number(tenantId),
      sexo: form.sexo,
      data_nascimento: form.data_nascimento.split("/").reverse().join("-"),
      telefone: digitos(form.telefone),
      whatsapp: digitos(form.mesmoWhatsapp ? form.telefone : form.whatsapp),
    };

    if (temEndereco) {
      payload.cep = cepDigits;
      ["logradouro", "numero", "complemento", "bairro", "cidade"].forEach((k) => {
        const v = form[k].trim();
        if (v) payload[k] = v;
      });
      payload.estado = form.estado.trim().toUpperCase();
    }

    return payload;
  };

  const cadastrar = async () => {
    setLoading(true);
    try {
      const recaptchaToken = await getRecaptchaToken();
      const payload = buildPayload();
      if (recaptchaToken) payload.recaptcha_token = recaptchaToken;
      const response = await authService.registerMobile(payload);

      if (response?.token) {
        router.replace("/(auth)/register-success");
      } else {
        setFormError("Não foi possível concluir o cadastro. Tente novamente.");
      }
    } catch (error) {
      const message = error?.message || "";
      const code = error?.code || "";
      const lower = message.toLowerCase();

      if (code === "EMAIL_ALREADY_EXISTS" || lower.includes("email já cadastrado") || lower.includes("email already exists")) {
        setErrosServidor((prev) => ({ ...prev, email: "Este e-mail já tem cadastro. Entre com ele ou use outro." }));
        irPara(1);
        return;
      }

      if (code === "CPF_ALREADY_EXISTS" || lower.includes("cpf já cadastrado") || lower.includes("cpf already exists")) {
        setErrosServidor((prev) => ({ ...prev, cpf: "Este CPF já tem cadastro." }));
        irPara(1);
        return;
      }

      if (code === "RATE_LIMIT_EXCEEDED") {
        const retryAfter = error?.retryAfter;
        setFormError(
          message ||
            (typeof retryAfter === "number"
              ? `Muitas tentativas. Tente novamente em ${Math.ceil(retryAfter / 60)} minutos.`
              : "Muitas tentativas. Tente novamente mais tarde."),
        );
        return;
      }

      setFormError(
        message ||
          (Array.isArray(error?.errors) ? error.errors.join("\n") : null) ||
          "Não foi possível concluir o cadastro.",
      );
    } finally {
      setLoading(false);
    }
  };

  const avancar = () => {
    if (loading) return;
    setTentou((prev) => ({ ...prev, [etapa]: true }));
    if (CAMPOS_DA_ETAPA[etapa].some((k) => erros[k] || errosServidor[k])) return;
    if (etapa < 2) {
      irPara(etapa + 1);
      return;
    }
    cadastrar();
  };

  const limparEndereco = () => {
    setForm((prev) => ({ ...prev, cep: "", logradouro: "", numero: "", complemento: "", bairro: "", cidade: "", estado: "" }));
    setCepStatus("idle");
  };

  const termo = busca.trim().toLowerCase();
  const tenantsVisiveis = tenants.filter((t) => !termo || `${t.nome} ${t.local}`.toLowerCase().includes(termo));
  const tenantEscolhido = tenants.find((t) => t.id === tenantId);

  const erroLinha = (texto) => (
    <View style={styles.msg} accessibilityLiveRegion="polite">
      <Feather name="alert-circle" size={16} color={ds.danger} />
      <Text style={styles.msgErro}>{texto}</Text>
    </View>
  );

  let corpo;
  if (etapa === 0) {
    corpo = (
      <>
        <View>
          <Text style={styles.introTitle}>Onde você treina?</Text>
          <Text style={styles.introText}>
            Seu cadastro vai para a academia escolhida. Ela libera seu acesso às aulas e planos.
          </Text>
        </View>
        {tenants.length > 5 ? (
          <TextField
            icon="search"
            placeholder="Buscar academia ou cidade"
            accessibilityLabel="Buscar academia"
            value={busca}
            onChangeText={setBusca}
            autoCorrect={false}
          />
        ) : null}
        <View style={styles.gyms} accessibilityRole="radiogroup" accessibilityLabel="Academia">
          {tenantsLoading ? (
            <View style={styles.vazio}>
              <ActivityIndicator color={ds.brand} />
              <Text style={styles.vazioText}>Carregando academias…</Text>
            </View>
          ) : tenantsVisiveis.length ? (
            tenantsVisiveis.map((t) => (
              <SelectRow
                key={t.id}
                title={t.nome}
                subtitle={t.local || null}
                selected={tenantId === t.id}
                onSelect={() => setTenantId(t.id)}
              />
            ))
          ) : (
            <Text style={styles.vazioText}>
              {termo
                ? `Nenhuma academia com "${busca.trim()}". Confira o nome com a recepção.`
                : "Nenhuma academia disponível no momento."}
            </Text>
          )}
        </View>
        {erro("tenant_id") ? erroLinha(erro("tenant_id")) : null}
        <View style={styles.login}>
          <Text style={styles.loginText}>Já tem uma conta? </Text>
          <Pressable onPress={() => router.replace("/(auth)/login")} accessibilityRole="link">
            <Text style={styles.link}>Entrar</Text>
          </Pressable>
        </View>
      </>
    );
  } else if (etapa === 1) {
    corpo = (
      <>
        {tenantEscolhido ? (
          <Pressable
            style={styles.gymChip}
            onPress={() => irPara(0)}
            accessibilityRole="button"
            accessibilityLabel={`Cadastro em ${tenantEscolhido.nome}. Trocar academia`}
          >
            <Feather name="home" size={18} color={ds.inkMuted} />
            <Text style={styles.gymChipText} numberOfLines={1}>
              Cadastro em <Text style={styles.gymChipName}>{tenantEscolhido.nome}</Text>
            </Text>
            <Text style={styles.link}>Trocar</Text>
          </Pressable>
        ) : null}

        <Section title="Quem é você" bare>
          <View style={[cardStyle, styles.fields]}>
            <TextField
              label="Nome completo"
              icon="user"
              placeholder="Como está no documento"
              autoCapitalize="words"
              autoComplete="name"
              value={form.nome}
              error={erro("nome")}
              onChangeText={(v) => set("nome", v)}
              editable={!loading}
            />
            <TextField
              label="CPF"
              icon="credit-card"
              placeholder="000.000.000-00"
              keyboardType="numeric"
              value={form.cpf}
              error={erro("cpf")}
              onChangeText={(v) => set("cpf", formatCpf(v))}
              editable={!loading}
            />
            <TextField
              label="Data de nascimento"
              icon="calendar"
              placeholder="DD/MM/AAAA"
              keyboardType="numeric"
              autoComplete="birthdate-full"
              value={form.data_nascimento}
              error={erro("data_nascimento")}
              hint={
                idade != null
                  ? `${idade} ${idade === 1 ? "ano" : "anos"}${idade < 18 ? ". Menores de idade precisam de um responsável na recepção." : ""}`
                  : null
              }
              onChangeText={(v) => set("data_nascimento", formatDate(v))}
              editable={!loading}
            />
            <View>
              <Text style={styles.fieldLabel}>Sexo</Text>
              <View
                style={[styles.sexo, erro("sexo") && styles.sexoErro]}
                accessibilityRole="radiogroup"
                accessibilityLabel="Sexo"
              >
                {SEXO_OPCOES.map((o) => {
                  const on = form.sexo === o.value;
                  return (
                    <Pressable
                      key={o.value}
                      onPress={() => set("sexo", o.value)}
                      disabled={loading}
                      accessibilityRole="radio"
                      accessibilityState={{ checked: on }}
                      style={[styles.sexoOpt, on && styles.sexoOptOn]}
                    >
                      {on ? <Feather name="check" size={16} color={ds.onInverse} /> : null}
                      <Text style={[styles.sexoText, on && { color: ds.onInverse }]}>{o.label}</Text>
                    </Pressable>
                  );
                })}
              </View>
              {erro("sexo") ? erroLinha(erro("sexo")) : null}
            </View>
          </View>
        </Section>

        <Section title="Contato" bare>
          <View style={[cardStyle, styles.fields]}>
            <TextField
              label="E-mail"
              icon="mail"
              placeholder="nome@email.com"
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
              autoComplete="email"
              value={form.email}
              error={erro("email")}
              hint="Você vai usar para entrar no app."
              onChangeText={(v) => set("email", v)}
              editable={!loading}
            />
            <TextField
              label="Telefone"
              icon="phone"
              placeholder="(82) 99999-9999"
              keyboardType="phone-pad"
              autoComplete="tel"
              value={form.telefone}
              error={erro("telefone")}
              onChangeText={(v) => set("telefone", formatPhone(v))}
              editable={!loading}
            />
            <View style={styles.wa}>
              <TextField
                label="WhatsApp"
                icon="message-circle"
                placeholder="(82) 99999-9999"
                keyboardType="phone-pad"
                value={form.mesmoWhatsapp ? form.telefone : form.whatsapp}
                error={form.mesmoWhatsapp ? null : erro("whatsapp")}
                hint="A academia usa para avisos e grupos de turma."
                onChangeText={(v) => set("whatsapp", formatPhone(v))}
                editable={!loading && !form.mesmoWhatsapp}
                style={form.mesmoWhatsapp ? { color: ds.inkMuted } : null}
              />
              <Checkbox
                checked={form.mesmoWhatsapp}
                label="Mesmo número do telefone"
                onChange={(v) => {
                  // Ao desmarcar, o campo começa com o telefone para a pessoa só ajustar.
                  if (!v && !form.whatsapp) set("whatsapp", form.telefone);
                  set("mesmoWhatsapp", v);
                }}
              />
            </View>
          </View>
        </Section>
      </>
    );
  } else {
    const cepHint =
      cepStatus === "loading"
        ? "Buscando endereço…"
        : cepStatus === "found"
          ? "Endereço encontrado. Confira e complete o número."
          : cepStatus === "notfound"
            ? "Não achamos esse CEP. Preencha o endereço abaixo."
            : "Preenchemos rua, bairro e cidade pelo CEP.";
    corpo = (
      <>
        <View>
          <Text style={styles.introTitle}>Endereço</Text>
          <Text style={styles.introText}>
            Opcional. Ajuda a academia em contratos e cobranças. Dá para completar depois em Conta.
          </Text>
        </View>
        <View style={[cardStyle, styles.fields]}>
          <TextField
            label="CEP"
            icon="map-pin"
            placeholder="00000-000"
            keyboardType="numeric"
            autoComplete="postal-code"
            value={form.cep}
            error={erro("cep")}
            hint={cepHint}
            trailing={
              cepStatus === "loading" ? (
                <ActivityIndicator size="small" color={ds.ink} style={styles.trailing} />
              ) : cepStatus === "found" ? (
                <Feather name="check" size={18} color={ds.success} style={styles.trailing} />
              ) : null
            }
            onChangeText={(v) => set("cep", formatCep(v))}
            editable={!loading}
          />
          <TextField
            label="Rua"
            icon="home"
            placeholder="Rua, avenida"
            autoComplete="street-address"
            value={form.logradouro}
            error={erro("logradouro")}
            onChangeText={(v) => set("logradouro", v)}
            editable={!loading}
          />
          <View style={styles.row}>
            <View style={styles.colNumero}>
              <TextField
                ref={numeroRef}
                label="Número"
                placeholder="123"
                value={form.numero}
                error={erro("numero")}
                onChangeText={(v) => set("numero", v)}
                editable={!loading}
              />
            </View>
            <View style={styles.colCompl}>
              <TextField
                label="Complemento"
                placeholder="Apto, bloco"
                value={form.complemento}
                onChangeText={(v) => set("complemento", v)}
                editable={!loading}
              />
            </View>
          </View>
          <TextField
            label="Bairro"
            placeholder="Bairro"
            value={form.bairro}
            onChangeText={(v) => set("bairro", v)}
            editable={!loading}
          />
          <View style={styles.row}>
            <View style={styles.colCidade}>
              <TextField
                label="Cidade"
                placeholder="Cidade"
                value={form.cidade}
                error={erro("cidade")}
                onChangeText={(v) => set("cidade", v)}
                editable={!loading}
              />
            </View>
            <View style={styles.colUf}>
              <TextField
                label="UF"
                placeholder="AL"
                autoCapitalize="characters"
                maxLength={2}
                value={form.estado}
                error={erro("estado")}
                onChangeText={(v) => set("estado", v.replace(/[^A-Za-z]/g, "").toUpperCase())}
                editable={!loading}
              />
            </View>
          </View>
        </View>
        {temEndereco ? (
          <Pressable onPress={limparEndereco} style={styles.clear} accessibilityRole="button">
            <Text style={styles.link}>Limpar endereço</Text>
          </Pressable>
        ) : null}
      </>
    );
  }

  const rotulo =
    etapa < 2
      ? "Continuar"
      : loading
        ? "Criando conta…"
        : temEndereco
          ? "Criar minha conta"
          : "Criar conta sem endereço";

  return (
    <SafeAreaView style={styles.container} edges={["left", "right"]}>
      <AppHeader
        title="Criar conta"
        eyebrow={`Etapa ${etapa + 1} de 3`}
        onBack={voltar}
        bottom={<Stepper steps={ETAPAS} current={etapa} tone="brand" />}
      />
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === "ios" ? "padding" : undefined}
      >
        <ScrollView
          ref={scrollRef}
          style={styles.flex}
          contentContainerStyle={styles.body}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          {formError ? (
            <View style={styles.alert} accessibilityRole="alert">
              <Feather name="alert-circle" size={20} color={ds.danger} />
              <Text style={styles.alertText}>{formError}</Text>
            </View>
          ) : null}
          {corpo}
        </ScrollView>
        <View style={[styles.foot, { paddingBottom: space[3] + insets.bottom }]}>
          <Button
            variant={etapa < 2 ? "primary" : "glow"}
            size="lg"
            block
            loading={loading}
            disabled={etapa === 0 && tenantsLoading}
            iconRight={etapa < 2 ? "arrow-right" : undefined}
            onPress={avancar}
          >
            {rotulo}
          </Button>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: ds.ground },
  flex: { flex: 1 },
  body: {
    flexGrow: 1,
    paddingHorizontal: space[4],
    paddingTop: space[5],
    paddingBottom: space[6],
    gap: space[5],
    width: "100%",
    maxWidth: 560,
    alignSelf: "center",
  },
  introTitle: { ...type.title, color: ds.ink },
  introText: { ...type.body, color: ds.inkMuted, marginTop: 4 },
  gyms: { gap: space[2] },
  vazio: { flexDirection: "row", alignItems: "center", justifyContent: "center", gap: space[2], padding: space[4] },
  vazioText: { fontSize: 14, fontWeight: "600", color: ds.inkMuted, textAlign: "center", padding: space[4] },
  login: { marginTop: "auto", flexDirection: "row", justifyContent: "center", alignItems: "center", flexWrap: "wrap" },
  loginText: { fontSize: 15, fontWeight: "600", color: ds.inkMuted },
  link: {
    fontSize: 14,
    lineHeight: 20,
    fontWeight: "800",
    color: ds.brandInk,
    textDecorationLine: "underline",
  },
  gymChip: {
    flexDirection: "row",
    alignItems: "center",
    gap: space[2],
    minHeight: 48,
    paddingHorizontal: space[4],
    borderRadius: radius.md,
    backgroundColor: ds.surfaceSunken,
    borderWidth: 1,
    borderColor: ds.line,
  },
  gymChipText: { flex: 1, minWidth: 0, fontSize: 14, lineHeight: 18, fontWeight: "600", color: ds.inkMuted },
  gymChipName: { color: ds.ink, fontWeight: "800" },
  fields: { padding: space[4], gap: space[4], overflow: "visible" },
  fieldLabel: { ...type.label, color: ds.inkMuted, marginBottom: space[2] },
  sexo: { flexDirection: "row", flexWrap: "wrap", gap: space[2], borderRadius: radius.md },
  sexoErro: { borderWidth: 2, borderColor: ds.danger, padding: 2 },
  sexoOpt: {
    flexGrow: 1,
    flexBasis: "45%",
    minHeight: 48,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    paddingVertical: 6,
    paddingHorizontal: space[2],
    borderRadius: radius.md,
    backgroundColor: ds.surface,
    borderWidth: 1.5,
    borderColor: ds.lineStrong,
  },
  sexoOptOn: { backgroundColor: ds.inverse, borderColor: ds.inverse },
  sexoText: { fontSize: 15, lineHeight: 18, fontWeight: "700", color: ds.ink, textAlign: "center" },
  wa: { gap: space[1] },
  check: { flexDirection: "row", alignItems: "center", gap: 10, minHeight: 44 },
  checkBox: {
    width: 22,
    height: 22,
    borderRadius: 6,
    borderWidth: 2,
    borderColor: ds.lineStrong,
    backgroundColor: ds.surface,
    alignItems: "center",
    justifyContent: "center",
  },
  checkBoxOn: { backgroundColor: ds.inverse, borderColor: ds.inverse },
  checkLabel: { flexShrink: 1, fontSize: 15, fontWeight: "600", color: ds.ink },
  row: { flexDirection: "row", gap: space[3] },
  colNumero: { flex: 0.9, minWidth: 0 },
  colCompl: { flex: 1.4, minWidth: 0 },
  colCidade: { flex: 1, minWidth: 0 },
  colUf: { width: 84 },
  trailing: { marginHorizontal: 11 },
  clear: { alignSelf: "center" },
  msg: { flexDirection: "row", alignItems: "center", gap: 6 },
  msgErro: { ...type.caption, color: ds.danger, flexShrink: 1 },
  alert: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: space[3],
    padding: space[3],
    paddingHorizontal: space[4],
    borderRadius: radius.lg,
    backgroundColor: ds.dangerSoft,
  },
  alertText: { flex: 1, fontSize: 14, lineHeight: 20, fontWeight: "700", color: ds.danger },
  foot: {
    paddingHorizontal: space[4],
    paddingTop: space[3],
    backgroundColor: ds.surface,
    borderTopWidth: 1,
    borderTopColor: ds.line,
  },
});
