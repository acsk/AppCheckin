// Relatório imprimível de planos e ciclos (GET /admin/relatorios/planos-ciclos), no visual do design system.
const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const money = (v) => BRL.format(Number(v) || 0);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const freq = (n) => (Number(n) >= 999 ? 'Livre' : `${Number(n) || 0}x por semana`);
const period = (c) => (/diar/i.test(`${c.frequencia_codigo || ''} ${c.frequencia_nome || ''}`) ? '1 dia' : Number(c.meses) === 1 ? '1 mês' : `${c.meses} meses`);

export const RELATORIO_BASE_CSS = `
  @import url('https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Semi+Condensed:wght@600;700&display=swap');
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Barlow, system-ui, sans-serif; font-size: 14px; line-height: 20px; color: #15181d; background: #f4f5f7; padding: 32px 16px; -webkit-font-smoothing: antialiased; }
  .page { max-width: 1040px; margin: 0 auto; display: grid; gap: 20px; }
  .head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; padding: 28px; border-radius: 20px; background: #1b1f26; color: #f2f4f7; }
  .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; font-family: 'Barlow Semi Condensed', Barlow, sans-serif; font-weight: 700; font-size: 17px; }
  .logo { width: 30px; height: 30px; border-radius: 9px; background: #f26522; display: grid; place-items: center; color: #fff; font-size: 16px; }
  h1 { font-family: 'Barlow Semi Condensed', Barlow, sans-serif; font-size: 32px; line-height: 36px; font-weight: 600; letter-spacing: -0.01em; }
  .sub { color: #a3abb7; margin-top: 4px; }
  .meta { text-align: right; color: #a3abb7; font-size: 13px; }
  .meta strong { display: block; color: #f2f4f7; font-size: 15px; }
  .kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
  .kpi { background: #fff; border: 1px solid #e3e6ea; border-radius: 14px; padding: 16px 18px; }
  .kpi span { display: block; color: #4f5661; font-size: 13px; }
  .kpi strong { display: block; font-family: 'Barlow Semi Condensed', Barlow, sans-serif; font-size: 28px; line-height: 34px; font-weight: 600; font-variant-numeric: tabular-nums; }
  .kpi.warn strong { color: #9a5300; } .kpi.ok strong { color: #127a3a; } .kpi.bad strong { color: #c4291c; }
  .card { background: #fff; border: 1px solid #e3e6ea; border-radius: 20px; overflow: hidden; break-inside: avoid; }
  .card h2 { font-family: 'Barlow Semi Condensed', Barlow, sans-serif; font-size: 20px; line-height: 24px; font-weight: 600; padding: 18px 22px; border-bottom: 1px solid #e3e6ea; display: flex; align-items: center; gap: 10px; }
  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; font-size: 12px; font-weight: 600; color: #4f5661; background: #f4f5f7; padding: 9px 14px; white-space: nowrap; }
  td { padding: 11px 14px; border-top: 1px solid #eef0f3; vertical-align: top; }
  .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .muted { color: #687080; font-size: 12px; }
  .badge { display: inline-block; padding: 1px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #eef0f3; color: #4f5661; white-space: nowrap; }
  .b-ok { background: #e6f4ea; color: #127a3a; } .b-warn { background: #fdf1dc; color: #9a5300; } .b-bad { background: #fce9e7; color: #c4291c; } .b-info { background: #e7eefc; color: #1f5fd1; }
  .foot { text-align: center; color: #687080; font-size: 12px; padding: 8px 0 0; }
  .actions { display: flex; justify-content: flex-end; }
  .print { font: inherit; font-weight: 600; border: 0; border-radius: 10px; background: #c9430c; color: #fff; padding: 10px 18px; cursor: pointer; }
  @media print { body { background: #fff; padding: 0; } .actions { display: none; } .head { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
`;

export function abrirRelatorioHtml(title, body) {
  const html = `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>${esc(title)}</title><style>${RELATORIO_BASE_CSS}</style></head><body><div class="page">${body}</div></body></html>`;
  const win = window.open('', '_blank');
  if (!win) throw { error: 'O navegador bloqueou a nova aba. Libere pop-ups para este site.' };
  win.document.write(html);
  win.document.close();
}

