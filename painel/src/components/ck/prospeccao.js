/* Prospecção → Recuperação de Alunos: tabela, filtros, badges e modais de contato.
   Estilos: src/theme/ck-prospeccao.css (+ ck-ml* de ck-enrollments.css). Primitivas de ./index. */
import React, { useEffect, useState } from 'react';
import { Avatar, Badge, Button, Callout, Icon, IconButton, Modal, SelectField, TextAreaField, TextField } from './index';
import { maskPhone, titleCase } from './pessoas';
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
export function AbordagemModal({ open, alvo, modelos = [], onClose, onAberto, onRegistrar }) {
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

  const sugerido = modelos.find((m) => m.etapa === etapaSugerida(alvo?.status)) || modelos[0];
  const selecionado = modelos.find((m) => String(m.id) === modeloId);
  const nome = titleCase(alvo?.nome || alvo?.aluno?.nome || 'Aluno');
  return <Modal open={open} onClose={onClose} title="Chamar no WhatsApp" size="lg" dialogClassName="ck-px-dlg" autoFocus={false}
    leading={<span className="ck-px-modal-icon"><Icon name="message-circle" size={20} /></span>}
    description="Escolha o modelo, revise e abra o WhatsApp com a mensagem pronta."
    footer={<>
      {onRegistrar ? <Button variant="ghost" className="ck-px-skip" onClick={onRegistrar}>Já falei por outro meio</Button> : null}
      <Button onClick={copiar} icon={copiado ? 'check' : 'copy'} disabled={!texto}>{copiado ? 'Copiado' : 'Copiar texto'}</Button>
      <Button variant="primary" iconRight="external-link" onClick={abrir} disabled={!tel.ok || !texto.trim()}>Abrir WhatsApp</Button>
    </>}>
    <div className="ck-px-msg">
      <div className="ck-px-msg__form">
        <div className="ck-px-to">
          <AlunoFoto nome={nome} foto={alvo?.aluno?.foto_caminho} size="md" />
          <div><strong>{nome}</strong><span><Icon name="message-circle" size={14} />{tel.ok ? (tel.nacional ? maskPhone(tel.nacional) : `+${tel.numero}`) : 'Sem telefone válido'}</span></div>
        </div>
        {!tel.ok ? <Callout tone="warning" title="Não dá para abrir o WhatsApp">{tel.mensagem} Você ainda pode copiar a mensagem.</Callout> : null}
        {modelos.length ? <div className="ck-field">
          <span className="ck-field__label">Modelo</span>
          <div className="ck-ml__filters" role="group" aria-label="Modelo de mensagem">
            {modelos.map((m) => <button key={m.id} type="button" className="ck-ml__chip" aria-pressed={modeloId === String(m.id)} onClick={() => setModeloId(String(m.id))}>
              {m.nome}{m.id === sugerido?.id ? <span className="ck-px-sug">sugerido</span> : null}
            </button>)}
          </div>
        </div> : <p className="ck-pr-hint">Nenhum modelo ativo. Escreva a mensagem abaixo.</p>}
        <TextAreaField label="Mensagem" rows={7} value={texto} onChange={(e) => setTexto(e.target.value)}
          hint={selecionado && texto !== aplicarModelo(selecionado.conteudo, alvo?.variaveis || {}).texto ? 'Você editou o modelo. A mudança vale só para esta mensagem.' : 'Revise antes de enviar. Dá para editar à vontade.'} />
        {faltando.length ? <Callout tone="info">Sem dado para {faltando.map((v) => `{${v}}`).join(', ')}: o trecho ficou em branco. Ajuste o texto se precisar.</Callout> : null}
      </div>
      <section className="ck-px-phone" aria-label="Prévia da mensagem no WhatsApp">
        <div className="ck-px-phone__head"><AlunoFoto nome={nome} foto={alvo?.aluno?.foto_caminho} />{nome.split(' ')[0]}</div>
        <div className="ck-px-phone__chat">{texto ? <p className="ck-px-bubble">{texto}</p> : <p className="ck-px-empty">A mensagem aparece aqui.</p>}</div>
      </section>
    </div>
  </Modal>;
}

const etapaSugerida = (status) => ({ contatado: 'negociacao', interessado: 'interessado', em_negociacao: 'negociacao', retorno_agendado: 'negociacao' }[status] || 'primeiro_contato');

