/**
 * Formata datas ISO (YYYY-MM-DD) para dd/mm/YYYY sem timezone/locale.
 * Evita 07/11/2026 quando o correto é 11/07/2026.
 */
export function formatDateBr(value?: string | null): string | null {
  if (!value) return null;
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value).trim());
  if (match) {
    return `${match[3]}/${match[2]}/${match[1]}`;
  }
  return null;
}

/**
 * Se a API mandar data_vencimento em ISO, corrige "Vencimento: xx/xx/xxxx" na mensagem.
 */
export function normalizeVencimentoMessage(
  message: string,
  dataVencimento?: string | null,
): string {
  const br = formatDateBr(dataVencimento);
  if (!br) return message;

  const replaced = String(message || "").replace(
    /Vencimento:\s*\d{1,2}\/\d{1,2}\/\d{4}/i,
    `Vencimento: ${br}`,
  );

  if (replaced !== message) return replaced;

  // Mensagens "expirou em dd/mm/yyyy"
  return String(message || "").replace(
    /expirou em\s*\d{1,2}\/\d{1,2}\/\d{4}/i,
    `expirou em ${br}`,
  );
}
