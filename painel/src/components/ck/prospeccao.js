/* Prospecção → Recuperação de Alunos: tabela, filtros, badges e modais de contato.
   Estilos: src/theme/ck-prospeccao.css (+ ck-ml* de ck-enrollments.css). Primitivas de ./index. */
import React, { useEffect, useState } from 'react';
import { Avatar, Badge, Button, Callout, Icon, IconButton, Modal, SelectField, TextAreaField, TextField } from './index';
import { titleCase } from './pessoas';
import { WhatsappContato } from './whatsapp';
import { API_V2_BASE_URL } from '../../config/api';
import { abrirConversaWhatsapp, normalizarTelefoneWhatsapp, telefoneDoAluno } from '../../utils/whatsapp';
import { POTENCIAL, TOM_STATUS, aplicarModelo, dataBR, dataHoraBR, fotoUrl, tempoInativo } from '../../utils/prospeccao';

const opcoes = (mapa = {}) => Object.entries(mapa).map(([value, label]) => ({ value, label }));

export function StatusBadge({ status, rotulo }) {
  return <Badge tone={TOM_STATUS[status] || 'neutral'} dot>{rotulo || status}</Badge>;
}

export function ScoreBadge({ score, potencial, confianca }) {
  if (score == null) return <span className="ck-pr-score is-empty" title="Score ainda não calculado">—</span>;
  const [tom, rotulo] = POTENCIAL[potencial] || POTENCIAL.baixo;
  const titulo = `${rotulo}${confianca != null && confianca < 100 ? ` · calculado com ${confianca}% dos critérios (faltam dados)` : ''}`;
  return <span className={`ck-pr-score ck-pr-score--${tom}`} title={titulo} aria-label={`Score ${score} de 100, ${titulo}`}>
    <strong>{score}</strong>{confianca != null && confianca < 70 ? <Icon name="info" size={12} /> : null}
  </span>;
}

// Foto do cadastro; se o arquivo não carregar, cai nas iniciais (como o Avatar sem foto).
export function AlunoFoto({ nome, foto, size = 'sm' }) {
  const [falhou, setFalhou] = useState(false);
  const src = foto && !falhou ? fotoUrl(foto, API_V2_BASE_URL) : null;
  if (!src) return <Avatar name={titleCase(nome)} size={size} decorative />;
  return <span className={`ck-avatar ck-avatar--${size}`} aria-hidden="true"><img src={src} alt="" onError={() => setFalhou(true)} /></span>;
}

const COLUNAS = [
  ['nome', 'Aluno'], [null, 'Telefone / WhatsApp'], [null, 'Matrícula anterior'], ['cancelamento', 'Cancelamento e motivo'],
  ['status', 'Status'], ['ultimo_contato', 'Contatos'], ['score', 'Score'],
];