/** Registro manual do contato. `inicial` pode trazer canal/mensagem/modelo vindos da abordagem. */
export function RegistrarContatoModal({ open, ctx, inicial, transicoes = [], onClose, onSalvar }) {
  const [f, setF] = useState({});
  const [erro, setErro] = useState('');
  const [salvando, setSalvando] = useState(false);
  useEffect(() => {
    if (open) { setF({ canal: 'whatsapp', resultado: '', observacao: '', novo_status: '', ...inicial }); setErro(''); }
  }, [open]);
  const set = (k, v) => setF((x) => ({ ...x, [k]: v }));
  const salvar = async () => {
    if (!f.resultado) { setErro('Escolha o resultado do contato'); return; }
    setSalvando(true); setErro('');
    try { await onSalvar(f); } catch (e) { setErro(e?.error || 'Não foi possível registrar o contato'); } finally { setSalvando(false); }
  };
  return <Modal open={open} onClose={onClose} title="Registrar o contato" size="md"
    description="Registre só o que de fato aconteceu. Abrir o WhatsApp não conta como contato."
    footer={<><Button onClick={onClose}>Agora não</Button><Button variant="primary" loading={salvando} onClick={salvar}>Salvar contato</Button></>}>
    <div className="ck-pr-form">
      {erro ? <Callout tone="danger">{erro}</Callout> : null}
      {inicial?.mensagem ? <div className="ck-px-sent"><Icon name="message-circle" size={20} /><div><strong>WhatsApp aberto</strong><span>Envie por lá e volte aqui para registrar como foi.</span></div></div> : null}
      <SelectField label="Canal" value={f.canal || ''} onChange={(e) => set('canal', e.target.value)} options={opcoes(ctx?.canais)} />
      <fieldset className="ck-px-results">
        <legend>Como foi?</legend>
        <div className="ck-px-outs">
          {opcoes(ctx?.resultados).map(({ value, label }) => <label key={value} className={`ck-px-out${f.resultado === value ? ' is-selected' : ''}${value === 'pediu_nao_contatar' ? ' is-danger' : ''}`}>
            <span>{label}</span><input type="radio" name="resultado-contato" value={value} checked={f.resultado === value} onChange={() => set('resultado', value)} />
          </label>)}
        </div>
      </fieldset>
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
      onRegistrar={() => { const alvo = abordagem; setAbordagem(null); setRegistro({ id: alvo.id, transicoes: alvo.transicoes, inicial: { canal: 'telefone' } }); }}
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
    registrarContato: (alvo) => setRegistro({ id: alvo.id, transicoes: alvo.transicoes, inicial: { canal: alvo.canal || 'telefone' } }),
    registrarDepoisDoWhatsapp: (alvo) => setRegistro({ id: alvo.id, transicoes: alvo.transicoes, inicial: { canal: 'whatsapp' } }),
  };
}

/**
 * Escolher um aluno com matrícula cancelada e iniciar a prospecção dele.
 * Mostra quem já está em prospecção (abre a ficha) e quem não pode (matrícula em andamento).
 */
