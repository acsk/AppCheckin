export function descricaoTurmaComInicio(nome, horarioInicio) {
  const descricao = String(nome || '').trim();
  const horario = String(horarioInicio || '').slice(0, 5);
  const inicio = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(horario);
  if (!inicio) return descricao;

  const horas = String(Number(inicio[1]));
  const minutos = inicio[2];
  const horarioNoNome = new RegExp(
    `(^|[^\\d])0?${horas}(?::${minutos}|h${minutos === '00' ? '(?:00)?' : minutos})(?!\\d)`,
    'i',
  );
  if (horarioNoNome.test(descricao)) return descricao;
  return descricao ? `${descricao} · ${horario}` : horario;
}
