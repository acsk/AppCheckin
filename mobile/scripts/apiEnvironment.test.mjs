import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import vm from "node:vm";
import ts from "typescript";

function loadEnvironment(os, isDevice, env, browserWindow) {
  const modules = new Map();
  const paths = {
    runtime: "../src/config/runtimeEnvironment.ts",
    urls: "../src/config/urls.ts",
    api: "../src/utils/apiConfig.ts",
  };
  function load(name) {
    if (modules.has(name)) return modules.get(name);
    const exports = {};
    modules.set(name, exports);
    const source = readFileSync(new URL(paths[name], import.meta.url), "utf8");
    const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } });
    vm.runInNewContext(outputText, {
      exports, process: { env }, window: browserWindow, __DEV__: false,
      require: dependency => {
        if (dependency === "react-native") return { Platform: { OS: os } };
        if (dependency === "expo-device") return { isDevice };
        if (dependency === "expo-constants") return { default: { expoConfig: { hostUri: "192.168.1.104:8081" } } };
        if (dependency.endsWith("runtimeEnvironment")) return load("runtime");
        if (dependency.endsWith("urls")) return load("urls");
        throw new Error(`Unexpected dependency: ${dependency}`);
      },
    });
    return exports;
  }
  return { runtime: load("runtime"), urls: load("urls"), api: load("api") };
}

for (const os of ["ios", "android"]) {
  test(`${os}: simulador usa produção mesmo com env local e window nativo`, () => {
    const { api, urls, runtime } = loadEnvironment(os, false, {
      EXPO_PUBLIC_APP_ENV: "development", EXPO_PUBLIC_API_URL: "http://192.168.1.104:8080",
    }, {});
    assert.equal(runtime.IS_SIMULATOR, true);
    assert.equal(api.APP_ENV, "production");
    assert.equal(api.API_URL, urls.CONFIG.api.production);
    assert.equal(api.getApiUrlRuntime(), urls.CONFIG.api.production);
    assert.equal(urls.getApiUrlRuntime(), urls.CONFIG.api.production);
    assert.equal(api.ASSETS_URL, urls.CONFIG.assets.production);
    assert.equal(api.getAssetsUrlRuntime(), urls.CONFIG.assets.production);
  });
}

test("web local continua em desenvolvimento", () => {
  const env = { EXPO_PUBLIC_APP_ENV: "development", EXPO_PUBLIC_API_URL: "http://localhost:9090/v2" };
  const { api, urls, runtime } = loadEnvironment("web", false, env, { location: { hostname: "localhost" } });
  assert.equal(runtime.IS_SIMULATOR, false);
  assert.equal(api.APP_ENV, "development");
  assert.equal(api.getApiUrlRuntime(), urls.CONFIG.api.development);
  assert.equal(urls.getApiUrlRuntime(), urls.CONFIG.api.development);
});

test("aparelho físico mantém a URL de desenvolvimento configurada", () => {
  const local = "http://192.168.1.104:8080";
  const { api, runtime } = loadEnvironment("android", true, { EXPO_PUBLIC_APP_ENV: "development", EXPO_PUBLIC_API_URL: local });
  assert.equal(runtime.IS_SIMULATOR, false);
  assert.equal(api.APP_ENV, "development");
  assert.equal(api.API_URL, local);
  assert.equal(api.getApiUrlRuntime(), local);
});

test("APK em aparelho físico e web publicada continuam em produção", () => {
  for (const os of ["android", "web"]) {
    const production = "https://apiv2.appcheckin.com.br/v2";
    const { api, urls } = loadEnvironment(os, true, { EXPO_PUBLIC_APP_ENV: "production", EXPO_PUBLIC_API_URL: production });
    assert.equal(api.getApiUrlRuntime(), production);
    assert.equal(urls.getApiUrlRuntime(), production);
  }
});