export function IniciarProspeccaoModal({ open, buscar, iniciar, onClose, onAbrir }) {
  const [termo, setTermo] = useState('');
  const [alunos, setAlunos] = useState(null);
  const [erro, setErro] = useState('');
  const [iniciando, setIniciando] = useState(null);
  useEffect(() => { if (open) { setTermo(''); setErro(''); } }, [open]);
  useEffect(() => {
    if (!open) return undefined;
    let vivo = true;
    const t = setTimeout(() => {
      buscar(termo.trim()).then((r) => { if (vivo) setAlunos(r.alunos || []); }).catch((e) => { if (vivo) setErro(e?.error || 'Não foi possível buscar os alunos'); });
    }, 300);
    return () => { vivo = false; clearTimeout(t); };
  }, [termo, open]);
  const comecar = async (a) => {
    setIniciando(a.aluno_id); setErro('');
    try { const r = await iniciar(a.aluno_id); onAbrir(r.oportunidade_id); }
    catch (e) { if (e?.oportunidade_id) onAbrir(e.oportunidade_id); else setErro(e?.error || 'Não foi possível iniciar a prospecção'); }
    finally { setIniciando(null); }
  };
  return <Modal open={open} onClose={onClose} title="Iniciar prospecção" size="md"
    description="Alunos com matrícula cancelada nesta academia. Escolha quem a equipe vai abordar.">
    <div className="ck-pr-form">
      <div className="ck-control">
        <Icon name="search" size={18} className="ck-control__icon" />
        <input type="search" className="ck-control__input" value={termo} placeholder="Buscar por nome ou telefone" aria-label="Buscar aluno" onChange={(e) => setTermo(e.target.value)} />
      </div>
      {erro ? <Callout tone="danger">{erro}</Callout> : null}
      {alunos === null ? <p className="ck-pr-hint">Carregando…</p> : null}
      {alunos && !alunos.length ? <p className="ck-pr-hint">{termo ? 'Nenhum aluno com matrícula cancelada encontrado.' : 'Esta academia não tem matrículas canceladas.'}</p> : null}
      {alunos?.length ? <ul className="ck-pr-list ck-pr-escolha">{alunos.map((a) => <li key={a.aluno_id}>
        <AlunoFoto nome={a.nome} foto={a.foto_caminho} />
        <span className="ck-pr-list__main">
          <strong>{titleCase(a.nome)}</strong>
          <small>{a.modalidade_nome ? `${a.modalidade_nome} · ` : ''}{a.plano_nome} · cancelou em {dataBR(a.data_cancelamento)}</small>
        </span>
        {a.oportunidade_aberta_id
          ? <Button size="sm" onClick={() => onAbrir(a.oportunidade_aberta_id)}>Abrir ficha</Button>
          : Number(a.em_andamento)
            ? <Badge tone="outline" title="Já tem outra matrícula ativa, pendente, vencida ou bloqueada">Matrícula em andamento</Badge>
            : <Button size="sm" variant="primary" loading={iniciando === a.aluno_id} onClick={() => comecar(a)}>Iniciar</Button>}
      </li>)}</ul> : null}
    </div>
  </Modal>;
}

const MOTIVOS_DIAGNOSTICO = [
  ['em_andamento', 'já têm outra matrícula em andamento (voltaram ou trocaram de plano)'],
  ['somente_diarias', 'só cancelaram diárias avulsas'],
  ['fora_da_janela', null],
  ['aguardando_carencia', null],
  ['sem_vinculo_aluno', 'não estão vinculados como aluno nesta academia'],
];

/** Explica por que alunos com matrícula cancelada estão ou não na lista. */
export function DiagnosticoProspeccao({ diagnostico: d, admin, sincronizando, onSincronizar, onIniciar }) {
  if (!d) return null;
  const excluidos = MOTIVOS_DIAGNOSTICO.filter(([k]) => d[k] > 0);
  return <div className="ck-pr-diag">
    <p><strong>{d.matriculas_canceladas}</strong> matrícula{d.matriculas_canceladas === 1 ? '' : 's'} cancelada{d.matriculas_canceladas === 1 ? '' : 's'} nesta academia, de <strong>{d.alunos_com_cancelamento}</strong> aluno{d.alunos_com_cancelamento === 1 ? '' : 's'}. <strong>{d.elegiveis}</strong> entra{d.elegiveis === 1 ? '' : 'm'} na prospecção automaticamente; {d.oportunidades_abertas} em prospecção agora.</p>
    {excluidos.length || d.matriculas_sem_aluno ? <ul>
      {excluidos.map(([k, txt]) => <li key={k}><strong>{d[k]}</strong> {k === 'fora_da_janela' ? `cancelaram há mais de ${d.janela_meses} meses` : k === 'aguardando_carencia' ? `cancelaram há menos de ${d.carencia_meses} ${d.carencia_meses === 1 ? 'mês' : 'meses'} (entram depois, conforme os Parâmetros)` : txt}</li>)}
      {d.matriculas_sem_aluno ? <li><strong>{d.matriculas_sem_aluno}</strong> matrícula{d.matriculas_sem_aluno === 1 ? '' : 's'} sem aluno vinculado (registro antigo)</li> : null}
    </ul> : null}
    <div className="ck-pr-row ck-pr-row--start">
      <Button size="sm" variant="primary" icon="user-plus" onClick={onIniciar}>Escolher aluno</Button>
      <Button size="sm" icon="refresh-cw" loading={sincronizando} onClick={onSincronizar}>{admin ? 'Sincronizar agora' : 'Atualizar lista'}</Button>
    </div>
  </div>;
}