/** Tabela paginada no servidor. `ordem`/`direcao` refletem a ordenação atual. */
export function RecuperacaoTabela({ rows = [], ordem, direcao, onOrdenar, onAbrir, onAbordar, onWhatsappAberto, onAgendar }) {
  return <div className="ck-mt-table-wrap">
    <table className="ck-mt-table ck-ml__table ck-pr-table">
      <thead><tr>
        {COLUNAS.map(([chave, rotulo]) => <th key={rotulo} scope="col" aria-sort={chave && ordem === chave ? (direcao === 'asc' ? 'ascending' : 'descending') : undefined}>
          {chave ? <button type="button" className="ck-pr-sort" onClick={() => onOrdenar(chave)}>
            {rotulo}<Icon name={ordem === chave ? (direcao === 'asc' ? 'chevron-up' : 'chevron-down') : 'unfold-more-horizontal'} size={14} />
          </button> : rotulo}
        </th>)}
        <th scope="col"><span className="ck-sr">Ações</span></th>
      </tr></thead>
      <tbody>{rows.map((r) => {
        const nome = titleCase(r.aluno_nome);
        return <tr key={r.id} className="ck-ml__row" onClick={(e) => { if (!e.target.closest('button, a, input')) onAbrir(r); }}>
          <td className="ck-pr-table__aluno"><div className="ck-ml__who">
            <AlunoFoto nome={r.aluno_nome} foto={r.foto_caminho} />
            <span style={{ minWidth: 0 }}>
              <a href={`/prospeccao/recuperacao/${r.id}`} className="ck-ml__name" onClick={(e) => { e.preventDefault(); onAbrir(r); }}>{nome}</a>
              <span className="ck-mt-cell__sub">{r.responsavel_nome ? `Com ${titleCase(r.responsavel_nome).split(' ')[0]}` : 'Sem responsável'}</span>
            </span>
          </div></td>
          <td data-label="Telefone">
            <WhatsappContato telefone={telefoneDoAluno(r)} nome={nome} bloqueado={r.nao_contatar ? 'Aluno pediu para não ser contatado' : false}
              onAbrir={() => onWhatsappAberto(r)} />
          </td>
          <td data-label="Matrícula anterior">
            <span className="ck-mt-cell__main">{r.modalidade_nome || '—'}</span>
            <span className="ck-mt-cell__sub">{r.plano_nome} · desde {dataBR(r.data_ultima_matricula)}</span>
          </td>
          <td data-label="Cancelamento">
            <span className="ck-mt-cell__main">{dataBR(r.data_cancelamento)}{r.data_cancelamento_estimada ? <abbr className="ck-pr-est" title="Data estimada: a matrícula não registrou a data de cancelamento">*</abbr> : null}</span>
            <span className="ck-mt-cell__sub">há {tempoInativo(r.dias_inatividade)} · {r.motivo_rotulo || 'motivo não informado'}</span>
          </td>
          <td data-label="Status"><StatusBadge status={r.status} rotulo={r.status_rotulo} /></td>
          <td data-label="Contatos">
            <span className="ck-mt-cell__main">{r.ultimo_contato_em ? dataBR(r.ultimo_contato_em) : 'Nunca'}</span>
            <span className={`ck-mt-cell__sub${r.proximo_contato_em && r.proximo_contato_em.slice(0, 10) < hojeIso() ? ' is-late' : ''}`}>
              {r.proximo_contato_em ? `Próximo: ${dataHoraBR(r.proximo_contato_em)}` : 'Sem retorno agendado'}
            </span>
          </td>
          <td data-label="Score"><ScoreBadge score={r.score} potencial={r.potencial} confianca={r.score_confianca} /></td>
          <td className="ck-mt-actions">
            {!r.nao_contatar && r.status !== 'recuperado' ? <>
              <IconButton icon="message-circle" size="sm" label={`Abordar ${nome} com modelo de mensagem`} onClick={() => onAbordar(r)} />
              <IconButton icon="calendar" size="sm" label={`Agendar retorno com ${nome}`} onClick={() => onAgendar(r)} />
            </> : null}
            <IconButton icon="chevron-right" size="sm" label={`Abrir ficha de ${nome}`} onClick={() => onAbrir(r)} />
          </td>
        </tr>;
      })}</tbody>
    </table>
  </div>;
}

export const hojeIso = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

/**
 * Abordagem com modelo: escolhe o modelo, revisa o texto, copia ou abre o WhatsApp.
 * Abrir o WhatsApp NÃO registra contato: chama onAberto para o atendente confirmar o que aconteceu.
 */
