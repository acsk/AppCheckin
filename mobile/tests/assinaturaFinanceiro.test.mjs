import assert from "node:assert/strict";
import test from "node:test";
import {
  criarCardsAssinaturas,
  pagamentosVisiveis,
  podePagarAssinatura,
} from "../src/utils/assinaturaFinanceiro.ts";

const hoje = "2026-10-07";
const pixAnterior = {
  id: 564,
  valor: 200,
  data_vencimento: "2026-05-21",
  data_pagamento: "2026-05-21",
  status: "Pago",
  status_pagamento_id: 2,
  forma_pagamento: "Pix",
};
const boletoAtual = {
  id: 1351,
  valor: 360,
  data_vencimento: hoje,
  data_pagamento: hoje,
  status: "Pago",
  status_pagamento_id: 2,
  forma_pagamento: "Boleto",
};
const assinatura = {
  id: 128,
  matricula_id: 337,
  status: { codigo: "ativa", nome: "Ativa" },
  valor: 360,
  data_inicio: hoje,
  data_fim: "2027-02-07",
  proxima_cobranca: "2027-02-07",
  ultima_cobranca: hoje,
  pode_pagar: false,
  pagamentos: [pixAnterior, boletoAtual],
};

test("matrícula 337: um card atual de 360, com histórico separado de 360 e 200", () => {
  const cards = criarCardsAssinaturas([assinatura], hoje);
  assert.equal(cards.length, 1);
  assert.equal(cards[0].key, "assinatura-128");
  assert.equal(cards[0].assinatura.valor, 360);
  assert.equal(cards[0].assinatura.status.nome, "Ativa");
  assert.equal(cards[0].assinatura.proxima_cobranca, "2027-02-07");
  assert.equal(cards[0].assinatura.ultima_cobranca, hoje);
  assert.deepEqual(cards[0].pagamentos, [boletoAtual, pixAnterior]);
  assert.equal(podePagarAssinatura(assinatura, cards[0].pagamentos), false);
  assert.deepEqual(assinatura.pagamentos, [pixAnterior, boletoAtual]);
});

test("oculta parcela futura e cancelada sem remover os pagamentos quitados", () => {
  const futura = {
    id: 1354,
    valor: 360,
    data_vencimento: "2027-02-07",
    status_pagamento_id: 1,
  };
  const cancelada = {
    id: 565,
    valor: 200,
    data_vencimento: "2026-09-21",
    status_pagamento_id: 4,
  };
  assert.deepEqual(
    pagamentosVisiveis([pixAnterior, futura, cancelada, boletoAtual], hoje),
    [boletoAtual, pixAnterior],
  );
});

test("mantém cobrança vencida/hoje e permite pagar mesmo com histórico quitado", () => {
  const aberta = {
    id: 1355,
    valor: 360,
    data_vencimento: hoje,
    status_pagamento_id: 1,
  };
  const pagamentos = pagamentosVisiveis([pixAnterior, aberta], hoje);
  assert.deepEqual(pagamentos, [aberta, pixAnterior]);
  assert.equal(
    podePagarAssinatura({ ...assinatura, pode_pagar: true }, pagamentos),
    true,
  );
});

test("permite renovação autorizada sem confundir histórico pago com pendência", () => {
  assert.equal(
    podePagarAssinatura(
      { ...assinatura, pode_pagar: true, pode_renovar: true },
      [boletoAtual, pixAnterior],
    ),
    true,
  );
  assert.equal(
    podePagarAssinatura(
      { ...assinatura, pode_pagar: true },
      [boletoAtual, pixAnterior],
    ),
    false,
  );
});

test("mantém assinatura ativa sem pagamentos e esconde pendente sem fatura", () => {
  const semPagamentos = { ...assinatura, pagamentos: null };
  assert.equal(criarCardsAssinaturas([semPagamentos], hoje).length, 1);
  assert.equal(
    criarCardsAssinaturas(
      [{ ...semPagamentos, status: { codigo: "pendente" } }],
      hoje,
    ).length,
    0,
  );
});

test("checkout pendente precisa de link; status pago numérico dispensa data", () => {
  const aberta = { id: 1, valor: 360, status_pagamento_id: 1 };
  const pendente = {
    ...assinatura,
    status: { codigo: "pendente" },
    pode_pagar: true,
  };
  assert.equal(podePagarAssinatura(pendente, [aberta]), false);
  assert.equal(
    podePagarAssinatura({ ...pendente, payment_url: "https://example.com" }, [aberta]),
    true,
  );
  assert.equal(
    podePagarAssinatura(assinatura, [aberta]),
    false,
  );
  assert.equal(
    pagamentosVisiveis(
      [{ id: 2, valor: 360, status_pagamento_id: 2, data_vencimento: "2027-02-07" }],
      hoje,
    ).length,
    1,
  );
});

test("desempata histórico pelo id e mantém cobranças abertas sem data", () => {
  const empatado = { ...boletoAtual, id: 1352 };
  assert.deepEqual(
    pagamentosVisiveis([boletoAtual, empatado], hoje),
    [empatado, boletoAtual],
  );
  assert.equal(
    pagamentosVisiveis([{ id: 3, valor: 360, status_pagamento_id: 3 }], hoje).length,
    1,
  );
});
