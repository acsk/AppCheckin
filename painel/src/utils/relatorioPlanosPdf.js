// PDF de planos e ciclos (GET /admin/relatorios/planos-ciclos), no visual do design system.
import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';

const C = {
  ink: [21, 24, 29], muted: [79, 86, 97], subtle: [104, 112, 128], line: [227, 230, 234], bg: [244, 245, 247],
  dark: [27, 31, 38], darkMuted: [163, 171, 183], brand: [242, 101, 34],
  success: [18, 122, 58], successSoft: [230, 244, 234], warning: [154, 83, 0], warningSoft: [253, 241, 220],
  danger: [196, 41, 28], dangerSoft: [252, 233, 231], info: [31, 95, 209],
};
const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const money = (v) => BRL.format(Number(v) || 0).replace(/\u00a0/g, ' ');
const freq = (n) => (Number(n) >= 999 ? 'Livre' : `${Number(n) || 0}x por semana`);
const period = (c) => (/diar/i.test(`${c.frequencia_codigo || ''} ${c.frequencia_nome || ''}`) ? '1 dia' : Number(c.meses) === 1 ? '1 mês' : `${c.meses} meses`);
const hexRgb = (hex) => {
  const m = /^#?([0-9a-f]{6})$/i.exec(String(hex || '').trim());
  if (!m) return null;
  const n = parseInt(m[1], 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
};
const slug = (s) => String(s || 'academia').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

const statusOf = (p) => (!Number(p.ativo) ? ['Inativo', C.subtle] : Number(p.atual) ? ['Ativo', C.success] : ['Fechado a novas', C.warning]);

export function gerarPdfPlanos(data) {
  const { tenant, planos = [], resumo = {} } = data || {};
  const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
  const W = doc.internal.pageSize.getWidth();
  const H = doc.internal.pageSize.getHeight();
  const M = 36;
  const geradoEm = new Date().toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  const academia = tenant?.nome || 'Academia';

  // Cabeçalho
  doc.setFillColor(...C.dark);
  doc.roundedRect(M, M, W - M * 2, 84, 12, 12, 'F');
  doc.setFillColor(...C.brand);
  doc.roundedRect(M + 20, M + 18, 20, 20, 5, 5, 'F');
  doc.setTextColor(255, 255, 255).setFont('helvetica', 'bold').setFontSize(11).text('C', M + 30, M + 32, { align: 'center' });
  doc.setFontSize(12).text('Check-in', M + 48, M + 32);
  doc.setFontSize(22).text('Planos e ciclos', M + 20, M + 66);
  doc.setFont('helvetica', 'normal').setFontSize(10).setTextColor(...C.darkMuted)
    .text('Valores, ciclos de cobrança e status de cada plano', M + 190, M + 66);
  doc.setFont('helvetica', 'bold').setFontSize(12).setTextColor(255, 255, 255).text(academia, W - M - 20, M + 32, { align: 'right' });
  doc.setFont('helvetica', 'normal').setFontSize(9).setTextColor(...C.darkMuted).text(`Gerado em ${geradoEm}`, W - M - 20, M + 48, { align: 'right' });

  // Indicadores
  const kpis = [
    ['Planos', resumo.total_planos, C.ink],
    ['Ativos', resumo.planos_ativos, C.success],
    ['Inativos', resumo.planos_inativos, C.ink],
    ['Ciclos ativos', resumo.ciclos_ativos, C.ink],
    ['Planos sem ciclo', resumo.planos_sem_ciclos, resumo.planos_sem_ciclos ? C.danger : C.ink],
  ];
  const gap = 10;
  const kw = (W - M * 2 - gap * (kpis.length - 1)) / kpis.length;
  const ky = M + 100;
  kpis.forEach(([label, value, color], i) => {
    const x = M + i * (kw + gap);
    doc.setDrawColor(...C.line).setFillColor(255, 255, 255).roundedRect(x, ky, kw, 52, 8, 8, 'FD');
    doc.setFont('helvetica', 'normal').setFontSize(9).setTextColor(...C.muted).text(label, x + 12, ky + 18);
    doc.setFont('helvetica', 'bold').setFontSize(18).setTextColor(...color).text(String(value || 0), x + 12, ky + 40);
  });

  // Tabelas por modalidade
  const grupos = planos.reduce((acc, p) => {
    const k = p.modalidade?.nome || 'Sem modalidade';
    (acc[k] = acc[k] || { cor: hexRgb(p.modalidade?.cor), planos: [] }).planos.push(p);
    return acc;
  }, {});
  let y = ky + 76;

  const nomes = Object.keys(grupos).sort((a, b) => a.localeCompare(b, 'pt-BR'));
  if (!nomes.length) {
    doc.setFont('helvetica', 'normal').setFontSize(12).setTextColor(...C.muted).text('Nenhum plano cadastrado.', M, y + 10);
  }

  nomes.forEach((nome) => {
    const g = grupos[nome];
    if (y > H - 120) { doc.addPage(); y = M; }
    doc.setFillColor(...(g.cor || C.subtle)).roundedRect(M, y - 9, 10, 10, 2, 2, 'F');
    doc.setFont('helvetica', 'bold').setFontSize(13).setTextColor(...C.ink).text(nome, M + 16, y);
    const nw = doc.getTextWidth(nome);
    doc.setFont('helvetica', 'normal').setFontSize(9).setTextColor(...C.subtle)
      .text(g.planos.length === 1 ? '1 plano' : `${g.planos.length} planos`, M + 24 + nw, y);

    const body = [];
    g.planos.forEach((p) => {
      const ciclos = p.ciclos || [];
      const [stLabel, stColor] = statusOf(p);
      const span = Math.max(ciclos.length, 1);
      const plano = { content: `${p.nome}\n#${p.id} · ${freq(p.checkins_semanais)} · ${p.duracao_dias || 30} dias`, rowSpan: span, planName: p.nome };
      const mensal = { content: Number(p.valor) > 0 ? money(p.valor) : 'Sem custo', rowSpan: span, styles: { halign: 'right' } };
      const status = { content: stLabel, rowSpan: span, styles: { textColor: stColor, fontStyle: 'bold' } };
      if (!ciclos.length) {
        body.push([plano, mensal, { content: 'Nenhum ciclo cadastrado', colSpan: 4, styles: { textColor: C.danger, fontStyle: 'bold' } }, status]);
        return;
      }
      ciclos.forEach((c, i) => {
        const desconto = Number(c.desconto_percentual) > 0 ? `\n${Number(c.desconto_percentual).toLocaleString('pt-BR')}% off` : '';
        const cobranca = `${Number(c.permite_recorrencia) ? 'Recorrente' : 'Avulso'}${Number(c.permite_reposicao) ? ' · Reposição' : ''}`;
        const row = [
          { content: `${c.frequencia_nome}\n${period(c)}${Number(c.ativo) ? '' : ' · indisponível'}`, styles: Number(c.ativo) ? {} : { textColor: C.subtle } },
          { content: money(c.valor), styles: { halign: 'right' } },
          { content: `${money(c.valor_mensal_equivalente)}${desconto}`, styles: { halign: 'right' } },
          cobranca,
        ];
        body.push(i === 0 ? [plano, mensal, ...row, status] : row);
      });
    });

    autoTable(doc, {
      startY: y + 8,
      margin: { left: M, right: M, top: M, bottom: 40 },
      head: [['Plano', 'Mensal', 'Ciclo', 'Valor do ciclo', 'Por mês', 'Cobrança', 'Status']],
      body,
      theme: 'grid',
      styles: { font: 'helvetica', fontSize: 9, cellPadding: { top: 6, bottom: 6, left: 8, right: 8 }, textColor: C.ink, lineColor: C.line, lineWidth: 0.5, valign: 'middle' },
      headStyles: { fillColor: C.bg, textColor: C.muted, fontStyle: 'bold', fontSize: 8.5 },
      columnStyles: {
        0: { cellWidth: 190 }, 1: { cellWidth: 80, halign: 'right' }, 2: { cellWidth: 110 },
        3: { cellWidth: 90, halign: 'right' }, 4: { cellWidth: 90, halign: 'right' }, 6: { cellWidth: 90 },
      },
      rowPageBreak: 'avoid',
      didParseCell: (d) => {
        if (d.section === 'head' && [1, 3, 4].includes(d.column.index)) d.cell.styles.halign = 'right';
      },
      willDrawCell: (d) => {
        if (d.section === 'body' && d.cell.raw?.planName) { d.cell.planLines = d.cell.text; d.cell.text = []; }
      },
      didDrawCell: (d) => {
        if (!d.cell.planLines) return;
        const x = d.cell.x + 8;
        const w = d.cell.width - 16;
        doc.setFont('helvetica', 'bold').setFontSize(10);
        const name = doc.splitTextToSize(d.cell.raw.planName, w);
        const sub = String(d.cell.raw.content).split('\n').slice(1).join(' ');
        let ty = d.cell.y + d.cell.height / 2 - ((name.length - 1) * 12) / 2 - 2;
        doc.setTextColor(...C.ink).text(name, x, ty);
        ty += name.length * 12;
        doc.setFont('helvetica', 'normal').setFontSize(8.5).setTextColor(...C.subtle).text(sub, x, ty);
      },
    });
    y = doc.lastAutoTable.finalY + 30;
  });

  // Rodapé
  const pages = doc.getNumberOfPages();
  for (let i = 1; i <= pages; i += 1) {
    doc.setPage(i);
    doc.setFont('helvetica', 'normal').setFontSize(8).setTextColor(...C.subtle);
    doc.text(`${academia} · Planos e ciclos · ${geradoEm}`, M, H - 20);
    doc.text(`Página ${i} de ${pages}`, W - M, H - 20, { align: 'right' });
  }

  doc.save(`planos-e-ciclos_${slug(academia)}_${new Date().toISOString().slice(0, 10)}.pdf`);
}