export function relatorioHead(title, subtitle, tenantName) {
  const now = new Date().toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  return `
    <div class="actions"><button class="print" onclick="window.print()">Imprimir ou salvar PDF</button></div>
    <header class="head">
      <div>
        <div class="brand"><span class="logo">✓</span>Check-in</div>
        <h1>${esc(title)}</h1>
        ${subtitle ? `<p class="sub">${esc(subtitle)}</p>` : ''}
      </div>
      <div class="meta"><strong>${esc(tenantName || 'Academia')}</strong>Gerado em ${now}</div>
    </header>`;
}

export function abrirRelatorioPlanos(data) {
  const { tenant, planos = [], resumo = {} } = data || {};
  const grupos = planos.reduce((acc, p) => {
    const k = p.modalidade?.nome || 'Sem modalidade';
    (acc[k] = acc[k] || []).push(p);
    return acc;
  }, {});

  const kpis = `
    <section class="kpis">
      <div class="kpi"><span>Planos</span><strong>${resumo.total_planos || 0}</strong></div>
      <div class="kpi ok"><span>Ativos</span><strong>${resumo.planos_ativos || 0}</strong></div>
      <div class="kpi"><span>Inativos</span><strong>${resumo.planos_inativos || 0}</strong></div>
      <div class="kpi"><span>Ciclos ativos</span><strong>${resumo.ciclos_ativos || 0}</strong></div>
      <div class="kpi ${resumo.planos_sem_ciclos ? 'bad' : ''}"><span>Planos sem ciclo</span><strong>${resumo.planos_sem_ciclos || 0}</strong></div>
    </section>`;

  const cards = Object.keys(grupos).sort((a, b) => a.localeCompare(b, 'pt-BR')).map((nome) => `
    <section class="card">
      <h2>${esc(nome)} <span class="badge">${grupos[nome].length} ${grupos[nome].length === 1 ? 'plano' : 'planos'}</span></h2>
      <table>
        <thead><tr><th>Plano</th><th class="num">Mensal</th><th>Ciclo</th><th class="num">Valor do ciclo</th><th class="num">Por mês</th><th>Cobrança</th><th>Status</th></tr></thead>
        <tbody>
          ${grupos[nome].map((p) => {
            const status = !p.ativo ? '<span class="badge">Inativo</span>' : p.atual ? '<span class="badge b-ok">Ativo</span>' : '<span class="badge b-warn">Fechado a novas</span>';
            const planoCell = `<strong>${esc(p.nome)}</strong><div class="muted">#${p.id} · ${freq(p.checkins_semanais)} · ${p.duracao_dias || 30} dias</div>`;
            const ciclos = p.ciclos || [];
            if (!ciclos.length) {
              return `<tr><td>${planoCell}</td><td class="num">${p.valor > 0 ? money(p.valor) : 'Sem custo'}</td><td colspan="4"><span class="badge b-bad">Nenhum ciclo</span></td><td>${status}</td></tr>`;
            }
            return ciclos.map((c, i) => `<tr>
              ${i === 0 ? `<td rowspan="${ciclos.length}">${planoCell}</td><td class="num" rowspan="${ciclos.length}">${p.valor > 0 ? money(p.valor) : 'Sem custo'}</td>` : ''}
              <td><strong>${esc(c.frequencia_nome)}</strong><div class="muted">${period(c)}${c.ativo ? '' : ' · indisponível'}</div></td>
              <td class="num">${money(c.valor)}</td>
              <td class="num">${money(c.valor_mensal_equivalente)}${c.desconto_percentual > 0 ? `<div class="muted">${Number(c.desconto_percentual).toLocaleString('pt-BR')}% off</div>` : ''}</td>
              <td><span class="badge ${c.permite_recorrencia ? 'b-info' : ''}">${c.permite_recorrencia ? 'Recorrente' : 'Avulso'}</span>${c.permite_reposicao ? ' <span class="badge">Reposição</span>' : ''}</td>
              ${i === 0 ? `<td rowspan="${ciclos.length}">${status}</td>` : ''}
            </tr>`).join('');
          }).join('')}
        </tbody>
      </table>
    </section>`).join('');

  abrirRelatorioHtml(
    `Planos e ciclos — ${tenant?.nome || 'Academia'}`,
    `${relatorioHead('Planos e ciclos', 'Valores, ciclos de cobrança e status de cada plano', tenant?.nome)}${kpis}${cards || '<section class="card"><h2>Nenhum plano cadastrado</h2></section>'}<p class="foot">Relatório gerado pelo painel Check-in</p>`,
  );
}
