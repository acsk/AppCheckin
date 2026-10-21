import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import ts from "typescript";

const source = readFileSync(new URL("../src/utils/recordes.ts", import.meta.url), "utf8");
const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } });
const r = await import(`data:text/javascript;base64,${Buffer.from(outputText).toString("base64")}`);
const tempo = { id: 1, nome: "Tempo", tipo_valor: "tempo_ms", obrigatoria: false, direcao: "menor_melhor", unidade: "ms" };
const carga = { id: 2, nome: "Carga", tipo_valor: "decimal", obrigatoria: false, direcao: "maior_melhor", unidade: "kg" };
const reps = { id: 3, nome: "Repetições", tipo_valor: "inteiro", obrigatoria: true, direcao: "maior_melhor", unidade: "reps" };
const detalhe = { definicao: { id: 8, metrica_principal_id: 1 }, metricas: [tempo, carga, reps] };
const base = () => ({ 1: { tempo: { m: "1", s: "23", c: "4" }, texto: "" }, 2: { tempo: {}, texto: "" }, 3: { tempo: {}, texto: "12" } });

test("fotos do ranking aceitam URL completa e caminho relativo à API", () => {
  assert.equal(r.fotoRecordeUrl("/uploads/fotos/aluno.jpg", "https://api.test/v2"), "https://api.test/v2/uploads/fotos/aluno.jpg");
  assert.equal(r.fotoRecordeUrl("uploads/fotos/aluno.jpg", "https://api.test/v2/"), "https://api.test/v2/uploads/fotos/aluno.jpg");
  assert.equal(r.fotoRecordeUrl("https://cdn.test/aluno.jpg", "https://api.test/v2"), "https://cdn.test/aluno.jpg");
  assert.equal(r.fotoRecordeUrl(null, "https://api.test/v2"), null);
  assert.equal(r.fotoRecordeUrl("  ", "https://api.test/v2"), null);
});

test("tempos em minutos, segundos e centésimos, sem aceitar negativos ou frações", () => {
  assert.equal(r.partesEmTempo({ m: "1", s: "23", c: "4" }), 83400);
  assert.equal(r.partesEmTempo({ m: "", s: "00", c: "01" }), 10);
  for (const p of [{ m: "-1", s: "23", c: "45" }, { m: "1.5", s: "01", c: "02" }, { m: "", s: "60", c: "12" }, { m: "", s: "1", c: "100" }, { m: "", s: "", c: "" }, { m: "", s: "0", c: "0" }]) assert.equal(r.partesEmTempo(p), null);
});
test("formatação não perde milissegundos, horas ou terceira casa decimal", () => {
  assert.equal(r.fmtTempo(38420), "38,42");
  assert.equal(r.fmtTempo(39999), "39,999");
  assert.equal(r.fmtTempo(3725123), "1:02:05,123");
  assert.equal(r.fmtValorMetrica(0.001, carga), "0,001 kg");
});
test("datas reais e anos bissextos", () => {
  assert.equal(r.dataIso("29/02/2024"), "2024-02-29");
  assert.equal(r.dataIso("31/04/2025"), null);
  assert.equal(r.dataIso("29/02/2025"), null);
  assert.equal(r.mascaraData("21022025"), "21/02/2025");
});
test("payload mantém prova e unidades e omite opcionais vazios", () => {
  assert.deepEqual(r.payloadMarca(detalhe, base(), "01/01/2020", " Teste "), {
    definicao_id: 8, data_recorde: "2020-01-01", observacoes: "Teste",
    valores: [{ metrica_id: 1, valor: 83400 }, { metrica_id: 3, valor: 12 }],
  });
});
test("principal é sempre necessária mesmo configurada como opcional", () => {
  const inputs = base(); inputs[1].tempo = { m: "", s: "", c: "" };
  assert.throws(() => r.payloadMarca(detalhe, inputs, "01/01/2020", ""), /tempo válido/);
});
test("inteiros e decimais respeitam limites e precisão do banco", () => {
  for (const value of ["1,2", "-1", "9007199254740992", "NaN"]) assert.equal(r.valorInput({ texto: value }, reps), null);
  for (const value of ["1.2345", "1000000000", "0", "-1"]) assert.equal(r.valorInput({ texto: value }, carga), null);
  assert.equal(r.valorInput({ texto: "12,345" }, carga), 12.345);
});
test("editar data/observação preserva valores subcentesimais originais", () => {
  const marca = { valores: { 1: 39999, 3: 12 } };
  const inputs = r.inputsMarca(detalhe.metricas, marca);
  assert.equal(r.payloadMarca(detalhe, inputs, "01/01/2020", "Nova nota", marca).valores[0].valor, 39999);
  inputs[1].tempo = { m: "", s: "38", c: "99" };
  assert.equal(r.payloadMarca(detalhe, inputs, "01/01/2020", "", marca).valores[0].valor, 38990);
});
test("formulário recusa futuro, datas inválidas e observações longas", () => {
  assert.throws(() => r.payloadMarca(detalhe, base(), "01/01/2999", ""), /futuro/);
  assert.throws(() => r.payloadMarca(detalhe, base(), "31/02/2025", ""), /data válida/);
  assert.throws(() => r.payloadMarca(detalhe, base(), "01/01/2020", "x".repeat(501)), /500/);
});
test("filtro usa seis meses de calendário, incluindo fim de mês", () => {
  const marcas = [{ id: 3, data: "2025-08-30" }, { id: 1, data: "2025-02-27" }, { id: 2, data: "2025-02-28" }];
  assert.deepEqual(r.marcasNoPeriodo(marcas, "6m", new Date(2025, 7, 31)).map(m => m.id), [2, 3]);
  assert.deepEqual(r.marcasNoPeriodo(marcas, "tudo").map(m => m.id), [1, 2, 3]);
});
test("comparação funciona nas duas direções e erro respeita mensagem da API", () => {
  assert.equal(r.melhorQue(1, 2, "menor_melhor"), true);
  assert.equal(r.melhorQue(2, 1, "maior_melhor"), true);
  assert.equal(r.melhorQue(1, 1, "menor_melhor"), false);
  assert.equal(r.recordeErro({ error: "Falha da API", message: "genérico" }), "Falha da API");
  assert.equal(r.recordeErro(new Error("Sem rede")), "Sem rede");
});