export function AbordagemModal({ open, alvo, modelos = [], onClose, onAberto }) {
  const [modeloId, setModeloId] = useState('');
  const [texto, setTexto] = useState('');
  const [faltando, setFaltando] = useState([]);
  const [copiado, setCopiado] = useState(false);
  const telefone = alvo ? telefoneDoAluno(alvo.aluno || alvo) : '';
  const tel = normalizarTelefoneWhatsapp(telefone);

  useEffect(() => {
    if (!open) return;
    const sugerido = modelos.find((m) => m.etapa === etapaSugerida(alvo?.status)) || modelos[0];
    setModeloId(sugerido ? String(sugerido.id) : '');
    setCopiado(false);
  }, [open]);

  useEffect(() => {
    const modelo = modelos.find((m) => String(m.id) === modeloId);
    const r = aplicarModelo(modelo?.conteudo || '', alvo?.variaveis || {});
    setTexto(r.texto);
    setFaltando(r.faltando);
  }, [modeloId, open]);

  const copiar = async () => {
    try { await navigator.clipboard.writeText(texto); setCopiado(true); } catch { setCopiado(false); }
  };
  const abrir = () => {
    const r = abrirConversaWhatsapp(telefone, texto);
    if (r.ok) onAberto({ mensagem: texto, modelo_mensagem_id: modeloId ? Number(modeloId) : null });
  };

  return <Modal open={open} onClose={onClose} title="Abordar pelo WhatsApp" size="md"
    description={alvo ? `${titleCase(alvo.nome)} · ${tel.ok ? (tel.nacional ? `(${tel.nacional.slice(0, 2)}) ${tel.nacional.slice(2)}` : `+${tel.numero}`) : 'telefone inválido'}` : ''}
    footer={<>
      <Button onClick={copiar} icon={copiado ? 'check' : 'copy'} disabled={!texto}>{copiado ? 'Copiado' : 'Copiar mensagem'}</Button>
      <Button variant="primary" icon="message-circle" onClick={abrir} disabled={!tel.ok}>Abrir WhatsApp</Button>
    </>}>
    <div className="ck-pr-form">
      {!tel.ok ? <Callout tone="warning" title="Não dá para abrir o WhatsApp">{tel.mensagem} Você ainda pode copiar a mensagem.</Callout> : null}
      <SelectField label="Modelo" value={modeloId} onChange={(e) => setModeloId(e.target.value)}
        options={modelos.map((m) => ({ value: String(m.id), label: m.nome }))} placeholder={modelos.length ? undefined : 'Nenhum modelo ativo'} />
      <TextAreaField label="Mensagem" rows={8} value={texto} onChange={(e) => setTexto(e.target.value)}
        hint="Revise antes de enviar. O envio é feito por você no WhatsApp." />
      {faltando.length ? <Callout tone="info">Sem dado para {faltando.map((v) => `{${v}}`).join(', ')}: o trecho ficou em branco. Ajuste o texto se precisar.</Callout> : null}
    </div>
  </Modal>;
}

const etapaSugerida = (status) => ({ interessado: 'interessado', em_negociacao: 'negociacao', retorno_agendado: 'negociacao' }[status] || 'primeiro_contato');

/** Registro manual do contato. `inicial` pode trazer canal/mensagem/modelo vindos da abordagem. */
export function RegistrarContatoModal({ open, ctx, inicial, transicoes = [], onClose, onSalvar }) {
  const [f, setF] = useState({});
  const [erro, setErro] = useState('');
  const [salvando, setSalvando] = useState(false);
  useEffect(() => {
    if (open) { setF({ canal: 'whatsapp', resultado: inicial?.mensagem ? 'mensagem_enviada' : '', observacao: '', novo_status: '', ...inicial }); setErro(''); }
  }, [open]);
  const set = (k, v) => setF((x) => ({ ...x, [k]: v }));
  const salvar = async () => {
    if (!f.resultado) { setErro('Escolha o resultado do contato'); return; }
    setSalvando(true); setErro('');
    try { await onSalvar(f); } catch (e) { setErro(e?.error || 'Não foi possível registrar o contato'); } finally { setSalvando(false); }
  };
  return <Modal open={open} onClose={onClose} title="Registrar contato" size="sm"
    description="Registre só o que de fato aconteceu. Abrir o WhatsApp não conta como contato."
    footer={<><Button onClick={onClose}>Agora não</Button><Button variant="primary" loading={salvando} onClick={salvar}>Registrar</Button></>}>
    <div className="ck-pr-form">
      {erro ? <Callout tone="danger">{erro}</Callout> : null}
      <div className="ck-pr-grid2">
        <SelectField label="Canal" value={f.canal || ''} onChange={(e) => set('canal', e.target.value)} options={opcoes(ctx?.canais)} />
        <SelectField label="Resultado" value={f.resultado || ''} placeholder="Escolha…" onChange={(e) => set('resultado', e.target.value)} options={opcoes(ctx?.resultados)} />
      </div>
      {transicoes.length ? <SelectField label="Mudar status (opcional)" value={f.novo_status || ''} onChange={(e) => set('novo_status', e.target.value)}
        options={[{ value: '', label: 'Automático pelo resultado' }, ...transicoes.filter((s) => s !== 'nao_contatar').map((s) => ({ value: s, label: ctx?.status?.[s] || s }))]} /> : null}
      <TextAreaField label="Observação" rows={3} value={f.observacao || ''} onChange={(e) => set('observacao', e.target.value)} />
      {f.resultado === 'pediu_nao_contatar' ? <Callout tone="warning" title="O aluno deixará de ser contatado">A oportunidade vai para "Não contatar" e a restrição vale também para futuras prospecções.</Callout> : null}
    </div>
  </Modal>;
}

