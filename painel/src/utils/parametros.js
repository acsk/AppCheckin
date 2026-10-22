export const normalizarBusca = (valor) => String(valor ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

export function booleanoParametro(valor) {
  if (typeof valor === 'boolean') return valor;
  if (valor === 1 || valor === 0) return valor === 1;
  const texto = String(valor ?? '').trim().toLowerCase();
  if (['1', 'true', 'sim', 'yes', 'on'].includes(texto)) return true;
  if (['0', 'false', 'nao', 'não', 'no', 'off'].includes(texto)) return false;
  return null;
}

export function opcoesParametro(param) {
  let opcoes = param.opcoes_select;
  if (typeof opcoes === 'string') { try { opcoes = JSON.parse(opcoes); } catch { return []; } }
  if (Array.isArray(opcoes)) return opcoes.map((o) => typeof o === 'object' && o !== null
    ? { value: String(o.value ?? o.valor ?? o.codigo ?? ''), label: String(o.label ?? o.nome ?? o.value ?? o.valor ?? '') }
    : { value: String(o), label: String(o) });
  return opcoes && typeof opcoes === 'object' ? Object.entries(opcoes).map(([value, label]) => ({ value, label: String(label) })) : [];
}

export function tipoParametro(param) {
  const tipo = String(param.tipo_valor ?? param.tipo ?? '').toLowerCase();
  if (['integer', 'decimal', 'json'].includes(tipo)) return tipo;
  if (opcoesParametro(param).length) return 'select';
  if (/bool|flag/.test(tipo) || (!tipo && booleanoParametro(param.valor) !== null)) return 'boolean';
  return 'text';
}

export function textoParametro(param, valor = param.valor) {
  return tipoParametro(param) === 'json' ? JSON.stringify(valor ?? null, null, 2) : String(valor ?? '');
}

export function validarParametro(param, texto) {
  const tipo = tipoParametro(param);
  if (tipo === 'integer' || tipo === 'decimal') {
    const bruto = String(texto).trim().replace(',', '.');
    if (!bruto || !/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/.test(bruto) || !Number.isFinite(Number(bruto))) return { erro: 'Use um número válido.' };
    const valor = Number(bruto);
    if (tipo === 'integer' && !Number.isSafeInteger(valor)) return { erro: 'Use um número inteiro.' };
    const minimo = param.min ?? 0;
    if (valor < Number(minimo)) return { erro: `Mínimo ${minimo}.` };
    if (param.max != null && valor > Number(param.max)) return { erro: `Máximo ${param.max}.` };
    return { valor };
  }
  if (tipo === 'json') {
    try { JSON.parse(texto); return { valor: texto }; } catch { return { erro: 'Use um JSON válido.' }; }
  }
  return { valor: texto };
}
