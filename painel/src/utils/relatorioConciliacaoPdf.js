// PDF da conciliação bancária (A4 retrato), no modelo conciliacao-bancaria_modelo.html.
// Recebe os grupos já filtrados da tela: uma linha por parcela do Check-in, com os pagamentos do provedor.
import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';

const C = { ink: [26, 28, 32], muted: [138, 143, 152], empty: [196, 199, 205], line: [230, 231, 234], brand: [242, 101, 34], flag: [180, 83, 9], dot: [217, 119, 6] };
const MM = 72 / 25.4;
const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
// Helvetica do jsPDF não tem o sinal de menos (U+2212) nem o espaço fino do Intl
const money = (v) => BRL.format(Number(v) || 0).replace(/\s/g, ' ');
const neg = (v) => `-${money(Math.abs(v))}`;
const fdate = (iso) => (iso ? String(iso).slice(0, 10).split('-').reverse().join('/') : '');
const curto = (iso) => fdate(iso).slice(0, 5);
const slug = (s) => String(s || 'academia').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
const plural = (n, um, varios) => `${n} ${n === 1 ? um : varios}`;

// Texto em laranja de cada situação que precisa de conferência
const FLAG = {
  sem_recebimento: 'Sem recebimento no provedor',
  manual: 'Baixa manual, fora do provedor',
  sem_baixa: 'Recebido no provedor, sem baixa no Check-in',
  divergente: 'Valor ou líquido diferente do provedor',
  sem_vinculo: 'Recebido sem matrícula ligada',
  nao_conferido: 'Provedor não consultado',
};

function origemDoPagamento(x, varios) {
  const forma = x.forma === 'Cartão de crédito' ? 'Cartão' : x.forma || 'Forma não informada';
  const parcela = !x.parcela_numero ? '' : varios > 1 ? ` ${x.parcela_numero}/${varios}` : `, parcela ${x.parcela_numero}`;
  return [
    x.provedor_nome,
    `${forma}${parcela}`,
    x.antecipado ? 'antecipado' : null,
    x.fora_do_periodo ? `recebido em ${curto(x.data)}` : x.a_receber && x.data_credito ? `crédito em ${curto(x.data_credito)}` : null,
    varios > 1 ? money(x.valor) : null,
  ].filter(Boolean).join(', ');
}

function linhaDoGrupo(g) {
  const pend = g.status !== 'conciliado';
  const pays = g.pays;
  const fontes = pays.length
    ? pays.map((x) => origemDoPagamento(x, pays.length))
    : [[g.baixa === 'manual' ? 'Manual' : 'Automática', g.forma].filter(Boolean).join(', ')];
  if (!pend && g.diff != null && Math.abs(g.diff) >= 0.005) fontes.push(`Diferença de ${money(Math.abs(g.diff))} no parcelamento`);
  if (pend && g.status === 'divergente' && g.notas[0]) fontes.push(g.notas[0]);
  const data = pays[0]?.data || g.parcela?.data_pagamento || g.parcela?.data_vencimento;
  return {
    pend,
    data,
    nome: g.student,
    ref: [g.matriculaId ? `Matrícula #${g.matriculaId}` : 'Sem matrícula', g.parcela ? `parcela ${g.parcela.id}` : null].filter(Boolean).join(', '),
    fontes,
    flag: pend ? FLAG[g.status] || null : null,
    parcela: g.parcela ? g.parcela.valor : null,
    recebido: pays.length ? g.t.gross : null,
    taxa: pays.length ? g.t.fee : null,
    liquido: pays.length ? g.t.net : null,
    pendente: pend ? (g.parcela ? g.parcela.valor : g.t.gross) : 0,
  };
}

function pulse(doc, x, y, s) {
  // Ícone "activity" (M22 12h-4l-3 9L9 3l-3 9H2) num quadrado de s pt
  const k = (s * 0.64) / 24;
  const ox = x + (s - 24 * k) / 2;
  const oy = y + (s - 24 * k) / 2;
  const pts = [[22, 12], [18, 12], [15, 21], [9, 3], [6, 12], [2, 12]].map(([a, b]) => [ox + a * k, oy + b * k]);
  doc.setDrawColor(255, 255, 255).setLineWidth(1.1).setLineCap('round').setLineJoin('round');
  for (let i = 1; i < pts.length; i += 1) doc.line(pts[i - 1][0], pts[i - 1][1], pts[i][0], pts[i][1]);
}

/**
 * @param {{ grupos: any[], resumo: any, academia?: string, inicio: string, fim: string, provedor?: string }} p
 */
