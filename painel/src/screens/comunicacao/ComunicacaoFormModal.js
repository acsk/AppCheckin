import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Badge, Button, Callout, Fieldset, Icon, IconButton, Modal, SegmentedControl, SelectField, Switch, TextAreaField, TextField } from '../../components/ck';
import comunicacaoService from '../../services/comunicacaoService';
import { showError } from '../../utils/toast';
import { CLASSIFICACOES, PRIORIDADES, PUBLICOS, TIPO, TIPOS, TIPOS_RESPOSTA, fdatetime, fromInput, msg, paraApi } from './comunicacaoUtil';

/**
 * Cadastro e edição. Depois de publicada, tipo, público, início e perguntas ficam fixos (a API também recusa).
 * Props: open, value (estado de paraForm), options ({ modalidades, planos, horarios }), students, saving,
 * canPublish, onCancel, onSave(form, publicar).
 */
// Pergunta nova já vem como escolha única com duas alternativas para preencher
const perguntaNova = () => ({ pergunta: '', tipo_resposta: 'unica', obrigatoria: true, alternativas: ['', ''] });

export default function ComunicacaoFormModal({ open, value, options, students = [], saving, canPublish, onCancel, onSave }) {
  const comPergunta = v => (v.tipo === 'enquete' && !v.id && !v.perguntas.length ? { ...v, perguntas: [perguntaNova()] } : v);
  const [f, setF] = useState(() => comPergunta(value));
  const [tried, setTried] = useState(false);
  const [preview, setPreview] = useState('mobile');
  const [simular, setSimular] = useState(false);
  const [alcance, setAlcance] = useState(null);
  const [busca, setBusca] = useState('');
  const [enviando, setEnviando] = useState(false);
  const arquivoRef = useRef(null);
  useEffect(() => { setF(comPergunta(value)); setTried(false); setAlcance(null); }, [value]);

  const rascunho = f.status === 'rascunho';
  const set = patch => setF(prev => ({ ...prev, ...patch }));
  const setPub = patch => setF(prev => ({ ...prev, publico: { ...prev.publico, ...patch } }));
  const setCfg = patch => setF(prev => ({ ...prev, configuracoes: { ...prev.configuracoes, ...patch } }));
  const setEnq = patch => setCfg({ enquete: { ...f.configuracoes.enquete, ...patch } });

  // Quantos alunos o público alcança (recalcula ao mudar o público)
  const publicoKey = JSON.stringify(f.publico);
  useEffect(() => {
    if (!open || !rascunho) return undefined;
    let vivo = true;
    const t = setTimeout(() => {
      comunicacaoService.previaPublico(f.publico)
        .then(r => vivo && setAlcance({ total: r.total, amostra: r.amostra || [] }))
        .catch(e => vivo && setAlcance({ erro: msg(e, 'Não foi possível calcular.') }));
    }, 400);
    return () => { vivo = false; clearTimeout(t); };
  }, [open, rascunho, publicoKey]);

  const erros = useMemo(() => {
    const e = {};
    if (!f.titulo.trim()) e.titulo = 'Informe o título.';
    if (!['enquete', 'notificacao'].includes(f.tipo) && !f.conteudo.trim() && !f.descricao.trim()) e.conteudo = 'Escreva o conteúdo.';
    if (f.obrigatoria && !f.termino_em) e.termino_em = 'Obrigatória precisa de término: ela trava o check-in até lá.';
    if (f.inicio_em && f.termino_em && f.termino_em <= f.inicio_em) e.termino_em = 'O término precisa ser depois do início.';
    if (f.tipo === 'enquete' && rascunho) {
      if (!f.perguntas.length) e.perguntas = 'Adicione ao menos uma pergunta.';
      f.perguntas.forEach((p, i) => {
        if (!p.pergunta.trim()) e.perguntas = `Pergunta ${i + 1}: escreva o texto.`;
        else if (['unica', 'multipla'].includes(p.tipo_resposta) && p.alternativas.filter(a => a.trim()).length < 2) e.perguntas = `Pergunta ${i + 1}: informe ao menos 2 alternativas.`;
      });
    }
    return e;
  }, [f, rascunho]);
  const err = k => (tried ? erros[k] : undefined);

  const salvar = publicar => {
    setTried(true);
    if (Object.keys(erros).length) return;
    onSave(paraApi(f), publicar);
  };

  async function anexar(e) {
    const arquivo = e.target.files?.[0];
    e.target.value = '';
    if (!arquivo) return;
    setEnviando(true);
    try {
      const r = await comunicacaoService.enviarAnexo(arquivo);
      const a = r.anexo;
      if (a.mime?.startsWith('image/') && !f.configuracoes.imagem_url) setCfg({ imagem_url: a.url });
      else setCfg({ anexos: [...f.configuracoes.anexos, a] });
    } catch (er) {
      showError(msg(er, 'Não foi possível enviar o arquivo.'));
    } finally {
      setEnviando(false);
    }
  }

  const perguntas = f.perguntas;
  const setPergunta = (i, patch) => set({ perguntas: perguntas.map((p, j) => (j === i ? { ...p, ...patch } : p)) });
  const novaPergunta = () => set({ perguntas: [...perguntas, perguntaNova()] });
  const mover = (i, d) => {
    const n = [...perguntas]; const j = i + d;
    if (j < 0 || j >= n.length) return;
    [n[i], n[j]] = [n[j], n[i]]; set({ perguntas: n });
  };

  const alunosEscolhidos = new Set((f.publico.alunos || []).map(Number));
  const alunosFiltrados = busca.trim().length >= 2
    ? students.filter(s => (s.name + ' ' + (s.email || '')).toLowerCase().includes(busca.trim().toLowerCase())).slice(0, 8) : [];
  const toggleLista = (k, v) => {
    const lista = f.publico[k] || [];
    setPub({ [k]: lista.includes(v) ? lista.filter(x => x !== v) : [...lista, v] });
  };
  const horarioKey = h => `${h.modalidade_id}|${h.horario}`;
  const horariosEscolhidos = new Set((f.publico.horarios || []).map(horarioKey));

  const tipoAtual = TIPO[f.tipo];
  const footer = <>
    <Button disabled={saving} onClick={onCancel}>Cancelar</Button>
    {rascunho ? <Button disabled={saving} loading={saving} onClick={() => salvar(false)}>Salvar rascunho</Button> : null}
    {rascunho && canPublish ? <Button variant="primary" icon="send" loading={saving} onClick={() => salvar(true)}>Publicar</Button> : null}
    {!rascunho ? <Button variant="primary" icon="save" loading={saving} onClick={() => salvar(false)}>Salvar alterações</Button> : null}
  </>;

  return <Modal open={open} size="lg" dialogClassName="ck-cm__dlg" title={f.id ? 'Editar comunicação' : 'Nova comunicação'}
    description={f.id && !rascunho ? 'Publicada: tipo, público, início e perguntas não mudam mais. Para mudar, duplique.' : 'Notificações, comunicados, mensagens e enquetes para os alunos no app.'}
    dismissable={!saving} onClose={onCancel} footer={footer}>
    <div className="ck-cm__form">
      <div className="ck-cm__fields">
        <Fieldset title="Informações gerais">
          <div className="ck-cm__tipos" role="radiogroup" aria-label="Tipo">
            {TIPOS.map(t => <button key={t.value} type="button" role="radio" aria-checked={f.tipo === t.value} disabled={!rascunho}
              className={'ck-cm__tipo' + (f.tipo === t.value ? ' is-on' : '')}
              onClick={() => set({ tipo: t.value, ...(t.value === 'enquete' && !perguntas.length ? { perguntas: [perguntaNova()] } : {}) })}>
              <Icon name={t.icon} size={18} glyph /><span>{t.label}</span>
            </button>)}
          </div>
          <TextField label="Título" value={f.titulo} maxLength={160} error={err('titulo')} onChange={e => set({ titulo: e.target.value })} />
          <TextField label="Descrição curta" hint="Aparece na lista e no aviso. Até 500 caracteres." value={f.descricao} maxLength={500} onChange={e => set({ descricao: e.target.value })} />
          {f.tipo !== 'enquete' || f.conteudo ? <TextAreaField label={f.tipo === 'enquete' ? 'Texto de abertura' : 'Conteúdo'} rows={6} value={f.conteudo} error={err('conteudo')}
            hint="Use linhas em branco para separar parágrafos. Links (https://…) ficam clicáveis no app." onChange={e => set({ conteudo: e.target.value })} /> : null}
          <div className="ck-cm__row">
            <SelectField label="Classificação" value={f.classificacao} options={CLASSIFICACOES} onChange={e => set({ classificacao: e.target.value })} />
            <SelectField label="Prioridade" value={f.prioridade} options={PRIORIDADES} onChange={e => set({ prioridade: e.target.value })} />
          </div>
          {f.tipo !== 'notificacao' ? <div className="ck-cm__anexos">
            <div className="ck-cm__anexoshead">
              <strong>Imagem, anexos e links</strong>
              <div className="ck-cm__acts">
                <input ref={arquivoRef} type="file" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" hidden onChange={anexar} />
                <Button size="sm" icon="paperclip" loading={enviando} onClick={() => arquivoRef.current?.click()}>Anexar</Button>
                <Button size="sm" icon="link" onClick={() => setCfg({ links: [...f.configuracoes.links, { rotulo: '', url: '' }] })}>Link</Button>
              </div>
            </div>
            {f.configuracoes.imagem_url ? <div className="ck-cm__img"><img src={f.configuracoes.imagem_url} alt="" />
              <IconButton icon="x" label="Remover imagem" size="sm" onClick={() => setCfg({ imagem_url: '' })} /></div> : null}
            {f.configuracoes.anexos.map((a, i) => <div key={a.url} className="ck-cm__anexo"><Icon name="file-text" size={16} /><span>{a.nome}</span>
              <IconButton icon="x" label={`Remover ${a.nome}`} size="sm" onClick={() => setCfg({ anexos: f.configuracoes.anexos.filter((_, j) => j !== i) })} /></div>)}
            {f.configuracoes.links.map((l, i) => <div key={i} className="ck-cm__link">
              <TextField label={i ? undefined : 'Texto do link'} placeholder="Ex.: Regulamento" value={l.rotulo} onChange={e => setCfg({ links: f.configuracoes.links.map((x, j) => (j === i ? { ...x, rotulo: e.target.value } : x)) })} />
              <TextField label={i ? undefined : 'Endereço'} placeholder="https://" value={l.url} onChange={e => setCfg({ links: f.configuracoes.links.map((x, j) => (j === i ? { ...x, url: e.target.value } : x)) })} />
              <IconButton icon="trash" label="Remover link" size="sm" onClick={() => setCfg({ links: f.configuracoes.links.filter((_, j) => j !== i) })} />
            </div>)}
          </div> : null}
        </Fieldset>

        <Fieldset title="Destinatários" description={rascunho ? 'O público é fixado na publicação. Depois dá para incluir alunos novos que se encaixem.' : 'Fixado na publicação.'}>
          <SegmentedControl label="Público" block options={PUBLICOS} value={f.publico.tipo} onChange={v => rascunho && setPub({ tipo: v })} />
          {f.publico.tipo === 'segmento' ? <div className="ck-cm__seg">
            <p className="ck-cm__hint">Os filtros se somam: o aluno precisa atender a todos os escolhidos.</p>
            <div className="ck-cm__chips" aria-label="Modalidades">
              <span>Modalidades</span>
              {(options.modalidades || []).map(m => <button key={m.id} type="button" disabled={!rascunho} aria-pressed={f.publico.modalidades.includes(m.id)}
                className={'ck-cm__chip' + (f.publico.modalidades.includes(m.id) ? ' is-on' : '')} onClick={() => toggleLista('modalidades', m.id)}>{m.nome}</button>)}
            </div>
            <div className="ck-cm__chips" aria-label="Planos">
              <span>Planos</span>
              {(options.planos || []).map(p => <button key={p.id} type="button" disabled={!rascunho} aria-pressed={f.publico.planos.includes(p.id)}
                className={'ck-cm__chip' + (f.publico.planos.includes(p.id) ? ' is-on' : '')} onClick={() => toggleLista('planos', p.id)}>{p.nome}{p.modalidade_nome ? ` · ${p.modalidade_nome}` : ''}</button>)}
            </div>
            <div className="ck-cm__chips" aria-label="Turmas">
              <span>Turmas (modalidade e horário)</span>
              {(options.horarios || []).map(h => {
                const k = horarioKey(h); const on = horariosEscolhidos.has(k);
                return <button key={k} type="button" disabled={!rascunho} aria-pressed={on} className={'ck-cm__chip' + (on ? ' is-on' : '')}
                  onClick={() => setPub({ horarios: on ? f.publico.horarios.filter(x => horarioKey(x) !== k) : [...f.publico.horarios, { modalidade_id: h.modalidade_id, horario: h.horario }] })}>{h.modalidade_nome} {h.horario}</button>;
              })}
            </div>
            {f.publico.horarios.length ? <TextField label="Frequentou a turma nos últimos (dias)" type="number" min={1} max={365} disabled={!rascunho} value={String(f.publico.dias_checkin)}
              onChange={e => setPub({ dias_checkin: Math.max(1, Math.min(365, +e.target.value || 30)) })} hint="Turma é a aula do dia: entra quem fez check-in nela nesse período." /> : null}
            <Switch label="Só inadimplentes" description="Com parcela vencida em aberto." checked={!!f.publico.inadimplentes} disabled={!rascunho} onChange={v => setPub({ inadimplentes: v })} />
          </div> : null}
          {f.publico.tipo === 'alunos' ? <div className="ck-cm__seg">
            <TextField icon="search" label="Buscar aluno" placeholder="Nome ou e-mail" value={busca} disabled={!rascunho} onChange={e => setBusca(e.target.value)} />
            {alunosFiltrados.length ? <ul className="ck-cm__busca">{alunosFiltrados.map(s => <li key={s.id}>
              <button type="button" onClick={() => toggleLista('alunos', Number(s.id))}>
                <Icon name={alunosEscolhidos.has(Number(s.id)) ? 'check-circle' : 'plus'} size={16} /><span>{s.name}</span><em>{s.email}</em>
              </button></li>)}</ul> : null}
            <div className="ck-cm__chips">
              {[...alunosEscolhidos].map(id => {
                const s = students.find(x => Number(x.id) === id);
                return <span key={id} className="ck-cm__chip is-on">{s?.name || `Aluno #${id}`}
                  {rascunho ? <button type="button" aria-label="Remover" onClick={() => toggleLista('alunos', id)}><Icon name="x" size={12} /></button> : null}</span>;
              })}
            </div>
            <TextField label="Ou pelo número da matrícula" placeholder="Ex.: 427, 463" disabled={!rascunho} value={(f.publico.matriculas || []).join(', ')}
              onChange={e => setPub({ matriculas: e.target.value.split(/[^0-9]+/).filter(Boolean).map(Number) })} />
          </div> : null}
          {rascunho && alcance ? (alcance.erro ? <Callout tone="warning">{alcance.erro}</Callout>
            : <Callout tone={alcance.total ? 'info' : 'warning'} icon="users" title={alcance.total === 1 ? 'Alcança 1 aluno' : `Alcança ${alcance.total} alunos`}>
              {alcance.total ? alcance.amostra.join(', ') + (alcance.total > alcance.amostra.length ? '…' : '') : 'Ninguém corresponde a este público agora.'}
            </Callout>) : null}
        </Fieldset>

        <Fieldset title="Publicação">
          <div className="ck-cm__row">
            <TextField label="Início" type="datetime-local" disabled={!rascunho} value={f.inicio_em} hint="Vazio: assim que publicar." onChange={e => set({ inicio_em: e.target.value })} />
            <TextField label="Término" type="datetime-local" value={f.termino_em} error={err('termino_em')} onChange={e => set({ termino_em: e.target.value })} />
          </div>
          <Switch label={f.tipo === 'enquete' ? 'Resposta obrigatória' : 'Leitura obrigatória'}
            description={f.tipo === 'enquete' ? 'O aluno só faz check-in depois de enviar as respostas.' : 'O aluno só faz check-in depois de tocar em "Li e estou ciente".'}
            checked={f.obrigatoria} onChange={v => set({ obrigatoria: v })} />
          <Switch label="Exibir em destaque ao abrir o app" description={f.obrigatoria ? 'Sempre ligado nas obrigatórias.' : 'Abre como janela uma vez.'}
            checked={f.obrigatoria || f.exibir_modal} disabled={f.obrigatoria} onChange={v => set({ exibir_modal: v })} />
          <Switch label="Manter no histórico depois de encerrar" checked={f.manter_historico} onChange={v => set({ manter_historico: v })} />
          {f.obrigatoria ? <Callout tone="warning" title="Trava o check-in">Enquanto estiver ativa, quem não {f.tipo === 'enquete' ? 'responder' : 'confirmar'} não consegue fazer check-in. O aluno continua usando o resto do app.</Callout> : null}
        </Fieldset>

        {f.tipo === 'enquete' ? <Fieldset title="Enquete" description={rascunho ? null : 'Perguntas fixas após publicar.'}>
          {perguntas.map((p, i) => <div key={i} className="ck-cm__perg">
            <div className="ck-cm__perghead">
              <strong>Pergunta {i + 1}</strong>
              {rascunho ? <div className="ck-cm__acts">
                <IconButton icon="arrow-up" label="Subir" size="sm" disabled={!i} onClick={() => mover(i, -1)} />
                <IconButton icon="arrow-down" label="Descer" size="sm" disabled={i === perguntas.length - 1} onClick={() => mover(i, 1)} />
                <IconButton icon="trash" label="Remover pergunta" size="sm" onClick={() => set({ perguntas: perguntas.filter((_, j) => j !== i) })} />
              </div> : null}
            </div>
            <TextField label="Pergunta" value={p.pergunta} disabled={!rascunho} maxLength={300} onChange={e => setPergunta(i, { pergunta: e.target.value })} />
            <div className="ck-cm__row">
              <SelectField label="Tipo de resposta" value={p.tipo_resposta} disabled={!rascunho} options={TIPOS_RESPOSTA}
                onChange={e => setPergunta(i, { tipo_resposta: e.target.value, alternativas: p.alternativas.length >= 2 ? p.alternativas : ['', ''] })} />
              <Switch label="Obrigatória" checked={p.obrigatoria !== false} disabled={!rascunho} onChange={v => setPergunta(i, { obrigatoria: v })} />
            </div>
            {['unica', 'multipla'].includes(p.tipo_resposta) ? <div className="ck-cm__alts">
              {p.alternativas.map((a, k) => <div key={k} className="ck-cm__alt">
                <TextField placeholder={`Alternativa ${k + 1}`} value={a} disabled={!rascunho} maxLength={200}
                  onChange={e => setPergunta(i, { alternativas: p.alternativas.map((x, j) => (j === k ? e.target.value : x)) })} />
                {rascunho && p.alternativas.length > 2 ? <IconButton icon="x" label="Remover alternativa" size="sm" onClick={() => setPergunta(i, { alternativas: p.alternativas.filter((_, j) => j !== k) })} /> : null}
              </div>)}
              {rascunho && p.alternativas.length < 20 ? <Button size="sm" variant="ghost" icon="plus" onClick={() => setPergunta(i, { alternativas: [...p.alternativas, ''] })}>Alternativa</Button> : null}
            </div> : null}
          </div>)}
          {err('perguntas') ? <Callout tone="danger">{err('perguntas')}</Callout> : null}
          {rascunho ? <Button icon="plus" onClick={novaPergunta}>Adicionar pergunta</Button> : null}
          <div className="ck-cm__grid2">
            <Switch label="Respostas anônimas" description="Os relatórios mostram só a participação, nunca quem respondeu o quê." checked={f.configuracoes.enquete.anonima} disabled={!rascunho}
              onChange={v => setEnq({ anonima: v, permitir_alterar: v ? false : f.configuracoes.enquete.permitir_alterar })} />
            <Switch label="Permitir alterar a resposta" description={f.configuracoes.enquete.anonima ? 'Indisponível em enquete anônima.' : 'Enquanto estiver ativa.'}
              checked={f.configuracoes.enquete.permitir_alterar} disabled={f.configuracoes.enquete.anonima} onChange={v => setEnq({ permitir_alterar: v })} />
            <Switch label="Mostrar resultados aos alunos" description="Depois que o aluno responde." checked={f.configuracoes.enquete.exibir_resultados} onChange={v => setEnq({ exibir_resultados: v })} />
            <Switch label="Mostrar total de participantes" checked={f.configuracoes.enquete.exibir_participantes} onChange={v => setEnq({ exibir_participantes: v })} />
          </div>
        </Fieldset> : null}
      </div>

      <aside className="ck-cm__prev" aria-label="Pré-visualização">
        <div className="ck-cm__prevbar">
          <SegmentedControl label="Pré-visualização" options={[{ value: 'mobile', label: 'Celular', icon: 'smartphone' }, { value: 'desktop', label: 'Tela larga', icon: 'monitor' }]} value={preview} onChange={setPreview} />
        </div>
        {f.obrigatoria ? <Switch label="Simular obrigatória" description="Como o aluno vê ao abrir o app." checked={simular} onChange={setSimular} /> : null}
        <div className={'ck-cm__device is-' + preview}>
          <div className="ck-cm__screen">
            {simular && f.obrigatoria ? <div className="ck-cm__pmodal">
              <span className="ck-cm__pcount">Comunicado importante</span>
              <strong>{f.titulo || 'Título da comunicação'}</strong>
              <p>{f.descricao || f.conteudo.slice(0, 180) || 'Conteúdo…'}</p>
              {f.termino_em ? <small>Vigência: {f.inicio_em ? fdatetime(fromInput(f.inicio_em)).slice(0, 5) : 'agora'} a {fdatetime(fromInput(f.termino_em)).slice(0, 5)}</small> : null}
              <span className="ck-cm__pbtn">{f.tipo === 'enquete' ? 'Enviar respostas' : 'Li e estou ciente'}</span>
              <span className="ck-cm__pnote"><Icon name="lock" size={12} /> Check-in indisponível até concluir</span>
            </div> : <div className={'ck-cm__pcard is-' + f.classificacao}>
              <div className="ck-cm__pcardhead">
                <span className="ck-cm__picon"><Icon name={f.icone || tipoAtual?.icon || 'bell'} size={18} glyph /></span>
                <div><strong>{f.titulo || 'Título da comunicação'}</strong><small>{tipoAtual?.label}{f.obrigatoria ? ' · Obrigatória' : ''}</small></div>
              </div>
              {f.configuracoes.imagem_url ? <img src={f.configuracoes.imagem_url} alt="" /> : null}
              {f.descricao ? <p className="ck-cm__pdesc">{f.descricao}</p> : null}
              {f.conteudo ? <p>{f.conteudo}</p> : null}
              {f.tipo === 'enquete' ? <ol className="ck-cm__pperg">{perguntas.slice(0, 3).map((p, i) => <li key={i}>
                <span>{p.pergunta || 'Pergunta'}</span>
                {['unica', 'multipla'].includes(p.tipo_resposta) ? <ul className="ck-cm__popts">{p.alternativas.map((a, k) => <li key={k}>
                  <span className={'ck-cm__pmark' + (p.tipo_resposta === 'multipla' ? ' is-box' : '')} aria-hidden />{a || `Alternativa ${k + 1}`}</li>)}</ul>
                  : p.tipo_resposta === 'sim_nao' ? <div className="ck-cm__pyn"><span>Sim</span><span>Não</span></div>
                    : p.tipo_resposta === 'avaliacao' ? <div className="ck-cm__pstars" aria-hidden>★★★★★</div>
                      : <div className="ck-cm__ptext">Resposta do aluno…</div>}
              </li>)}</ol> : null}
              {f.configuracoes.links.filter(l => l.url).map((l, i) => <span key={i} className="ck-cm__plink"><Icon name="link" size={13} /> {l.rotulo || l.url}</span>)}
              {f.tipo !== 'notificacao' || f.obrigatoria ? <span className="ck-cm__pbtn">{f.tipo === 'enquete' ? 'Enviar respostas' : 'Li e estou ciente'}</span> : null}
            </div>}
          </div>
        </div>
        {f.obrigatoria ? <Badge tone="warning" icon="lock">Trava o check-in</Badge> : null}
      </aside>
    </div>
  </Modal>;
}
