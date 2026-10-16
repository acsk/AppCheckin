import assert from "node:assert/strict";
import test from "node:test";
import {
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

test("rótulo do botão segue o método escolhido", () => {
  assert.equal(rotuloPagarMetodo("boleto"), "Pagar com boleto");
  assert.equal(rotuloPagarMetodo("cartao", "renovar"), "Renovar com cartão");
});
