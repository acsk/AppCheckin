import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import vm from "node:vm";
import ts from "typescript";

const source = readFileSync(new URL("../src/utils/clipboard.ts", import.meta.url), "utf8");
const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } });
function load(os, clipboard, browser = {}) {
  const exports = {};
  vm.runInNewContext(outputText, { exports, require: name => name === "react-native" ? { Platform: { OS: os } } : clipboard, ...browser });
  return exports.copyToClipboard;
}

test("a APK copia o payload completo pela API nativa", async () => {
  const payload = "000201-PAYLOAD-PIX-COMPLETO";
  let received;
  const copy = load("android", { setStringAsync: async value => { received = value; return true; } });
  assert.equal(await copy(payload), true);
  assert.equal(received, payload);
});

test("falha nativa e código vazio não confirmam cópia", async () => {
  const copy = load("ios", { setStringAsync: async () => { throw new Error("unavailable"); } });
  assert.equal(await copy("PIX"), false);
  assert.equal(await copy(""), false);
});

test("a web usa a Clipboard API quando disponível", async () => {
  let received;
  const copy = load("web", {}, { navigator: { clipboard: { writeText: async value => { received = value; } } } });
  assert.equal(await copy("PIX"), true);
  assert.equal(received, "PIX");
});

for (const outcome of [true, false, "throw"]) {
  test(`fallback web preserva o resultado e remove o campo temporário: ${outcome}`, async () => {
    let removed = false;
    let selected = false;
    const input = { style: {}, setAttribute() {}, select() { selected = true; } };
    const copy = load("web", {}, {
      navigator: { clipboard: { writeText: async () => { throw new Error("denied"); } } },
      document: {
        createElement: () => input,
        body: { appendChild() {}, removeChild(node) { assert.equal(node, input); removed = true; } },
        execCommand(command) { assert.equal(command, "copy"); if (outcome === "throw") throw new Error("failed"); return outcome; },
      },
    });
    assert.equal(await copy("PIX completo"), outcome === true);
    assert.equal(input.value, "PIX completo");
    assert.equal(selected, true);
    assert.equal(removed, true);
  });
}
