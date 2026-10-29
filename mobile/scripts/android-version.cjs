const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');

function incrementarVersaoAndroid(root) {
  const appPath = path.join(root, 'app.json');
  const packagePath = path.join(root, 'package.json');
  const lockPath = path.join(root, 'package-lock.json');
  const app = JSON.parse(fs.readFileSync(appPath, 'utf8'));
  const pkg = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
  const lock = fs.existsSync(lockPath) ? JSON.parse(fs.readFileSync(lockPath, 'utf8')) : null;
  const partes = /^(\d+)\.(\d+)\.(\d+)$/.exec(app.expo.version);
  if (!partes) throw new Error('expo.version deve usar o formato major.minor.patch (ex.: 1.0.1).');
  const numeros = partes.slice(1).map(Number);
  if (numeros.some((n) => !Number.isSafeInteger(n)) || !Number.isSafeInteger(numeros[2] + 1)) throw new Error('Versão inválida.');
  const metadataPath = path.join(root, 'android/app/build/outputs/apk/release/output-metadata.json');
  const metadata = fs.existsSync(metadataPath) ? JSON.parse(fs.readFileSync(metadataPath, 'utf8')) : null;
  const anterior = Math.max(app.expo.android?.versionCode ?? 1, ...(metadata?.elements || []).map((e) => e.versionCode ?? 1));
  if (!Number.isSafeInteger(anterior) || anterior < 1 || anterior >= 2100000000) throw new Error('versionCode inválido ou esgotado.');
  const version = `${numeros[0]}.${numeros[1]}.${numeros[2] + 1}`;
  const versionCode = anterior + 1;
  app.expo.version = version;
  app.expo.android = { ...app.expo.android, versionCode };
  pkg.version = version;
  if (lock) {
    lock.version = version;
    if (lock.packages?.['']) lock.packages[''].version = version;
  }
  for (const [file, value] of [[appPath, app], [packagePath, pkg], ...(lock ? [[lockPath, lock]] : [])]) {
    fs.writeFileSync(file, JSON.stringify(value, null, 2) + '\n');
  }
  return { version, versionCode };
}

function invalidarBundleAndroid(root) {
  // Gradle não considera app.json uma entrada do bundle em todas as versões do plugin.
  // Remover apenas essa saída força o JS a incorporar o expo.version atualizado.
  fs.rmSync(path.join(root, 'android/app/build/generated/assets/createBundleReleaseJsAndAssets'), { recursive: true, force: true });
}

/**
 * Copia o APK para a pasta de downloads da apiV2 e grava android.json, que a API lê como versão
 * publicada. Mantém só o APK atual; o manifesto é gravado por último (troca atômica).
 */
function publicarApk(apkPath, { version, versionCode }, destino) {
  fs.mkdirSync(destino, { recursive: true });
  const arquivo = `appcheckin-${version}-${versionCode}.apk`;
  fs.copyFileSync(apkPath, path.join(destino, arquivo));
  const manifesto = {
    version, version_code: versionCode, arquivo,
    sha256: crypto.createHash('sha256').update(fs.readFileSync(apkPath)).digest('hex'),
    tamanho: fs.statSync(apkPath).size, gerado_em: new Date().toISOString(),
  };
  const tmp = path.join(destino, 'android.json.tmp');
  fs.writeFileSync(tmp, JSON.stringify(manifesto, null, 2) + '\n');
  fs.renameSync(tmp, path.join(destino, 'android.json'));
  for (const f of fs.readdirSync(destino)) {
    if (/^appcheckin-.+\.apk$/.test(f) && f !== arquivo) fs.rmSync(path.join(destino, f));
  }
  return manifesto;
}

module.exports = { incrementarVersaoAndroid, invalidarBundleAndroid, publicarApk };
