const quantidade = (valor) => Array.isArray(valor) ? valor.length : 0;
const soma = (lista, campo) => (Array.isArray(lista) ? lista : []).reduce((total, item) => total + (Number(item[campo]) || 0), 0);

export function resumoExclusao(data = {}) {
  const r = data.resumo || {};
  const pagamentos = Number(r.total_pagamentos_plano ?? quantidade(data.pagamentos_plano));
  const pagamentosProvedor = Number(r.total_pagamentos_provedor ?? quantidade(data.pagamentos_mercadopago));
  const assinaturas = Number(r.total_assinaturas ?? quantidade(data.assinaturas));
  const assinaturasProvedor = Number(r.total_assinaturas_mercadopago ?? quantidade(data.assinaturas_mercadopago));
  const valorPlano = Number(r.valor_total_pagamentos_plano ?? soma(data.pagamentos_plano, 'valor'));
  const valorProvedor = Number(r.valor_total_pagamentos_provedor ?? soma(data.pagamentos_mercadopago, 'transaction_amount'));
  return { pagamentos, pagamentosProvedor, assinaturas, assinaturasProvedor, valorPlano, valorProvedor,
    excluidos: pagamentos + pagamentosProvedor + assinaturasProvedor,
    exigeNumero: valorPlano > 0 || valorProvedor > 0 };
}

export function confirmaExclusao(id, digitado, exigeNumero) {
  if (id == null || String(id).trim() === '') return false;
  return !exigeNumero || String(digitado).trim().replace(/^#/, '') === String(id);
}
