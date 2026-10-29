import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import version from './android-version.cjs';

function fixture(t, expo = { version: '1.0.1', android: { package: 'com.anonymous.mobile', versionCode: 1 } }) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'appcheckin-version-'));
  t.after(() => fs.rmSync(root, { recursive: true, force: true }));
  for (const [name, value] of Object.entries({
    'app.json': { expo }, 'package.json': { version: '1.0.1', scripts: { android: 'expo start --android' } },
    'package-lock.json': { version: '1.0.1', packages: { '': { version: '1.0.1' } } },
    'buildNumber.json': { number: 55 }, 'buildInfo.json': { version: 'v1.55' },
  })) fs.writeFileSync(path.join(root, name), JSON.stringify(value));
  return root;
}
const read = (root, name) => JSON.parse(fs.readFileSync(path.join(root, name), 'utf8'));

test('incrementa versão e versionCode em cada build e sincroniza as fontes', (t) => {
  const root = fixture(t);
  assert.deepEqual(version.incrementarVersaoAndroid(root), { version: '1.0.2', versionCode: 2 });
  assert.deepEqual(version.incrementarVersaoAndroid(root), { version: '1.0.3', versionCode: 3 });
  assert.equal(read(root, 'app.json').expo.android.package, 'com.anonymous.mobile');
  assert.equal(read(root, 'package.json').version, '1.0.3');
  assert.equal(read(root, 'package-lock.json').packages[''].version, '1.0.3');
  assert.equal(read(root, 'package-lock.json').version, '1.0.3');
  assert.equal(read(root, 'package.json').scripts.android, 'expo start --android');
  assert.deepEqual(read(root, 'buildNumber.json'), { number: 55 });
  assert.deepEqual(read(root, 'buildInfo.json'), { version: 'v1.55' });
});

test('usa um versionCode maior que o último APK mesmo com configuração antiga', (t) => {
  const root = fixture(t);
  const dir = path.join(root, 'android/app/build/outputs/apk/release');
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(path.join(dir, 'output-metadata.json'), JSON.stringify({ elements: [{ versionCode: 8 }] }));
  assert.deepEqual(version.incrementarVersaoAndroid(root), { version: '1.0.2', versionCode: 9 });
});

test('recusa entradas inválidas antes de alterar arquivos', (t) => {
  for (const expo of [{ version: 'v1.0.1' }, { version: '1.0.1', android: { versionCode: 2100000000 } }]) {
    const root = fixture(t, expo);
    const before = fs.readFileSync(path.join(root, 'app.json'), 'utf8');
    assert.throws(() => version.incrementarVersaoAndroid(root));
    assert.equal(fs.readFileSync(path.join(root, 'app.json'), 'utf8'), before);
    assert.equal(read(root, 'package.json').version, '1.0.1');
  }
});

test('invalida o JavaScript anterior sem apagar o cache nativo', (t) => {
  const root = fixture(t);
  const dir = path.join(root, 'android/app/build/generated/assets/createBundleReleaseJsAndAssets');
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(path.join(dir, 'index.android.bundle'), 'versão antiga');
  const nativeCache = path.join(root, 'android/app/build/cache-nativo');
  fs.writeFileSync(nativeCache, 'preservar');
  version.invalidarBundleAndroid(root);
  version.invalidarBundleAndroid(root);
  assert.equal(fs.existsSync(dir), false);
  assert.equal(fs.readFileSync(nativeCache, 'utf8'), 'preservar');
});

test('publica o APK com manifesto e remove o anterior da pasta de downloads', (t) => {
  const root = fixture(t);
  const destino = path.join(root, 'downloads');
  const apk = path.join(root, 'app.apk');
  fs.writeFileSync(apk, 'apk-1');
  version.publicarApk(apk, { version: '1.0.2', versionCode: 2 }, destino);
  fs.writeFileSync(apk, 'apk-2');
  const m = version.publicarApk(apk, { version: '1.0.3', versionCode: 3 }, destino);
  assert.deepEqual(fs.readdirSync(destino).sort(), ['android.json', 'appcheckin-1.0.3-3.apk']);
  assert.deepEqual(read(destino, 'android.json'), m);
  assert.equal(m.arquivo, 'appcheckin-1.0.3-3.apk');
  assert.equal(m.tamanho, 5);
  assert.match(m.sha256, /^[0-9a-f]{64}$/);
});
