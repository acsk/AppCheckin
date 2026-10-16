import { useFocusEffect } from "@react-navigation/native";
import { useCallback, useEffect, useRef } from "react";
import { AppState, Platform } from "react-native";

type Options = {
  /** Recarrega também a cada N ms enquanto a tela está em foco e visível. */
  intervalMs?: number;
  /** Intervalo mínimo entre duas atualizações automáticas (evita rajadas). Padrão 10 s. */
  minGapMs?: number;
  /** Desliga temporariamente (ex.: modal aberto, formulário em edição). */
  enabled?: boolean;
  /** Recarrega também ao voltar para a tela pela navegação (para telas que só carregam uma vez). */
  onScreenFocus?: boolean;
};

/**
 * Mantém os dados da tela atualizados sozinhos, como o React Query faz no appEscola:
 * - ao voltar para o app (segundo plano → ativo) ou para a aba do navegador;
 * - opcionalmente a cada `intervalMs`, só enquanto a tela está em foco e visível.
 *
 * A carga inicial e a de "entrar na tela" continuam com a própria tela (useFocusEffect);
 * este hook só cobre o que acontece depois. `refresh` deve atualizar em silêncio
 * (sem trocar a tela por um carregando).
 */
export function useRefreshOnFocus(refresh: () => unknown, { intervalMs, minGapMs = 10_000, enabled = true, onScreenFocus = false }: Options = {}) {
  const focused = useRef(false);
  const last = useRef(Date.now());
  const refreshRef = useRef(refresh);
  refreshRef.current = refresh;

  const run = useCallback(
    (force = false) => {
      if (!enabled || !focused.current) return;
      const now = Date.now();
      if (!force && now - last.current < minGapMs) return;
      last.current = now;
      try {
        const r = refreshRef.current();
        if (r && typeof (r as Promise<unknown>).catch === "function") (r as Promise<unknown>).catch(() => {});
      } catch {
        // atualização silenciosa: erros ficam com a própria tela
      }
    },
    [enabled, minGapMs],
  );

  // Foco da tela (navegação). Marca o horário para não repetir a carga que a tela já faz.
  const firstFocus = useRef(true);
  useFocusEffect(
    useCallback(() => {
      focused.current = true;
      if (onScreenFocus && !firstFocus.current) {
        run(true);
      } else {
        last.current = Date.now();
      }
      firstFocus.current = false;
      return () => {
        focused.current = false;
      };
    }, [onScreenFocus, run]),
  );

  // Volta do segundo plano (nativo) e volta para a aba/janela (web).
  useEffect(() => {
    const sub = AppState.addEventListener("change", (state) => {
      if (state === "active") run();
    });
    let removeWeb: (() => void) | undefined;
    if (Platform.OS === "web" && typeof document !== "undefined") {
      const onVisible = () => {
        if (document.visibilityState === "visible") run();
      };
      const onFocus = () => run();
      document.addEventListener("visibilitychange", onVisible);
      window.addEventListener("focus", onFocus);
      removeWeb = () => {
        document.removeEventListener("visibilitychange", onVisible);
        window.removeEventListener("focus", onFocus);
      };
    }
    return () => {
      sub.remove();
      removeWeb?.();
    };
  }, [run]);

  // Atualização periódica enquanto a tela está aberta e visível.
  useEffect(() => {
    if (!intervalMs || !enabled) return;
    const t = setInterval(() => {
      const hidden =
        Platform.OS === "web" && typeof document !== "undefined"
          ? document.visibilityState !== "visible"
          : AppState.currentState !== "active";
      if (!hidden) run(true);
    }, intervalMs);
    return () => clearInterval(t);
  }, [intervalMs, enabled, run]);
}
