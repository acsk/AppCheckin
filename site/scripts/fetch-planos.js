// Salva em src/data/planos.json os planos do painel marcados para o site.
// O site usa esse arquivo enquanto a API não responde (ou se ela estiver fora).
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const out = resolve(root, 'src/data/planos.json');

function envFile(name) {
  try {
    const line = readFileSync(resolve(root, name), 'utf8').split('\n').find((l) => l.startsWith('VITE_API_URL='));
    return line ? line.slice('VITE_API_URL='.length).trim() : null;
  } catch {
    return null;
  }
}

const api = (process.env.VITE_API_URL || envFile('.env.production') || envFile('.env') || 'https://apiv2.appcheckin.com.br/v2').replace(/\/$/, '');

try {
  const res = await fetch(`${api}/site/planos`, { signal: AbortSignal.timeout(10000) });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const { planos } = await res.json();
  if (!Array.isArray(planos) || !planos.length) throw new Error('nenhum plano marcado para o site');
  writeFileSync(out, `${JSON.stringify(planos, null, 2)}\n`);
  console.log(`✅ ${planos.length} planos salvos de ${api}`);
} catch (e) {
  console.warn(`⚠️  Não foi possível buscar os planos em ${api} (${e.message}). Mantendo src/data/planos.json.`);
}
