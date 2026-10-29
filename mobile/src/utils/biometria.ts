import AsyncStorage from "@react-native-async-storage/async-storage";
import * as LocalAuthentication from "expo-local-authentication";
import * as SecureStore from "expo-secure-store";
import { Platform } from "react-native";

/**
 * Entrada por biometria (APK/iOS). E-mail e senha ficam num item do SecureStore preso à biometria
 * do aparelho (requireAuthentication): só saem depois da digital/rosto. Cadastrar uma biometria nova
 * no aparelho invalida o item; aí a leitura falha e a entrada por biometria é desligada.
 */
const CREDENCIAIS = "appcheckin_biometria_credenciais";
// Só o e-mail, para saber sem pedir a biometria se ela está ligada (e para qual conta)
const ATIVA = "@appcheckin:biometria_email";
const RECUSADA = "@appcheckin:biometria_recusada";

const OPCOES: SecureStore.SecureStoreOptions = {
  requireAuthentication: true,
  keychainAccessible: SecureStore.WHEN_PASSCODE_SET_THIS_DEVICE_ONLY,
};

export type Credenciais = { email: string; senha: string };

/** Aparelho com biometria cadastrada e o app nativo (não vale para a web). */
export async function biometriaDisponivel(): Promise<boolean> {
  if (Platform.OS === "web") return false;
  try {
    return (await LocalAuthentication.hasHardwareAsync())
      && (await LocalAuthentication.isEnrolledAsync())
      && SecureStore.canUseBiometricAuthentication();
  } catch {
    return false;
  }
}

/** E-mail da conta com biometria ligada neste aparelho, ou null. */
export async function emailComBiometria(): Promise<string | null> {
  try {
    return await AsyncStorage.getItem(ATIVA);
  } catch {
    return null;
  }
}

export async function biometriaRecusada(): Promise<boolean> {
  try {
    return (await AsyncStorage.getItem(RECUSADA)) === "1";
  } catch {
    return false;
  }
}

/** Liga a biometria (o aparelho pede a digital/rosto para gravar). false = cancelado ou falhou. */
export async function ativarBiometria(c: Credenciais): Promise<boolean> {
  try {
    await SecureStore.setItemAsync(CREDENCIAIS, JSON.stringify(c), { ...OPCOES, authenticationPrompt: "Confirme para ativar a entrada por biometria" });
    await AsyncStorage.setItem(ATIVA, c.email.trim().toLowerCase());
    await AsyncStorage.removeItem(RECUSADA);
    return true;
  } catch {
    return false;
  }
}

/** "Agora não": não pergunta de novo até a pessoa ligar pelo login. */
export async function recusarBiometria(): Promise<void> {
  await AsyncStorage.setItem(RECUSADA, "1").catch(() => {});
}

export async function desativarBiometria(): Promise<void> {
  await SecureStore.deleteItemAsync(CREDENCIAIS).catch(() => {});
  await AsyncStorage.removeItem(ATIVA).catch(() => {});
}

/**
 * Pede a biometria e devolve as credenciais. null = cancelado. Item ilegível (biometria nova no
 * aparelho, dado corrompido) desliga a biometria e devolve null com invalidada = true.
 */
export async function lerCredenciais(): Promise<{ credenciais: Credenciais | null; invalidada: boolean }> {
  try {
    const bruto = await SecureStore.getItemAsync(CREDENCIAIS, { ...OPCOES, authenticationPrompt: "Entrar no AppCheckin" });
    const c = bruto ? (JSON.parse(bruto) as Credenciais) : null;
    if (c?.email && c?.senha) return { credenciais: c, invalidada: false };
    await desativarBiometria();
    return { credenciais: null, invalidada: true };
  } catch (e) {
    // Cancelar o diálogo também cai aqui; só desliga quando não foi cancelamento
    const msg = String((e as Error)?.message || e).toLowerCase();
    if (msg.includes("cancel")) return { credenciais: null, invalidada: false };
    await desativarBiometria();
    return { credenciais: null, invalidada: true };
  }
}
