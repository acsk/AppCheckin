#!/usr/bin/env node

const fs = require("fs");
const http = require("http");
const https = require("https");
const path = require("path");

const appJsonPath = path.join(__dirname, "../app.json");
const buildInfoPath = path.join(__dirname, "../buildInfo.json");
const buildNumberPath = path.join(__dirname, "../buildNumber.json");
const envProductionPath = path.join(__dirname, "../.env.production");
const DEFAULT_PROD_API_BASE = "https://apiv2.appcheckin.com.br/v2";

function loadEnvFile(filePath) {
  if (!fs.existsSync(filePath)) return;
  const raw = fs.readFileSync(filePath, "utf-8");
  for (const line of raw.split("\n")) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith("#")) continue;
    const eq = trimmed.indexOf("=");
    if (eq <= 0) continue;
    const key = trimmed.slice(0, eq).trim();
    let value = trimmed.slice(eq + 1).trim();
    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }
    if (process.env[key] === undefined) {
      process.env[key] = value;
    }
  }
}

loadEnvFile(envProductionPath);

const postJson = (url, payload, token) =>
  new Promise((resolve, reject) => {
    const parsed = new URL(url);
    const transport = parsed.protocol === "https:" ? https : http;
    const body = JSON.stringify(payload);
    const headers = {
      Accept: "application/json",
      "Content-Type": "application/json",
      "Content-Length": Buffer.byteLength(body),
    };
    if (token) {
      headers["X-App-Version-Token"] = token;
    }

    const req = transport.request(
      {
        hostname: parsed.hostname,
        port: parsed.port || (parsed.protocol === "https:" ? 443 : 80),
        path: `${parsed.pathname}${parsed.search}`,
        method: "POST",
        headers,
      },
      (res) => {
        let data = "";
        res.on("data", (chunk) => {
          data += chunk;
        });
        res.on("end", () => {
          if ((res.statusCode || 500) >= 200 && (res.statusCode || 500) < 300) {
            resolve({ statusCode: res.statusCode, body: data });
            return;
          }
          reject(
            new Error(
              `Falha ao publicar versão (${res.statusCode}): ${data || "sem resposta"}`,
            ),
          );
        });
      },
    );

    req.on("error", reject);
    req.write(body);
    req.end();
  });

const toEndpoint = (base) => {
  const cleaned = String(base || "").replace(/\/$/, "");
  if (!cleaned) return "";
  if (/\/version\/mobile$/i.test(cleaned)) return cleaned;
  return `${cleaned}/version/mobile`;
};

const run = async () => {
  const appJson = JSON.parse(fs.readFileSync(appJsonPath, "utf-8"));
  const buildNumber = JSON.parse(fs.readFileSync(buildNumberPath, "utf-8"));
  const nextBuildNumber = Number(buildNumber.number || 0) + 1;
  const majorVersion = Number(String(appJson.expo.version).split(".")[0] || "1");
  const buildVersion = `v${majorVersion}.${nextBuildNumber}`;
  const buildDateIso = new Date().toISOString();

  const buildInfo = {
    version: buildVersion,
    buildDate: buildDateIso,
    buildNumber: nextBuildNumber,
  };

  fs.writeFileSync(buildInfoPath, JSON.stringify(buildInfo, null, 2) + "\n");
  fs.writeFileSync(
    buildNumberPath,
    JSON.stringify({ number: nextBuildNumber }, null, 2) + "\n",
  );

  console.log(`✅ Build incrementado: ${buildVersion}`);
  console.log(`📅 Build gerado: ${buildInfo.buildDate}`);

  const skipPublish = process.env.APP_VERSION_SKIP_PUBLISH === "1";
  if (skipPublish) {
    console.log("ℹ️ Publicação no backend ignorada (APP_VERSION_SKIP_PUBLISH=1).");
    return;
  }

  const token = process.env.APP_VERSION_UPDATE_TOKEN || "";
  const bases = (process.env.APP_VERSION_API_URLS || DEFAULT_PROD_API_BASE)
    .split(",")
    .map((item) => item.trim())
    .filter(Boolean);

  const payload = {
    version: majorVersion,
    release: nextBuildNumber,
    release_date: buildDateIso.slice(0, 10),
  };

  let failed = false;
  for (const base of bases) {
    const endpoint = toEndpoint(base);
    try {
      await postJson(endpoint, payload, token);
      console.log(`☁️ Versão publicada: ${endpoint} → ${buildVersion}`);
    } catch (error) {
      failed = true;
      console.error(`❌ ${endpoint}: ${error.message}`);
    }
  }

  if (failed) {
    process.exitCode = 1;
  }
};

run();
