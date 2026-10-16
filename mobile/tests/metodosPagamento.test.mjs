import assert from "node:assert/strict";
import test from "node:test";
import {
  nomeProvedorPagamento,
  opcoesPagamentoVisiveis,
  rotuloPagarMetodo,
} from "../src/utils/metodosPagamento.ts";

test("mostra só PIX quando o provedor não habilitou os outros", () => {
  assert.deepEqual(opcoesPagamentoVisiveis(["pix"], true), ["pix"]);
});

test("mostra boleto e cartão quando o ciclo traz esses métodos", () => {
  assert.deepEqual(opcoesPagamentoVisiveis(["pix", "boleto", "cartao", "checkout"]), [
    "pix",
    "boleto",
    "cartao",
  ]);
});

test("checkout sozinho não vira cartão", () => {
  assert.deepEqual(opcoesPagamentoVisiveis(["checkout"]), []);
});

test("nome do provedor vem do id ou da URL", () => {
  assert.equal(nomeProvedorPagamento("asaas"), "Asaas");
  assert.equal(nomeProvedorPagamento("mercadopago"), "Mercado Pago");
  assert.equal(
    nomeProvedorPagamento(null, "https://www.asaas.com/c/abc"),
    "Asaas",
  );
});

test("rótulo do botão segue o método escolhido", () => {
  assert.equal(rotuloPagarMetodo("boleto"), "Pagar com boleto");
  assert.equal(rotuloPagarMetodo("cartao", "renovar"), "Renovar com cartão");
});
