/**
 * Utilitários para tratamento de strings UTF-8/acentos em React Native.
 * Observação: strings JS são UTF-16 internamente; aqui focamos em normalização e correções comuns.
 */

/** Normaliza a string para forma NFC e remove caracteres inválidos (�). */
export function normalizeUtf8(input: unknown): string {
  if (input == null) return '';
  const str = String(input);
  try {
    return str.normalize('NFC').replace(/\uFFFD/g, '');
  } catch {
    return str.replace(/\uFFFD/g, '');
  }
}

/** Remove acentos/diacríticos mantendo letras (ex.: Café -> Cafe). */
export function removeDiacritics(input: unknown): string {
  if (input == null) return '';
  const str = String(input);
  try {
    return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  } catch {
    return str;
  }
}

/** Converte string Latin1 (ISO-8859-1) que veio como UTF-8 errado para UTF-8 correta. */
export function latin1ToUtf8(misencoded: string): string {
  try {
    // decodeURIComponent(escape(x)) converte de Latin1->UTF-8 (hack histórico)
    return decodeURIComponent(escape(misencoded));
  } catch {
    return misencoded;
  }
}

/** Converte string UTF-8 para Latin1 (útil para interoperabilidade específica). */
export function utf8ToLatin1(str: string): string {
  try {
    // unescape(encodeURIComponent(x)) converte UTF-8->Latin1 (se possível)
    return unescape(encodeURIComponent(str));
  } catch {
    return str;
  }
}

/** decodeURIComponent com proteção contra erros. */
export function safeDecodeURIComponent(value: string): string {
  try {
    return decodeURIComponent(value);
  } catch {
    return value;
  }
}

/** Gera um slug simples (minúsculas, sem acentos, separador -). */
export function toSlug(input: unknown): string {
  const base = removeDiacritics(normalizeUtf8(input)).toLowerCase();
  return base.replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
}
