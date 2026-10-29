#!/usr/bin/env node
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { incrementarVersaoAndroid, invalidarBundleAndroid, publicarApk } = require('./android-version.cjs');
const { publicarFtp } = require('./publicar-apk-ftp.cjs');

const root = path.resolve(__dirname, '..');
// Pasta servida pela apiV2 em /downloads (fora do git; sobe no deploy)
const publishDir = path.resolve(process.env.APK_PUBLISH_DIR || path.join(root, '../apiV2/public/downloads'));
const expoDir = path.join(root, '.expo');
const statePath = path.join(expoDir, 'android-apk-state.json');
const lockPath = path.join(expoDir, 'android-apk.lock');
const retry = process.argv.slice(2).includes('--retry');
if (process.argv.slice(2).some((a) => a !== '--retry')) throw new Error('Use npm run android:apk [-- --retry].');

function run(command, args, options = {}) {
  const result = spawnSync(command, args, { cwd: root, env, stdio: 'inherit', ...options });
  if (result.error) throw result.error;
  if (result.status !== 0) throw new Error(`${path.basename(command)} falhou (${result.signal || result.status}).`);
  return result.stdout?.toString().trim();
}

const [major, minor] = process.versions.node.split('.').map(Number);
if (major < 20 || (major === 20 && minor < 19)) throw new Error('Use Node 20.19+ (recomendado: Node 22).');
const javaHome = process.env.JAVA_HOME || (process.platform === 'darwin'
  ? spawnSync('/usr/libexec/java_home', ['-v', '17'], { encoding: 'utf8' }).stdout?.trim() : undefined);
const sdk = process.env.ANDROID_HOME || process.env.ANDROID_SDK_ROOT || (process.platform === 'darwin' ? path.join(os.homedir(), 'Library/Android/sdk') : undefined);
if (!sdk || !fs.existsSync(sdk)) throw new Error('Configure ANDROID_HOME com o caminho do Android SDK.');
const buildToolsDir = path.join(sdk, 'build-tools');
const buildTools = fs.readdirSync(buildToolsDir).filter((v) => fs.existsSync(path.join(buildToolsDir, v, 'apksigner')))
  .sort((a, b) => a.localeCompare(b, undefined, { numeric: true })).at(-1);
if (!buildTools) throw new Error('Android Build Tools/apksigner não encontrado.');
const env = {
  ...process.env,
  PATH: [path.dirname(process.execPath), javaHome && path.join(javaHome, 'bin'), process.env.PATH].filter(Boolean).join(path.delimiter),
  ...(javaHome ? { JAVA_HOME: javaHome } : {}),
  ANDROID_HOME: sdk, ANDROID_SDK_ROOT: sdk,
  CI: '1', NODE_ENV: 'production', EXPO_NO_DOTENV: '1',
  EXPO_PUBLIC_APP_ENV: process.env.EXPO_PUBLIC_APP_ENV || 'production',
  EXPO_PUBLIC_API_URL: process.env.EXPO_PUBLIC_API_URL || 'https://apiv2.appcheckin.com.br/v2',
  EXPO_PUBLIC_DEBUG_LOGS: 'false',
};
run(javaHome ? path.join(javaHome, 'bin/java') : 'java', ['-version']);
fs.mkdirSync(expoDir, { recursive: true });
let lock;
try { lock = fs.openSync(lockPath, 'wx'); }
catch (e) { throw new Error(`Já existe um build reservado em ${lockPath}. Verifique se o processo terminou antes de remover o arquivo.`, { cause: e }); }
fs.writeFileSync(lock, String(process.pid));

try {
  let version;
  if (retry) {
    const state = JSON.parse(fs.readFileSync(statePath, 'utf8'));
    const app = JSON.parse(fs.readFileSync(path.join(root, 'app.json'), 'utf8')).expo;
    if (state.completed || state.version !== app.version || state.versionCode !== app.android?.versionCode) {
      throw new Error('Não há build interrompido correspondente à versão atual para retomar.');
    }
    version = { version: state.version, versionCode: state.versionCode };
  } else {
    version = incrementarVersaoAndroid(root);
    fs.writeFileSync(statePath, JSON.stringify({ ...version, completed: false }, null, 2) + '\n');
  }
  console.log(`\nAPK ${version.version} · versionCode ${version.versionCode} · API ${env.EXPO_PUBLIC_API_URL}\n`);
  const packagePath = path.join(root, 'package.json');
  const pkg = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
  try {
    run(process.execPath, [path.join(root, 'node_modules/expo/bin/cli'), 'prebuild', '--platform', 'android', '--no-install',
      '--skip-dependency-update', Object.keys({ ...pkg.dependencies, ...pkg.devDependencies }).join(',')]);
  } finally {
    // O Expo altera os atalhos android/ios durante prebuild; preserva os comandos do projeto.
    const current = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
    current.scripts = pkg.scripts;
    fs.writeFileSync(packagePath, JSON.stringify(current, null, 2) + '\n');
  }
  const gradlePath = path.join(root, 'android/app/build.gradle');
  const source = fs.readFileSync(gradlePath, 'utf8');
  const assignment = `    nodeExecutableAndArgs = [${JSON.stringify(process.execPath)}]`;
  fs.writeFileSync(gradlePath, /^\s*nodeExecutableAndArgs\s*=.*$/m.test(source)
    ? source.replace(/^\s*nodeExecutableAndArgs\s*=.*$/m, assignment)
    : source.replace('react {', `react {\n${assignment}`));
  invalidarBundleAndroid(root);
  run(path.join(root, 'android', process.platform === 'win32' ? 'gradlew.bat' : 'gradlew'),
    [':app:assembleRelease', '--console=plain', '--max-workers=2'], { cwd: path.join(root, 'android') });
  const releaseDir = path.join(root, 'android/app/build/outputs/apk/release');
  const metadata = JSON.parse(fs.readFileSync(path.join(releaseDir, 'output-metadata.json'), 'utf8'));
  const apk = metadata.elements.find((e) => e.versionName === version.version && e.versionCode === version.versionCode);
  if (!apk) throw new Error('O APK gerado não corresponde à versão reservada.');
  const sourceApk = path.join(releaseDir, apk.outputFile);
  run(path.join(buildToolsDir, buildTools, 'apksigner'), ['verify', '--verbose', sourceApk]);
  const artifacts = path.join(root, 'artifacts');
  fs.mkdirSync(artifacts, { recursive: true });
  const output = path.join(artifacts, `appcheckin-${version.version}-${version.versionCode}.apk`);
  fs.copyFileSync(sourceApk, output, fs.constants.COPYFILE_EXCL);
  fs.writeFileSync(statePath, JSON.stringify({ ...version, completed: true, output }, null, 2) + '\n');
  console.log(`\nAPK validado: ${output}\n`);
  const publicado = publicarApk(output, version, publishDir);
  console.log(`Publicado em ${path.join(publishDir, publicado.arquivo)} + android.json.`);
  try {
    if (!publicarFtp(publishDir)) console.log('Sem .env.apk-ftp.local: suba a pasta no deploy (primeiro o .apk, depois o android.json).\n');
  } catch (e) {
    // O APK já está pronto; só o envio falhou e pode ser repetido sem novo build
    console.error(`\n${e.message}\nPara reenviar sem novo build: npm run android:apk:publicar`);
    process.exitCode = 1;
  }
} catch (e) {
  console.error(`\n${e.message}\nPara retomar a mesma versão após corrigir a falha: npm run android:apk -- --retry`);
  process.exitCode = 1;
} finally {
  fs.closeSync(lock);
  fs.unlinkSync(lockPath);
}
