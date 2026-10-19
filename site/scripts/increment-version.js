#!/usr/bin/env node
/**
 * Incrementa o build do site (v{major}.{build}) em buildInfo.json e buildNumber.json.
 * Uso: node scripts/increment-version.js [patch|minor|major]
 * patch só incrementa o build; minor/major também sobem a versão do package.json.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const packagePath = path.join(root, 'package.json');
const buildInfoPath = path.join(root, 'buildInfo.json');
const buildNumberPath = path.join(root, 'buildNumber.json');

const tipo = process.argv[2] || 'patch';
const pkg = JSON.parse(fs.readFileSync(packagePath, 'utf-8'));
const buildNumber = fs.existsSync(buildNumberPath)
  ? JSON.parse(fs.readFileSync(buildNumberPath, 'utf-8'))
  : { number: 0 };

if (tipo === 'minor' || tipo === 'major') {
  const [major, minor] = String(pkg.version || '1.0.0').split('.').map(Number);
  pkg.version = tipo === 'major' ? `${major + 1}.0.0` : `${major}.${minor + 1}.0`;
  fs.writeFileSync(packagePath, JSON.stringify(pkg, null, 2) + '\n');
}

const nextBuildNumber = Number(buildNumber.number || 0) + 1;
const majorVersion = Number(String(pkg.version).split('.')[0] || '1');
const buildVersion = `v${majorVersion}.${nextBuildNumber}`;

const buildInfo = {
  version: buildVersion,
  buildDate: new Date().toISOString(),
  buildNumber: nextBuildNumber,
};

fs.writeFileSync(buildInfoPath, JSON.stringify(buildInfo, null, 2) + '\n');
fs.writeFileSync(buildNumberPath, JSON.stringify({ number: nextBuildNumber }, null, 2) + '\n');

console.log(`✅ Build incrementado: ${buildVersion}`);
console.log(`📅 Build gerado: ${buildInfo.buildDate}`);
