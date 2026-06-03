/**
 * Abreviações dos dias (domingo = 0).
 * Evita "Sex" — no mobile web (pt-BR) autocorreção/tradução vira "sexo".
 */
export const WEEKDAY_ABBREV = [
  "Dom",
  "Seg",
  "Ter",
  "Qua",
  "Qui",
  "Sext",
  "Sáb",
] as const;

export function weekdayAbbrev(
  date: Date,
  options?: { uppercase?: boolean },
): string {
  const label = WEEKDAY_ABBREV[date.getDay()] ?? "";
  return options?.uppercase ? label.toUpperCase() : label;
}
