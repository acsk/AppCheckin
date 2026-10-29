#!/usr/bin/env node
/**
 * Envia o APK publicado (apiV2/public/downloads) para o servidor por FTP com TLS.
 * Ordem: APK, depois android.json (enviado como .tmp e renomeado), e por último apaga os APKs antigos.
 * Configuração em mobile/.env.apk-ftp.local (fora do git) ou nas variáveis APK_FTP_*.
 * Uso direto: npm run android:apk:publicar
 */
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const root = path.resolve(__dirname, '..');
const CONFIG = path.join(root, '.env.apk-ftp.local');

function lerConfig() {
  const arquivo = fs.existsSync(CONFIG) ? fs.readFileSync(CONFIG, 'utf8') : '';
  const vars = {};
  for (const linha of arquivo.split(/\r?\n/)) {
    const m = /^\s*(APK_FTP_\w+)\s*=(.*)$/.exec(linha);
    if (m) vars[m[1]] = m[2].trim();
  }
  const v = (k, padrao = '') => process.env[k] || vars[k] || padrao;
  const cfg = {
    host: v('APK_FTP_HOST'), port: v('APK_FTP_PORT', '21'), user: v('APK_FTP_USER'), password: v('APK_FTP_PASSWORD'),
    dir: v('APK_FTP_DIR', 'apiV2/public/downloads').replace(/^\/+|\/+$/g, ''),
    // Certificado da Hostinger é *.hstgr.io: valida por esse nome e conecta no IP do host
    tlsName: v('APK_FTP_TLS_NAME'),
  };
  return cfg.host && cfg.user && cfg.password ? cfg : null;
}

function curl(cfg, caminho, args) {
  const nome = cfg.tlsName || cfg.host;
  const url = `ftp://${nome}:${cfg.port}/${cfg.dir}/${caminho}`;
  const conectar = cfg.tlsName ? ['--connect-to', `${nome}:${cfg.port}:${cfg.host}:${cfg.port}`] : [];
  // Credencial pela entrada padrão: não aparece na lista de processos
  const user = `${cfg.user}:${cfg.password}`.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  const r = spawnSync('curl', ['-sS', '--ssl-reqd', '--ftp-create-dirs', '--retry', '2', '-K', '-', ...conectar, ...args, url], {
    input: `user = "${user}"\n`, encoding: 'utf8', maxBuffer: 16 * 1024 * 1024,
  });
  if (r.error) throw r.error;
  if (r.status !== 0) throw new Error(`FTP falhou em ${caminho || 'listagem'} (curl ${r.status}): ${(r.stderr || '').trim()}`);
  return r.stdout;
}

function publicarFtp(publishDir) {
  const cfg = lerConfig();
  if (!cfg) return false;
  const manifesto = JSON.parse(fs.readFileSync(path.join(publishDir, 'android.json'), 'utf8'));
  const apk = path.join(publishDir, manifesto.arquivo);
  if (fs.statSync(apk).size !== manifesto.tamanho) throw new Error(`${manifesto.arquivo} não confere com o android.json.`);

  console.log(`Enviando ${manifesto.arquivo} (${(manifesto.tamanho / 1048576).toFixed(1)} MB) para ${cfg.dir}…`);
  curl(cfg, manifesto.arquivo, ['-T', apk]);
  const remoto = /content-length:\s*(\d+)/i.exec(curl(cfg, manifesto.arquivo, ['-I']));
  if (!remoto || Number(remoto[1]) !== manifesto.tamanho) throw new Error('O APK no servidor ficou com tamanho diferente; o android.json não foi trocado.');

  curl(cfg, '.htaccess', ['-T', path.join(publishDir, '.htaccess')]);
  curl(cfg, 'android.json.tmp', ['-T', path.join(publishDir, 'android.json'), '-Q', '-RNFR android.json.tmp', '-Q', '-RNTO android.json']);

  const antigos = curl(cfg, '', ['--list-only']).split(/\r?\n/).map((s) => s.trim())
    .filter((f) => /^appcheckin-.+\.apk$/.test(f) && f !== manifesto.arquivo);
  if (antigos.length) curl(cfg, '', ['--list-only', ...antigos.flatMap((f) => ['-Q', `-DELE ${f}`])]);
  console.log(`Publicado por FTP: ${manifesto.version} (versionCode ${manifesto.version_code})${antigos.length ? `; removidos: ${antigos.join(', ')}` : ''}.`);
  registrarNaApi(manifesto);
  return true;
}

/**
 * Registra o APK no banco (POST /version/android), como o web:build faz com /version/mobile.
 * Token e URLs da API vêm do .env.production (APP_VERSION_UPDATE_TOKEN, APP_VERSION_API_URLS).
 */
function registrarNaApi(manifesto) {
  const env = {};
  const arquivo = path.join(root, '.env.production');
  for (const linha of (fs.existsSync(arquivo) ? fs.readFileSync(arquivo, 'utf8') : '').split(/\r?\n/)) {
    const m = /^\s*(APP_VERSION_\w+|APK_PUBLIC_URL)\s*=(.*)$/.exec(linha);
    if (m) env[m[1]] = m[2].trim().replace(/^(["'])(.*)\1$/, '$2');
  }
  const v = (k, padrao = '') => process.env[k] || env[k] || padrao;
  const token = v('APP_VERSION_UPDATE_TOKEN');
  const bases = v('APP_VERSION_API_URLS', 'https://apiv2.appcheckin.com.br/v2').split(',').map((b) => b.trim().replace(/\/+$/, '')).filter(Boolean);

  for (const base of bases) {
    // O APK é servido pela própria apiV2 em /downloads (fora do prefixo /v2)
    const apkUrl = `${v('APK_PUBLIC_URL', new URL(base).origin + '/downloads').replace(/\/+$/, '')}/${encodeURIComponent(manifesto.arquivo)}`;
    const corpo = JSON.stringify({ version: manifesto.version, version_code: manifesto.version_code, apk_url: apkUrl, sha256: manifesto.sha256, tamanho: manifesto.tamanho });
    // Token pela configuração do curl (entrada padrão): não aparece na lista de processos
    const res = spawnSync('curl', ['-sS', '-K', '-', '-H', 'Content-Type: application/json', '-H', 'Accept: application/json',
      '--data-binary', corpo, '-w', '\n%{http_code}', `${base}/version/android`], {
      input: token ? `header = "X-App-Version-Token: ${token.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"\n` : '', encoding: 'utf8',
    });
    const linhas = (res.stdout || '').trim().split('\n');
    const status = Number(linhas.pop());
    if (res.status !== 0 || !(status >= 200 && status < 300)) {
      throw new Error(`Registro na API falhou (${base}): HTTP ${status || '?'} ${(linhas.join(' ') || res.stderr || '').trim().slice(0, 300)}`);
    }
    console.log(`Registrado na API: ${base} → ${manifesto.version}`);
  }
}

module.exports = { publicarFtp, lerConfig, registrarNaApi };

if (require.main === module) {
  const dir = path.resolve(process.env.APK_PUBLISH_DIR || path.join(root, '../apiV2/public/downloads'));
  try {
    if (!publicarFtp(dir)) {
      console.error(`Configure ${CONFIG} (APK_FTP_HOST, APK_FTP_USER, APK_FTP_PASSWORD, APK_FTP_DIR).`);
      process.exitCode = 1;
    }
  } catch (e) {
    console.error(e.message);
    process.exitCode = 1;
  }
}