export function StatusModal({ open, ctx, atual, transicoes = [], onClose, onSalvar }) {
  const [status, setStatus] = useState('');
  const [observacao, setObservacao] = useState('');
  const [motivo, setMotivo] = useState('');
  const [erro, setErro] = useState('');
  const [salvando, setSalvando] = useState(false);
  useEffect(() => { if (open) { setStatus(transicoes[0] || ''); setObservacao(''); setMotivo(''); setErro(''); } }, [open]);
  const salvar = async () => {
    if (status === 'nao_contatar' && motivo.trim().length < 3) { setErro('Descreva como o aluno pediu para não ser contatado'); return; }
    setSalvando(true); setErro('');
    try { await onSalvar({ status, observacao, motivo_restricao: status === 'nao_contatar' ? motivo : undefined }); } catch (e) { setErro(e?.error || 'Não foi possível mudar o status'); } finally { setSalvando(false); }
  };
  return <Modal open={open} onClose={onClose} title="Mudar status" size="sm" description={`Atual: ${ctx?.status?.[atual] || atual}. "Recuperado" é definido quando o aluno tem nova matrícula ativa.`}
    footer={<><Button onClick={onClose}>Cancelar</Button><Button variant="primary" loading={salvando} onClick={salvar} disabled={!status}>Salvar</Button></>}>
    <div className="ck-pr-form">
      {erro ? <Callout tone="danger">{erro}</Callout> : null}
      <SelectField label="Novo status" value={status} onChange={(e) => setStatus(e.target.value)} options={transicoes.map((s) => ({ value: s, label: ctx?.status?.[s] || s }))} />
      {status === 'nao_contatar' ? <>
        <Callout tone="warning" title="Oposição ao contato (LGPD)">O aluno não aparecerá mais para abordagem, inclusive em cancelamentos futuros. Só um registro de nova autorização remove a restrição.</Callout>
        <TextField label="Como o aluno pediu" value={motivo} onChange={(e) => setMotivo(e.target.value)} placeholder="Ex.: respondeu no WhatsApp pedindo para não receber mensagens" />
      </> : null}
      <TextAreaField label="Observação" rows={3} value={observacao} onChange={(e) => setObservacao(e.target.value)} />
    </div>
  </Modal>;
}

