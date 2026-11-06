import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import { useRef, useState } from "react";
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import AppHeader from "../../src/components/ds/AppHeader";
import Button from "../../src/components/ds/Button";
import { cardStyle } from "../../src/components/ds/Section";
import SelectRow from "../../src/components/ds/SelectRow";
import TextField from "../../src/components/ds/TextField";
import { ds, radius, space, type } from "../../src/components/ds/tokens";
import { dependenteService } from "../../src/services/dependenteService";

// Login do dependente (criança sem e-mail): e-mail do responsável → escolhe o perfil → PIN que o
// responsável criou em Conta › Dependentes.

const isValidEmail = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);

export default function LoginDependenteScreen() {
  const router = useRouter();
  const pinRef = useRef(null);
  const [etapa, setEtapa] = useState("email"); // email → perfil → pin → academia
  const [email, setEmail] = useState("");
  const [perfis, setPerfis] = useState([]);
  const [perfil, setPerfil] = useState(null);
  const [pin, setPin] = useState("");
  const [tenants, setTenants] = useState([]);
  const [aviso, setAviso] = useState("");
  const [erro, setErro] = useState("");
  const [loading, setLoading] = useState(false);

  const voltar = () => {
    setErro("");
    if (etapa === "academia") setEtapa("pin");
    else if (etapa === "pin") {
      setPin("");
      setEtapa(perfis.length > 1 ? "perfil" : "email");
    } else if (etapa === "perfil") setEtapa("email");
    else if (router.canGoBack?.()) router.back();
    else router.replace("/(auth)/login");
  };

  const buscarPerfis = async () => {
    const e = email.trim().toLowerCase();
    if (!isValidEmail(e)) {
      setErro("Digite o e-mail do seu responsável. Exemplo: nome@email.com");
      return;
    }
    setLoading(true);
    setErro("");
    setAviso("");
    try {
      const r = await dependenteService.perfisPorEmail(e);
      setPerfis(r.perfis);
      if (!r.perfis.length) {
        setAviso(r.message || "Nenhum perfil liberado para este e-mail.");
        return;
      }
      if (r.perfis.length === 1) {
        setPerfil(r.perfis[0]);
        setEtapa("pin");
        setTimeout(() => pinRef.current?.focus?.(), 50);
      } else {
        setEtapa("perfil");
      }
    } catch (err) {
      setErro(err?.message || "Não foi possível buscar os perfis.");
    } finally {
      setLoading(false);
    }
  };

  const entrar = async (tenantId) => {
    if (!/^\d{4,6}$/.test(pin)) {
      setErro("O PIN tem de 4 a 6 números.");
      return;
    }
    setLoading(true);
    setErro("");
    try {
      const r = await dependenteService.loginComPin({
        email: email.trim().toLowerCase(),
        dependente_id: perfil.id,
        pin,
        tenant_id: tenantId,
      });
      if (r?.requires_tenant_selection) {
        setTenants(r.tenants || []);
        setEtapa("academia");
        return;
      }
      if (r?.token) router.replace("/(tabs)");
    } catch (err) {
      setPin("");
      setErro(err?.message || "Não foi possível entrar.");
    } finally {
      setLoading(false);
    }
  };

  let corpo;
  if (etapa === "email") {
    corpo = (
      <>
        <View>
          <Text style={styles.title}>Entrar como dependente</Text>
          <Text style={styles.text}>
            Para quem não tem e-mail próprio. Digite o e-mail do seu responsável. Ele cria o seu PIN no app dele, em Conta › Dependentes.
          </Text>
        </View>
        <View style={[cardStyle, styles.fields]}>
          <TextField
            label="E-mail do responsável"
            icon="mail"
            placeholder="nome@email.com"
            keyboardType="email-address"
            autoCapitalize="none"
            autoCorrect={false}
            value={email}
            onChangeText={(v) => {
              setEmail(v);
              setErro("");
              setAviso("");
            }}
            onSubmitEditing={() => void buscarPerfis()}
            returnKeyType="next"
            editable={!loading}
          />
        </View>
        {aviso ? (
          <View style={styles.aviso}>
            <Feather name="info" size={16} color={ds.inkMuted} />
            <Text style={styles.avisoText}>{aviso}</Text>
          </View>
        ) : null}
        <Button size="lg" block iconRight="arrow-right" loading={loading} onPress={() => void buscarPerfis()}>
          Continuar
        </Button>
      </>
    );
  } else if (etapa === "perfil") {
    corpo = (
      <>
        <View>
          <Text style={styles.title}>Quem é você?</Text>
          <Text style={styles.text}>Escolha o seu perfil.</Text>
        </View>
        <View style={styles.list} accessibilityRole="radiogroup">
          {perfis.map((p) => (
            <SelectRow
              key={p.id}
              title={p.nome}
              selected={perfil?.id === p.id}
              onSelect={() => {
                setPerfil(p);
                setPin("");
                setEtapa("pin");
                setTimeout(() => pinRef.current?.focus?.(), 50);
              }}
            />
          ))}
        </View>
      </>
    );
  } else if (etapa === "pin") {
    corpo = (
      <>
        <View>
          <Text style={styles.title}>Oi, {perfil?.nome}!</Text>
          <Text style={styles.text}>Digite o seu PIN. Se esqueceu, peça para o seu responsável criar outro.</Text>
        </View>
        <View style={[cardStyle, styles.fields]}>
          <Text style={styles.label}>PIN</Text>
          <TextInput
            ref={pinRef}
            style={styles.pin}
            value={pin}
            onChangeText={(v) => {
              setPin(v.replace(/\D/g, "").slice(0, 6));
              setErro("");
            }}
            keyboardType="number-pad"
            secureTextEntry
            maxLength={6}
            placeholder="••••"
            placeholderTextColor={ds.inkMuted}
            accessibilityLabel="PIN"
            onSubmitEditing={() => void entrar()}
            editable={!loading}
          />
        </View>
        <Button size="lg" block icon="log-in" loading={loading} onPress={() => void entrar()}>
          Entrar
        </Button>
      </>
    );
  } else {
    corpo = (
      <>
        <View>
          <Text style={styles.title}>Qual academia?</Text>
          <Text style={styles.text}>Você treina em mais de uma.</Text>
        </View>
        <View style={styles.list}>
          {tenants.map((t) => (
            <SelectRow key={t.tenant.id} title={t.tenant.nome} selected={false} onSelect={() => void entrar(t.tenant.id)} />
          ))}
        </View>
      </>
    );
  }

  return (
    <SafeAreaView style={styles.container} edges={["left", "right", "bottom"]}>
      <AppHeader title="Sou dependente" subtitle="Entrar com o e-mail do responsável" onBack={voltar} />
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === "ios" ? "padding" : undefined}>
        <ScrollView contentContainerStyle={styles.body} keyboardShouldPersistTaps="handled">
          {corpo}
          {erro ? (
            <View style={styles.msg} accessibilityLiveRegion="polite">
              <Feather name="alert-circle" size={16} color={ds.danger} />
              <Text style={styles.msgErro}>{erro}</Text>
            </View>
          ) : null}
          <Pressable onPress={() => router.replace("/(auth)/login")} accessibilityRole="link" style={styles.voltarLogin}>
            <Text style={styles.link}>Tenho e-mail próprio: entrar normalmente</Text>
          </Pressable>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: ds.ground },
  body: { flexGrow: 1, padding: space[4], paddingTop: space[5], gap: space[4] },
  title: { ...type.title, color: ds.ink },
  text: { ...type.body, color: ds.inkMuted, marginTop: 4 },
  fields: { padding: space[4], gap: space[3] },
  label: { ...type.label, color: ds.inkMuted },
  pin: {
    height: 56,
    borderWidth: 1.5,
    borderColor: ds.lineStrong,
    borderRadius: radius.md,
    paddingHorizontal: space[4],
    fontSize: 28,
    letterSpacing: 12,
    color: ds.ink,
    textAlign: "center",
    backgroundColor: ds.surface,
  },
  list: { gap: space[2] },
  aviso: { flexDirection: "row", gap: space[2], alignItems: "flex-start", padding: space[3], borderRadius: radius.md, backgroundColor: ds.surfaceSunken },
  avisoText: { ...type.caption, color: ds.ink, flex: 1 },
  msg: { flexDirection: "row", gap: space[2], alignItems: "center" },
  msgErro: { ...type.caption, color: ds.danger, flex: 1 },
  voltarLogin: { alignSelf: "center", paddingVertical: space[3] },
  link: { ...type.caption, color: ds.brandInk, textDecorationLine: "underline" },
});
