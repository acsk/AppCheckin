export function extrairExcecoes(tenants, chave) {
  return Object.fromEntries(Object.entries(tenants || {})
    .filter(([, valores]) => valores[chave] !== undefined)
    .map(([id, valores]) => [id, valores[chave]]));
}

// Prepara tudo antes da primeira gravação: valores inválidos não geram aplicação parcial.
export function prepararExcecoes(def, originais, editadas) {
  const normalizadas = {};
  for (const [id, valor] of Object.entries(editadas)) {
    if (def.tipo === 'inteiro') {
      const numero = Number(valor);
      if (String(valor).trim() === '' || !Number.isInteger(numero) || numero < def.min || numero > def.max) {
        throw new Error(`Use um valor entre ${def.min} e ${def.max} para a academia ${id}.`);
      }
      normalizadas[id] = numero;
    } else {
      if (def.tipo === 'texto' && [...String(valor)].length > def.max) {
        throw new Error(`Use no máximo ${def.max} caracteres para a academia ${id}.`);
      }
      normalizadas[id] = valor;
    }
  }
  return [...new Set([...Object.keys(originais), ...Object.keys(normalizadas)])]
    .filter((id) => originais[id] !== normalizadas[id])
    .map((id) => ({ tenantId: Number(id), valor: normalizadas[id] ?? null }));
}