export function gerarPdfConciliacao({ grupos = [], resumo = {}, academia, inicio, fim, provedor = 'Todos' }) {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'a4' });
  const W = doc.internal.pageSize.getWidth();
  const H = doc.internal.pageSize.getHeight();
  const MX = 20 * MM;
  const MY = 22 * MM;
  const CW = W - MX * 2;
  const nomeAcademia = academia || 'Academia';
  const periodo = `${fdate(inicio)} a ${fdate(fim)}`;
  const geradoEm = new Date().toLocaleDateString('pt-BR');

  const linhas = grupos.map(linhaDoGrupo);
  const porData = (a, b) => String(b.data || '').localeCompare(String(a.data || ''));
  const ordenadas = [...linhas.filter((l) => !l.pend).sort(porData), ...linhas.filter((l) => l.pend).sort(porData)];
  const conciliadas = linhas.length - linhas.filter((l) => l.pend).length;
  const pendencias = linhas.filter((l) => l.pend);
  const pendente = pendencias.reduce((a, l) => a + l.pendente, 0);
  const baixadas = grupos.reduce((a, g) => a + (g.paga ? g.parcela.valor : 0), 0);

  // Cabeçalho
  let y = MY;
  doc.setFillColor(...C.brand).roundedRect(MX, y, 14, 14, 3, 3, 'F');
  pulse(doc, MX, y, 14);
  doc.setFont('helvetica', 'bold').setFontSize(9.5).setTextColor(...C.ink).text('Check-in', MX + 20, y + 10.2);
  doc.setFont('helvetica', 'bold').setFontSize(20).text('Conciliação', W - MX, y + 15, { align: 'right' });
  doc.setFont('helvetica', 'normal').setFontSize(8.4).setTextColor(...C.muted).text(periodo, W - MX, y + 29, { align: 'right' });

  // Dados do relatório
  y += 24 + 29;
  const fr = [1.4, 1, 1, 1];
  const gap = 16;
  const unit = (CW - gap * 3) / fr.reduce((a, v) => a + v, 0);
  const info = [
    ['Academia', nomeAcademia],
    ['Provedor', provedor],
    ['Situação', `${conciliadas} de ${linhas.length} conciliadas`],
    ['Gerado em', geradoEm],
  ];
  let ix = MX;
  info.forEach(([dt, dd], i) => {
    const w = unit * fr[i];
    doc.setFont('helvetica', 'normal').setFontSize(7).setTextColor(...C.muted).text(dt, ix, y);
    doc.setFont('helvetica', 'bold').setFontSize(8.4).setTextColor(...C.ink).text(doc.splitTextToSize(dd, w)[0], ix, y + 12);
    ix += w + gap;
  });
  y += 12 + 20 + 8;

  // Tabela
  const cols = [0.09, null, 0.13, 0.12, 0.10, 0.12].map((f) => (f ? CW * f : null));
  cols[1] = CW - cols.reduce((a, v) => a + (v || 0), 0);
  const alunoW = cols[1] - 8;
  const LH = { nome: 8.4 * 1.45, small: 7.2 * 1.45 + 1 };
  const valor = (v, cor) => (v == null ? { content: '—', styles: { textColor: C.empty } } : { content: v, styles: cor ? { textColor: cor } : {} });

  const body = ordenadas.map((l) => {
    doc.setFont('helvetica', 'normal').setFontSize(7.2);
    const smalls = [l.ref, ...l.fontes].flatMap((t) => doc.splitTextToSize(t, alunoW));
    doc.setFont('helvetica', 'bold').setFontSize(8.4);
    const nome = doc.splitTextToSize(l.nome, alunoW);
    const altura = nome.length * LH.nome + (smalls.length + (l.flag ? 1 : 0)) * LH.small + 12;
    return [
      { content: curto(l.data), styles: { textColor: C.muted } },
      { content: '', aluno: { nome, smalls, flag: l.flag }, styles: { minCellHeight: altura } },
      valor(l.parcela != null ? money(l.parcela) : null),
      valor(l.recebido != null ? money(l.recebido) : null),
      valor(l.taxa != null ? neg(l.taxa) : null, l.taxa != null ? C.muted : null),
      valor(l.liquido != null ? money(l.liquido) : null),
    ];
  });

  autoTable(doc, {
    startY: y,
    margin: { left: MX, right: MX, top: MY, bottom: MY },
    head: [['Data', 'Aluno', 'Parcela', 'Recebido', 'Taxa', 'Líquido']],
    body: body.length ? body : [[{ content: 'Nenhuma parcela neste filtro.', colSpan: 6, styles: { textColor: C.muted } }]],
    theme: 'plain',
    styles: { font: 'helvetica', fontSize: 8.4, textColor: C.ink, cellPadding: { top: 6, bottom: 6, left: 8, right: 0 }, valign: 'top', lineColor: C.line, lineWidth: { bottom: 0.5 } },
    headStyles: { fontSize: 7, fontStyle: 'normal', textColor: C.muted, cellPadding: { top: 0, bottom: 6, left: 8, right: 0 }, lineColor: C.ink, lineWidth: { bottom: 0.5 } },
    columnStyles: Object.fromEntries(cols.map((w, i) => [i, { cellWidth: w, halign: i >= 2 ? 'right' : 'left' }])),
    rowPageBreak: 'avoid',
    showHead: 'everyPage',
    didParseCell: (d) => {
      if (d.column.index === 0) d.cell.styles.cellPadding = { ...d.cell.styles.cellPadding, left: 0 };
      if (d.section === 'head' && d.column.index >= 2) d.cell.styles.halign = 'right';
    },
    didDrawCell: (d) => {
      const a = d.cell.raw?.aluno;
      if (d.section !== 'body' || !a) return;
      const x = d.cell.x + 8;
      let ty = d.cell.y + 6 + 8.4 * 0.95;
      doc.setFont('helvetica', 'bold').setFontSize(8.4).setTextColor(...C.ink).text(a.nome, x, ty);
      ty += (a.nome.length - 1) * LH.nome + LH.small + 1;
      doc.setFont('helvetica', 'normal').setFontSize(7.2).setTextColor(...C.muted);
      a.smalls.forEach((t) => { doc.text(t, x, ty); ty += LH.small; });
      if (a.flag) {
        doc.setFillColor(...C.dot).circle(x + 2, ty - 2.4, 2, 'F');
        doc.setFont('helvetica', 'bold').setTextColor(...C.flag).text(a.flag, x + 8, ty);
      }
    },
  });

  // Totais: notas à esquerda, resumo à direita
  const resumoLinhas = [
    ['Parcelas baixadas', money(baixadas)],
    ['Recebido no provedor', money(resumo.gross)],
    ['Taxas', neg(resumo.fees || 0)],
    ['Líquido', money(resumo.net), 'big'],
    ['A receber em cartão', money(resumo.receivable), 'sub'],
    ...(resumo.taxa?.parcelas ? [
      ['Acréscimos cobrados no cartão', money(resumo.taxa.acrescimos), 'sub sep'],
      [resumo.taxa.resultado < -0.004 ? 'Perda com taxas (líquido x plano)' : 'Ganho com taxas (líquido x plano)', `${resumo.taxa.resultado < -0.004 ? '-' : '+'}${money(Math.abs(resumo.taxa.resultado))}`, 'sub'],
    ] : []),
    ['Pendente de conferência', money(pendente), 'sub sep'],
  ];
  const alturaTotais = 190;
  y = doc.lastAutoTable.finalY + 14;
  if (y + alturaTotais > H - MY) { doc.addPage(); y = MY; }

  const nw = CW * 0.46;
  let ny = y + 8;
  const nota = (titulo, texto) => {
    doc.setFont('helvetica', 'bold').setFontSize(7.6).setTextColor(...C.ink).text(titulo, MX, ny);
    ny += 11;
    doc.setFont('helvetica', 'normal').setFontSize(7.2).setTextColor(...C.muted);
    const t = doc.splitTextToSize(texto, nw);
    doc.text(t, MX, ny);
    ny += t.length * 10.4 + 6;
  };
  if (pendencias.length) {
    nota(plural(pendencias.length, 'pendência', 'pendências'), 'Parcelas marcadas em laranja não batem com o provedor: foram baixadas no Check-in sem pagamento correspondente, ou recebidas no provedor sem baixa. Confira antes de fechar o mês.');
  } else {
    nota('Nenhuma pendência', 'Todas as parcelas deste relatório batem com os pagamentos do provedor.');
  }
  nota('Como ler', 'Parcela é o valor baixado no Check-in. Recebido, taxa e líquido vêm do provedor. Diferenças de centavos vêm do parcelamento no cartão.');

  const sx = W - MX - CW * 0.42;
  const sw = CW * 0.42;
  let sy = y;
  resumoLinhas.forEach(([dt, dd, cls = '']) => {
    const big = cls.includes('big');
    const sub = cls.includes('sub');
    if (big || cls.includes('sep')) {
      sy += big ? 4 : 6;
      doc.setDrawColor(...(big ? C.ink : C.line)).setLineWidth(0.5).line(sx, sy, sx + sw, sy);
      sy += 8;
    } else {
      sy += 4;
    }
    const size = sub ? 7.2 : 8.4;
    const base = sy + (big ? 13 : size);
    doc.setFont('helvetica', big ? 'bold' : 'normal').setFontSize(size).setTextColor(...(big ? C.ink : C.muted)).text(dt, sx, base);
    doc.setFont('helvetica', big ? 'bold' : 'normal').setFontSize(big ? 15 : size).setTextColor(...C.ink).text(dd, sx + sw, base, { align: 'right' });
    sy = base + 4;
  });

  // Rodapé
  const pages = doc.getNumberOfPages();
  for (let i = 1; i <= pages; i += 1) {
    doc.setPage(i);
    doc.setDrawColor(...C.line).setLineWidth(0.5).line(MX, H - MY + 4, W - MX, H - MY + 4);
    doc.setFont('helvetica', 'normal').setFontSize(7).setTextColor(...C.muted);
    doc.text(`${nomeAcademia} · Conciliação de ${periodo}`, MX, H - MY + 16);
    doc.text(`Página ${i} de ${pages}`, W - MX, H - MY + 16, { align: 'right' });
  }

  doc.save(`conciliacao_${slug(nomeAcademia)}_${inicio}_${fim}.pdf`);
}
