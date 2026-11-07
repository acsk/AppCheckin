// Relatório de pré-matrículas separado por turma (CSV e PDF), a partir das solicitações já carregadas na tela.
// Um aluno com 2x por semana aparece nas duas turmas da grade dele.
import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import { downloadCsv } from './csv';

export const DIAS_SEMANA = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
const STATUS = { pendente: 'Aguardando aprovação', aprovada: 'Aprovada', reprovada: 'Reprovada' };
const hora = (valor) => String(valor || '').slice(0, 5);
const slug = (texto) => String(texto || 'academia').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
const hoje = () => new Date().toISOString().slice(0, 10);

export const rotuloPlano = (item) => `${item.checkins_semanais}x por semana${item.plano_descricao ? ` · ${item.plano_descricao}` : ''}`;
export const rotuloTurma = (slot) => `${slot.turma_nome}${slot.horario_inicio ? ` · ${hora(slot.horario_inicio)}–${hora(slot.horario_fim)}` : ''}`;

/**
 * Agrupa por turma. `dia` e `turma` limitam quais horários da grade entram (os mesmos filtros da tela).
 * @returns {{ chave: number, turma_nome: string, horario: string, linhas: object[] }[]}
 */
export function agruparPorTurma(solicitacoes, { dia = '', turma = '' } = {}) {
  const grupos = new Map();
  solicitacoes.forEach((item) => {
    (item.grade || []).forEach((slot) => {
      if (dia && Number(slot.dia_semana) !== Number(dia)) return;
      if (turma && Number(slot.turma_fixa_id) !== Number(turma)) return;
      const chave = Number(slot.turma_fixa_id);
      if (!grupos.has(chave)) {
        grupos.set(chave, { chave, turma_nome: slot.turma_nome, horario: slot.horario_inicio ? `${hora(slot.horario_inicio)}–${hora(slot.horario_fim)}` : '', inicio: hora(slot.horario_inicio), linhas: [] });
      }
      grupos.get(chave).linhas.push({
        dia: Number(slot.dia_semana),
        nome: item.nome,
        telefone: item.telefone || item.responsavel_telefone || '',
        email: item.email || '',
        cpf: item.cpf || '',
        responsavel: item.dependente ? item.responsavel_nome || '' : '',
        plano: rotuloPlano(item),
        vencimento: item.dia_vencimento ? `Dia ${item.dia_vencimento}` : '',
        status: STATUS[item.status] || item.status,
      });
    });
  });

  return [...grupos.values()]
    .map((grupo) => ({
      ...grupo,
      linhas: grupo.linhas.sort((a, b) => a.dia - b.dia || a.nome.localeCompare(b.nome, 'pt-BR')),
    }))
    .sort((a, b) => a.turma_nome.localeCompare(b.turma_nome, 'pt-BR') || a.inicio.localeCompare(b.inicio));
}

const resumo = (grupo) => `${grupo.linhas.length} aluno(s)`;

export function exportarCsvPorTurma(grupos) {
  const cabecalho = ['Dia', 'Aluno', 'Telefone', 'E-mail', 'CPF', 'Responsável', 'Plano', 'Vencimento', 'Status'];
  const linhas = [];
  grupos.forEach((grupo, indice) => {
    if (indice) linhas.push([]);
    linhas.push([`TURMA: ${grupo.turma_nome}${grupo.horario ? ` (${grupo.horario})` : ''}`, resumo(grupo)]);
    linhas.push(cabecalho);
    grupo.linhas.forEach((l) => linhas.push([DIAS_SEMANA[l.dia - 1] || l.dia, l.nome, l.telefone, l.email, l.cpf, l.responsavel, l.plano, l.vencimento, l.status]));
  });
  downloadCsv(`pre-matriculas-por-turma-${hoje()}.csv`, linhas);
}

export function exportarPdfPorTurma(grupos, { academia = 'Academia', filtros = '' } = {}) {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
  const largura = doc.internal.pageSize.getWidth();
  const margem = 36;
  const geradoEm = new Date().toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Sao_Paulo' });

  doc.setFont('helvetica', 'bold').setFontSize(18).setTextColor(21, 24, 29).text('Pré-matrículas por turma', margem, margem + 10);
  doc.setFont('helvetica', 'normal').setFontSize(10).setTextColor(79, 86, 97)
    .text(`${academia} · gerado em ${geradoEm}`, margem, margem + 28);
  if (filtros) doc.text(`Filtros: ${filtros}`, margem, margem + 42);
  const total = new Set(grupos.flatMap((g) => g.linhas.map((l) => `${l.nome}|${l.email}`))).size;
  doc.setFont('helvetica', 'bold').text(`${grupos.length} turma(s) · ${total} aluno(s)`, largura - margem, margem + 28, { align: 'right' });

  let y = margem + (filtros ? 58 : 46);
  if (!grupos.length) {
    doc.setFont('helvetica', 'normal').setFontSize(12).text('Nenhuma pré-matrícula para os filtros escolhidos.', margem, y + 16);
  }
  grupos.forEach((grupo) => {
    autoTable(doc, {
      startY: y,
      margin: { left: margem, right: margem, bottom: 36 },
      head: [
        [{ content: `${grupo.turma_nome}${grupo.horario ? `  ·  ${grupo.horario}` : ''}  ·  ${resumo(grupo)}`, colSpan: 6, styles: { fillColor: [27, 31, 38], textColor: 255, fontSize: 11 } }],
        ['Dia', 'Aluno', 'Contato', 'Plano', 'Venc.', 'Status'],
      ],
      body: grupo.linhas.map((l) => [
        DIAS_SEMANA[l.dia - 1] || l.dia,
        l.responsavel ? `${l.nome}\n(dep. de ${l.responsavel})` : l.nome,
        [l.telefone, l.email].filter(Boolean).join('\n'),
        l.plano,
        l.vencimento,
        l.status,
      ]),
      styles: { font: 'helvetica', fontSize: 9, cellPadding: 4, textColor: [21, 24, 29], lineColor: [227, 230, 234], lineWidth: 0.5 },
      headStyles: { fillColor: [244, 245, 247], textColor: [79, 86, 97], fontStyle: 'bold' },
      columnStyles: { 0: { cellWidth: 60 }, 4: { cellWidth: 52 }, 5: { cellWidth: 110 } },
      showHead: 'everyPage',
    });
    y = doc.lastAutoTable.finalY + 18;
  });

  const paginas = doc.getNumberOfPages();
  for (let i = 1; i <= paginas; i += 1) {
    doc.setPage(i);
    doc.setFont('helvetica', 'normal').setFontSize(8).setTextColor(104, 112, 128)
      .text(`Página ${i} de ${paginas}`, largura - margem, doc.internal.pageSize.getHeight() - 18, { align: 'right' });
  }
  doc.save(`pre-matriculas-por-turma-${slug(academia)}-${hoje()}.pdf`);
}
