import appJson from "../../app.json";
import buildInfo from "../../buildInfo.json";
import { Platform } from "react-native";
import { getApiUrlRuntime } from "@/src/config/urls";

export const APP_VERSION = String((appJson as { expo?: { version?: string } })?.expo?.version ?? "1.0.0");
export const APP_BUILD_VERSION = String((buildInfo as { version?: string })?.version ?? "v1.0");
export const APP_BUILD_DATE = String((buildInfo as { buildDate?: string })?.buildDate ?? "");
export const APP_BUILD_NUMBER = Number((buildInfo as { buildNumber?: number })?.buildNumber ?? 0);

export type RemoteAppVersion = {
  app: string;
  version: string;
  release_date: string;
  /** Super admin decide no painel: true bloqueia até atualizar, false só avisa. */
  obrigatoria: boolean;
  android: AndroidRelease | null;
};

/** APK publicado: version é o expo.version (app.json) do build. */
export type AndroidRelease = {
  version: string;
  apk_url: string;
  obrigatoria: boolean;
  /** Abaixo dela o APK fica bloqueado (Super Admin → Versões). */
  versao_minima?: string | null;
};

// Só a web recarrega o bundle; no APK não existe window.location (quebrava o login).
const isWeb = Platform.OS === "web";

export function isProductionRuntime(): boolean {
  const appEnv = (process.env.EXPO_PUBLIC_APP_ENV || "").toLowerCase();
  if (appEnv === "production") return true;
  if (appEnv === "development") return false;

  if (typeof window !== "undefined" && window.location?.hostname) {
    const hostname = window.location.hostname;
    const isLocal =
      hostname === "localhost" ||
      hostname === "127.0.0.1" ||
      hostname.startsWith("192.168.") ||
      hostname.startsWith("10.") ||
      hostname.endsWith(".local");
    return !isLocal;
  }

  return false;
}

export function compareBuildVersions(left: string, right: string): number {
  const normalize = (value: string) =>
    String(value || "")
      .trim()
      .replace(/^v/i, "")
      .split(".")
      .map((part) => Number.parseInt(part, 10) || 0);

  const leftParts = normalize(left);
  const rightParts = normalize(right);
  const max = Math.max(leftParts.length, rightParts.length);

  for (let i = 0; i < max; i++) {
    const a = leftParts[i] ?? 0;
    const b = rightParts[i] ?? 0;
    if (a > b) return 1;
    if (a < b) return -1;
  }

  return 0;
}

export function formatDateToPtBr(dateStr: string): string {
  if (!dateStr || dateStr === "-") return dateStr;
  const match = String(dateStr).match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!match) return dateStr;
  const [, year, month, day] = match;
  return `${day}/${month}/${year}`;
}

const AUTO_RELOAD_KEY = "appcheckin_auto_reload_for";

export function startPwaUpdateWatcher(): () => void {
  if (typeof window === "undefined" || !("serviceWorker" in navigator)) {
    return () => {};
  }

  let cancelled = false;
  let reloading = false;

  const onControllerChange = () => {
    if (reloading || cancelled) return;
    reloading = true;
    window.location.reload();
  };

  navigator.serviceWorker.addEventListener("controllerchange", onControllerChange);

  let intervalId = 0;
  const onVisible = () => {
    if (document.visibilityState === "visible" && !cancelled) {
      navigator.serviceWorker.getRegistration("/sw.js").then((reg) => {
        void reg?.update();
      });
    }
  };

  navigator.serviceWorker
    .register("/sw.js", { updateViaCache: "none" })
    .then((reg) => {
      const tick = () => {
        if (!cancelled) void reg.update();
      };
      tick();
      intervalId = window.setInterval(tick, 60 * 1000);
      document.addEventListener("visibilitychange", onVisible);
    })
    .catch(() => {});

  return () => {
    cancelled = true;
    if (intervalId) window.clearInterval(intervalId);
    document.removeEventListener("visibilitychange", onVisible);
    navigator.serviceWorker.removeEventListener("controllerchange", onControllerChange);
  };
}

/**
 * Se o servidor tiver versão mais nova, recarrega uma vez (como o AppEscola).
 * Se já tentou e o bundle ainda é antigo, retorna "blocked".
 */
export async function ensureAppIsCurrent(): Promise<"ok" | "reloading" | "blocked"> {
  if (!isWeb) return "ok";
  const remote = await fetchMobileVersion();
  if (!remote) return "ok";
  if (compareBuildVersions(remote.version, APP_BUILD_VERSION) <= 0) return "ok";
  // Atualização opcional: não recarrega no meio do login; o aviso aparece no ForceUpdateGate
  if (!remote.obrigatoria) return "ok";

  try {
    const last =
      typeof sessionStorage !== "undefined"
        ? sessionStorage.getItem(AUTO_RELOAD_KEY)
        : null;
    if (last !== remote.version) {
      sessionStorage?.setItem(AUTO_RELOAD_KEY, remote.version);
      await forceReloadApp();
      return "reloading";
    }
  } catch {
    await forceReloadApp();
    return "reloading";
  }

  return "blocked";
}

/** Limpa cache/PWA e recarrega o app web para buscar a versão nova. */
export async function forceReloadApp(): Promise<void> {
  if (!isWeb || typeof window === "undefined" || !window.location) return;

  try {
    if ("caches" in window) {
      const keys = await caches.keys();
      await Promise.all(keys.map((key) => caches.delete(key)));
    }
    if ("serviceWorker" in navigator) {
      const regs = await navigator.serviceWorker.getRegistrations();
      await Promise.all(
        regs.map(async (reg) => {
          reg.waiting?.postMessage("SKIP_WAITING");
          await reg.update();
        }),
      );
      await navigator.serviceWorker.register("/sw.js").catch(() => null);
    }
  } catch {
    /* recarrega mesmo se o cache falhar */
  }

  const url = new URL(window.location.href);
  url.searchParams.set("_v", String(Date.now()));
  window.location.replace(url.toString());
}

export async function fetchMobileVersion(): Promise<RemoteAppVersion | null> {
  try {
    const base = String(getApiUrlRuntime() || "").replace(/\/$/, "");
    const url = `${base}/version/mobile`;
    const response = await fetch(url, {
      method: "GET",
      cache: "no-store",
      headers: { Accept: "application/json", "Cache-Control": "no-cache" },
    });
    if (!response.ok) return null;
    const raw: any = await response.json().catch(() => ({}));
    const body = raw?.data ?? raw?.body ?? raw ?? {};
    if (!body?.version) return null;
    const android = body.android?.version && body.android?.apk_url
      ? {
          version: String(body.android.version),
          apk_url: String(body.android.apk_url),
          obrigatoria: !!body.android.obrigatoria,
          versao_minima: body.android.versao_minima ? String(body.android.versao_minima) : null,
        }
      : null;
    return {
      app: String(body.app ?? "mobile"),
      version: String(body.version),
      release_date: String(body.release_date ?? ""),
      obrigatoria: body.obrigatoria !== false,
      android,
    };
  } catch {
    return null;
  }
}
