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

const OPCOES: SecureStore.SecureStoreOptions = {
  requireAuthentication: true,
  keychainAccessible: SecureStore.WHEN_PASSCODE_SET_THIS_DEVICE_ONLY,
};

export type Credenciais = { email: string; senha: string };

/**
 * web: navegador (não há cofre do aparelho); sem_sensor; sem_cadastro: nenhuma digital/rosto no
 * aparelho; fraca: só biometria "fraca" (rosto em muitos Android) — o cofre exige a forte (digital).
 */
export type MotivoBiometria = "web" | "sem_sensor" | "sem_cadastro" | "fraca" | null;

export async function estadoBiometria(): Promise<{ disponivel: boolean; motivo: MotivoBiometria }> {
  if (Platform.OS === "web") return { disponivel: false, motivo: "web" };
  try {
    if (!(await LocalAuthentication.hasHardwareAsync())) return { disponivel: false, motivo: "sem_sensor" };
    if (!(await LocalAuthentication.isEnrolledAsync())) return { disponivel: false, motivo: "sem_cadastro" };
    if (!SecureStore.canUseBiometricAuthentication()) {
      const nivel = await LocalAuthentication.getEnrolledLevelAsync().catch(() => null);
      return { disponivel: false, motivo: nivel === LocalAuthentication.SecurityLevel.BIOMETRIC_WEAK ? "fraca" : "sem_cadastro" };
    }
    return { disponivel: true, motivo: null };
  } catch (e) {
    console.warn("[biometria] não deu para verificar o aparelho", e);
    return { disponivel: false, motivo: "sem_sensor" };
  }
}

/** Texto para o aluno quando o aparelho tem sensor mas a biometria não pode ser usada. */
export function textoMotivoBiometria(m: MotivoBiometria): string | null {
  if (m === "sem_cadastro") return "Para entrar com biometria, cadastre uma digital nas configurações do aparelho.";
  if (m === "fraca") return "O reconhecimento facial deste aparelho não é aceito para entrar. Cadastre uma digital para usar a biometria.";
  return null;
}

/** Aparelho com biometria cadastrada e o app nativo (não vale para a web). */
export async function biometriaDisponivel(): Promise<boolean> {
  return (await estadoBiometria()).disponivel;
}

/** E-mail da conta com biometria ligada neste aparelho, ou null. */
export async function emailComBiometria(): Promise<string | null> {
  try {
    return await AsyncStorage.getItem(ATIVA);
  } catch {
    return null;
  }
}

/** Liga a biometria (o aparelho pede a digital/rosto para gravar). false = cancelado ou falhou. */
export async function ativarBiometria(c: Credenciais): Promise<boolean> {
  try {
    await SecureStore.setItemAsync(CREDENCIAIS, JSON.stringify(c), { ...OPCOES, authenticationPrompt: "Confirme para ativar a entrada por biometria" });
    await AsyncStorage.setItem(ATIVA, c.email.trim().toLowerCase());
    return true;
  } catch (e) {
    console.warn("[biometria] não deu para ativar", e);
    return false;
  }
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
