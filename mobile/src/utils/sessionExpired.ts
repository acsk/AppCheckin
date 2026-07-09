type SessionExpiredListener = (message: string) => void;

const DEFAULT_MESSAGE =
  "Sua sessão expirou. Por favor, faça login novamente.";

let visible = false;
let currentMessage = DEFAULT_MESSAGE;
const listeners = new Set<SessionExpiredListener>();

/**
 * Notifica a UI global de que a sessão expirou.
 * Debounce: múltiplos 401 simultâneos abrem a modal só uma vez.
 */
export function notifySessionExpired(message?: string): void {
  if (visible) {
    return;
  }

  visible = true;
  currentMessage =
    typeof message === "string" && message.trim()
      ? message.trim()
      : DEFAULT_MESSAGE;

  listeners.forEach((listener) => {
    try {
      listener(currentMessage);
    } catch (error) {
      console.warn("[sessionExpired] listener falhou:", error);
    }
  });
}

export function dismissSessionExpired(): void {
  visible = false;
  currentMessage = DEFAULT_MESSAGE;
}

export function isSessionExpiredVisible(): boolean {
  return visible;
}

export function getSessionExpiredMessage(): string {
  return currentMessage;
}

export function subscribeSessionExpired(
  listener: SessionExpiredListener,
): () => void {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
}