/** Novo agendamento ou reagendamento (`reagendar` = só data/hora). */
export function AgendarModal({ open, ctx, alvoNome, reagendar = false, onClose, onSalvar }) {
  const [f, setF] = useState({});
  const [erro, setErro] = useState('');
  const [salvando, setSalvando] = useState(false);
  useEffect(() => {
    if (!open) return;
    const amanha = new Date(Date.now() + 86400000);
    const padrao = `${amanha.getFullYear()}-${String(amanha.getMonth() + 1).padStart(2, '0')}-${String(amanha.getDate()).padStart(2, '0')}T10:00`;
    setF({ agendado_para: padrao, tipo_contato: 'whatsapp', responsavel_id: ctx?.usuario_id ? String(ctx.usuario_id) : '', observacao: '', marcar_retorno_agendado: true });
    setErro('');
  }, [open]);
  const set = (k, v) => setF((x) => ({ ...x, [k]: v }));
  const salvar = async () => {
    setSalvando(true); setErro('');
    try { await onSalvar(f); } catch (e) { setErro(e?.error || 'Não foi possível agendar'); } finally { setSalvando(false); }
  };
  return <Modal open={open} onClose={onClose} title={reagendar ? 'Reagendar retorno' : 'Agendar retorno'} size="sm" description={alvoNome ? titleCase(alvoNome) : undefined}
    footer={<><Button onClick={onClose}>Cancelar</Button><Button variant="primary" loading={salvando} onClick={salvar}>{reagendar ? 'Reagendar' : 'Agendar'}</Button></>}>
    <div className="ck-pr-form">
      {erro ? <Callout tone="danger">{erro}</Callout> : null}
      <TextField label="Data e horário" type="datetime-local" value={f.agendado_para || ''} onChange={(e) => set('agendado_para', e.target.value)} />
      {!reagendar ? <>
        <div className="ck-pr-grid2">
          <SelectField label="Tipo de contato" value={f.tipo_contato || ''} onChange={(e) => set('tipo_contato', e.target.value)} options={opcoes(ctx?.tipos_contato)} />
          <SelectField label="Responsável" value={f.responsavel_id || ''} onChange={(e) => set('responsavel_id', e.target.value)}
            options={(ctx?.responsaveis || []).map((u) => ({ value: String(u.id), label: titleCase(u.nome) }))} />
        </div>
        <label className="ck-pr-check"><input type="checkbox" checked={!!f.marcar_retorno_agendado} onChange={(e) => set('marcar_retorno_agendado', e.target.checked)} /> Mudar status para "Retorno agendado"</label>
      </> : null}
      <TextAreaField label="Observação" rows={2} value={f.observacao || ''} onChange={(e) => set('observacao', e.target.value)} />
    </div>
  </Modal>;
}

/** Fluxo comum: abriu o WhatsApp → pergunta se quer registrar o contato; trata o aviso de contato recente. */
export function useFluxoContato({ ctx, registrar, depois }) {
  const [abordagem, setAbordagem] = useState(null);
  const [registro, setRegistro] = useState(null);
  const [duplicado, setDuplicado] = useState(null);
  const salvar = async (dados) => {
    try {
      await registrar(registro.id, dados);
      setRegistro(null);
      depois && depois();
    } catch (e) {
      if (e?.code === 'CONTATO_RECENTE') { setDuplicado({ ...dados, ultimo: e.ultimo_contato_em, msg: e.error }); return; }
      throw e;
    }
  };
  const confirmarDuplicado = async () => {
    await registrar(registro.id, { ...duplicado, confirmar_duplicado: true });
    setDuplicado(null); setRegistro(null);
    depois && depois();
  };
  const modais = <>
    <AbordagemModal open={!!abordagem} alvo={abordagem} modelos={abordagem?.modelos || []} onClose={() => setAbordagem(null)}
      onAberto={(extra) => { const alvo = abordagem; setAbordagem(null); setRegistro({ id: alvo.id, transicoes: alvo.transicoes, inicial: { canal: 'whatsapp', ...extra } }); }} />
    <RegistrarContatoModal open={!!registro && !duplicado} ctx={ctx} inicial={registro?.inicial} transicoes={registro?.transicoes}
      onClose={() => setRegistro(null)} onSalvar={salvar} />
    <Modal open={!!duplicado} onClose={() => setDuplicado(null)} title="Contato recente" size="sm"
      footer={<><Button onClick={() => setDuplicado(null)}>Voltar</Button><Button variant="primary" onClick={confirmarDuplicado}>Registrar mesmo assim</Button></>}>
      <p className="ck-pr-p">{duplicado?.msg} Último contato: {dataHoraBR(duplicado?.ultimo)}. Combine com a equipe para não abordar o aluno em dobro.</p>
    </Modal>
  </>;
  return {
    modais,
    abordar: (alvo) => setAbordagem(alvo),
    registrarDepoisDoWhatsapp: (alvo) => setRegistro({ id: alvo.id, transicoes: alvo.transicoes, inicial: { canal: 'whatsapp' } }),
  };
}
