export type CheckinFeedbackType = "error" | "warning" | "success";

export function feedbackBloqueioCheckin(
  code?: string | null,
  message?: string | null,
): { type: CheckinFeedbackType; title: string } {
  const codigo = String(code || "").toUpperCase();
  const msg = String(message || "").toLowerCase();

  if (msg.includes("já realizou check-in") || msg.includes("já fez check-in")) {
    return { type: "warning", title: "Atenção" };
  }
  if (codigo === "LIMITE_CHECKINS_CICLO" || msg.includes("limite de check-ins")) {
    return { type: "warning", title: "Limite de check-ins" };
  }
  if (codigo === "MATRICULA_PENDENTE") {
    return { type: "warning", title: "Pagamento pendente" };
  }
  if (
    codigo.startsWith("MATRICULA_") ||
    codigo === "SEM_MATRICULA" ||
    msg.includes("matrícula") ||
    msg.includes("acesso expirou")
  ) {
    return { type: "warning", title: "Informação" };
  }

  return { type: "error", title: "Erro!" };
}
