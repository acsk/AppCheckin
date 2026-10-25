import React, { useEffect, useMemo, useState } from 'react';
import AppMobileLayout from '../superadmin/AppMobileLayout';
import { Badge, Button, Callout, IconButton, Modal, SelectField, Switch, Tabs, TextAreaField, TextField } from '../../components/ck';
import { Panel } from '../../components/ck/pessoas';
import monetizacaoService from '../../services/monetizacaoService';
import { showSuccess } from '../../utils/toast';

const clone = (v) => JSON.parse(JSON.stringify(v));
const dataHora = (v) => v ? new Date(String(v).replace(' ', 'T')).toLocaleString('pt-BR') : '—';
const linhas = (v) => v.split('\n');
const FORMATOS = { banner: 'Banner', adaptativo: 'Banner adaptativo', interstitial: 'Intersticial', rewarded: 'Recompensado', rewarded_interstitial: 'Intersticial recompensado' };
const vazio = { identificador: '', nome: '', formato: 'banner', unidade_id: '', rota: '', evento: '', habilitado: false, prioridade: 0 };

function PosicionamentoModal({ item, adsense, onClose, onSave }) {
  const [f, setF] = useState(item.valor);
  const [erro, setErro] = useState('');
  const set = (k, v) => setF((p) => ({ ...p, [k]: v }));
  const salvar = () => {
    if (!/^[a-z][a-z0-9_]*$/.test(f.identificador) || !f.nome.trim()) { setErro('Informe nome e identificador válido (letras minúsculas, números e sublinhado).'); return; }
    if (f.habilitado && !f.unidade_id.trim()) { setErro('Informe o identificador do anúncio antes de habilitar o posicionamento.'); return; }
    const mensagem = onSave({ ...f, prioridade: Number(f.prioridade) || 0 });
    if (mensagem) setErro(mensagem);
  };
  return <Modal open size="md" title={item.index === null ? 'Novo posicionamento' : 'Editar posicionamento'} onClose={onClose}
    description="As mudanças entram em vigor ao salvar a configuração da aplicação."
    footer={<><span style={{ flex: 1 }} /><Button variant="ghost" onClick={onClose}>Cancelar</Button><Button variant="primary" onClick={salvar}>Aplicar</Button></>}>
    <div className="ck-mc-form">
      {erro ? <Callout tone="danger">{erro}</Callout> : null}
      <TextField label="Nome" value={f.nome} maxLength={120} onChange={(e) => set('nome', e.target.value)} />
      <TextField label="Identificador" value={f.identificador} maxLength={80} hint="Ex.: mobile_inicio. Único nesta aplicação e ambiente." onChange={(e) => set('identificador', e.target.value)} />
      <SelectField label="Formato" value={f.formato} options={Object.entries(FORMATOS).filter(([k]) => !adsense || k === 'banner').map(([value, label]) => ({ value, label }))} onChange={(e) => set('formato', e.target.value)} />
      <TextField label={adsense ? 'Ad Slot ID' : 'Ad Unit ID'} value={f.unidade_id} placeholder={adsense ? '1234567890' : 'ca-app-pub-XXXXXXXXXXXXXXXX/XXXXXXXXXX'} onChange={(e) => set('unidade_id', e.target.value.trim())} />
      <TextField label="Rota" value={f.rota} placeholder="/account" onChange={(e) => set('rota', e.target.value)} />
      {!adsense ? <TextField label="Evento de exibição" value={f.evento} placeholder="transicao_natural" onChange={(e) => set('evento', e.target.value)} /> : null}
      <TextField label="Prioridade" type="number" min={0} max={10000} value={String(f.prioridade)} onChange={(e) => set('prioridade', e.target.value)} />
      <Switch label="Posicionamento habilitado" checked={f.habilitado} onChange={(v) => set('habilitado', v)} />
    </div>
  </Modal>;
}

function MonetizacaoEditor({ provedor }) {
  const adsense = provedor === 'adsense';
  const [ambiente, setAmbiente] = useState('development');
  const [dados, setDados] = useState(null);
  const [id, setId] = useState('');
  const [f, setF] = useState(null);
  const [global, setGlobal] = useState(false);
  const [aba, setAba] = useState('configuracoes');
  const [erro, setErro] = useState('');
  const [saving, setSaving] = useState(false);
  const [recarregar, setRecarregar] = useState(0);
  const [posicao, setPosicao] = useState(null);
  const [excluir, setExcluir] = useState(null);
  const [contexto, setContexto] = useState(null);
  const [auditoria, setAuditoria] = useState(null);
  const atual = dados?.configuracoes.find((c) => String(c.id) === id);
  const sujo = !!f && !!atual && (JSON.stringify(f) !== JSON.stringify(atual) || global !== dados.global.habilitado);

  useEffect(() => {
    let ativo = true;
    setDados(null); setF(null); setErro('');
    monetizacaoService.listar(provedor, ambiente).then((r) => {
      if (!ativo) return;
      setDados(r); setId((anterior) => r.configuracoes.some((c) => String(c.id) === anterior) ? anterior : String(r.configuracoes[0]?.id || ''));
    }).catch((e) => { if (ativo) setErro(e.message); });
    return () => { ativo = false; };
  }, [provedor, ambiente, recarregar]);
  useEffect(() => {
    if (atual) { setF(clone(atual)); setGlobal(dados.global.habilitado); }
  }, [atual, dados]);

  const set = (k, v) => setF((p) => ({ ...p, [k]: v }));
  const regra = (k, v) => setF((p) => ({ ...p, configuracoes: { ...p.configuracoes, [k]: v } }));
  const mudar = (tipo, valor) => {
    if (sujo) { setContexto({ tipo, valor }); return; }
    if (tipo === 'ambiente') setAmbiente(valor);
    else if (tipo === 'aplicacao') setId(valor);
    else setRecarregar((v) => v + 1);
  };
  const salvar = async () => {
    if (!f || saving) return;
    setSaving(true); setErro('');
    try {
      const r = await monetizacaoService.salvar(provedor, f.id, {
        versao: f.versao, versao_global: dados.global.versao, global_habilitado: global, habilitado: f.habilitado,
        publisher_id: f.publisher_id, app_id: f.app_id, dominio: f.dominio,
        configuracoes: { ...f.configuracoes,
          rotas_permitidas: f.configuracoes.rotas_permitidas.map((v) => v.trim()).filter(Boolean),
          rotas_bloqueadas: f.configuracoes.rotas_bloqueadas.map((v) => v.trim()).filter(Boolean),
        }, posicionamentos: f.posicionamentos,
      });
      setDados(r); showSuccess('Configuração de monetização salva');
    } catch (e) { setErro(e.message); }
    finally { setSaving(false); }
  };
  const alertas = useMemo(() => !f ? [] : [
    ...(adsense ? [!f.publisher_id && 'Publisher ID não configurado.', !f.dominio && 'Domínio não configurado.'] : [!f.app_id && 'App ID não configurado.']),
    !f.posicionamentos.some((p) => p.habilitado) && 'Nenhum posicionamento habilitado.',
    ...f.posicionamentos.filter((p) => p.habilitado && !p.unidade_id).map((p) => `${p.nome}: identificador de anúncio ausente.`),
  ].filter(Boolean), [f, adsense]);

  return <div className="ck-mc ck-monetizacao">
    <div className="ck-monetizacao__context">
      <SelectField label="Ambiente" value={ambiente} disabled={saving} options={[{ value: 'development', label: 'Desenvolvimento' }, { value: 'production', label: 'Produção' }]} onChange={(e) => mudar('ambiente', e.target.value)} />
      <SelectField label="Aplicação" value={id} disabled={saving || !dados} options={(dados?.configuracoes || []).map((c) => ({ value: String(c.id), label: c.aplicacao_nome }))} onChange={(e) => mudar('aplicacao', e.target.value)} />
      <Button icon="refresh-cw" variant="ghost" disabled={saving} onClick={() => mudar('recarregar')}>Recarregar</Button>
    </div>
    {erro ? <Callout tone="danger" title="Não foi possível concluir">{erro}</Callout> : null}
    {!f ? (!erro ? <p role="status" className="ck-mc-hint">Carregando monetização…</p> : null) : <fieldset disabled={saving} className="ck-monetizacao__form">
      <div className="ck-monetizacao__stats">
        <div><small>Monetização geral · {ambiente === 'production' ? 'produção' : 'desenvolvimento'}</small><Badge tone={global ? 'success' : 'outline'}>{global ? 'Habilitada' : 'Desativada'}</Badge></div>
        <div><small>Configuração da aplicação</small><Badge tone={f.habilitado ? 'success' : 'outline'}>{f.habilitado ? 'Habilitada' : 'Desativada'}</Badge></div>
        <div><small>Posicionamentos habilitados</small><strong>{f.posicionamentos.filter((p) => p.habilitado).length} / {f.posicionamentos.length}</strong></div>
        <div><small>Última atualização salva</small><strong>{dataHora(f.updated_at)}</strong><small>Versão {f.versao}</small></div>
      </div>
      <Callout tone="info" title="Configuração administrativa">{adsense ? 'O carregamento do script e a exibição de anúncios no site ainda precisam ser integrados.' : 'O SDK de anúncios ainda precisa ser integrado ao aplicativo nativo. Cadastrar IDs aqui não instala o SDK.'} Nenhuma receita, aprovação ou execução de anúncio é presumida por esta tela.</Callout>
      <Tabs className="ck-mc-tabs" label="Áreas da monetização" value={aba} onChange={setAba} tabs={[
        { value: 'configuracoes', label: 'Configurações' }, { value: 'posicionamentos', label: 'Posicionamentos' },
        { value: 'regras', label: 'Regras de exibição' }, { value: 'diagnostico', label: 'Diagnóstico e auditoria' },
      ]} />
      {aba === 'configuracoes' ? <Panel title="Identificação e ativação" description="IDs públicos do Google. Não cadastre credenciais de API ou código JavaScript.">
        <div className="ck-mc-form">
          <Switch label="Monetização global neste ambiente" description="Controle geral compartilhado entre AdSense e AdMob e entre todas as aplicações deste ambiente." checked={global} disabled={saving} onChange={setGlobal} />
          <Switch label={`Habilitar Google ${adsense ? 'AdSense' : 'AdMob'} nesta aplicação`} checked={f.habilitado} disabled={saving} onChange={(v) => set('habilitado', v)} />
          {adsense ? <>
            <TextField label="Publisher ID" value={f.publisher_id} placeholder="ca-pub-XXXXXXXXXXXXXXXX" onChange={(e) => set('publisher_id', e.target.value.trim())} />
            <TextField label="Domínio autorizado na conta Google" hint="Informe apenas o domínio, sem https:// ou caminho. A aprovação deve ser conferida na conta Google." value={f.dominio} placeholder="mobile.appcheckin.com.br" onChange={(e) => set('dominio', e.target.value.trim())} />
            <SelectField label="Tipo de anúncios" value={f.configuracoes.modo} options={[{ value: 'manual', label: 'Manuais' }, { value: 'automatico', label: 'Automáticos' }]} onChange={(e) => regra('modo', e.target.value)} />
          </> : <>
            <Badge tone="info">{f.plataforma === 'android' ? 'Android' : 'iOS'}</Badge>
            <TextField label="AdMob App ID" value={f.app_id} placeholder="ca-app-pub-XXXXXXXXXXXXXXXX~XXXXXXXXXX" hint="O App ID contém ~. As unidades de anúncio usam / e são cadastradas em Posicionamentos." onChange={(e) => set('app_id', e.target.value.trim())} />
            <Callout tone="warning">O App ID utilizado para inicialização nativa deve acompanhar o build Android/iOS. Sua alteração pode exigir nova versão do aplicativo.</Callout>
          </>}
        </div>
      </Panel> : null}
      {aba === 'regras' ? <Panel title="Elegibilidade" description="Regras armazenadas para a futura integração de exibição. Não substituem o consentimento e a verificação de elegibilidade do provedor.">
        <div className="ck-mc-form">
          {[['gratuitos', 'Permitir para usuários gratuitos'], ['premium', 'Permitir para usuários Premium'], ...(adsense ? [['desktop', 'Permitir em desktop'], ['mobile_web', 'Permitir em navegadores móveis']] : []), ['exigir_consentimento', 'Exigir consentimento de publicidade'], ['somente_maiores', 'Restringir a maiores de idade']].map(([k, label]) => <Switch key={k} label={label} checked={f.configuracoes[k]} onChange={(v) => regra(k, v)} />)}
          <TextAreaField label="Rotas permitidas" hint="Uma por linha, iniciando com /. Vazio: sem lista de permissão específica." value={f.configuracoes.rotas_permitidas.join('\n')} onChange={(e) => regra('rotas_permitidas', linhas(e.target.value))} />
          <TextAreaField label="Rotas bloqueadas" hint="Uma por linha. Ex.: /checkin e /pagamento." value={f.configuracoes.rotas_bloqueadas.join('\n')} onChange={(e) => regra('rotas_bloqueadas', linhas(e.target.value))} />
        </div>
      </Panel> : null}
      {aba === 'posicionamentos' ? <Panel title="Posicionamentos" description="Candidatos à exibição, sujeitos à integração e avaliação de elegibilidade." actions={<Button size="sm" icon="plus" onClick={() => setPosicao({ index: null, valor: { ...vazio } })}>Novo posicionamento</Button>}>
        <div className="ck-pc__wrap"><table className="ck-pc__table ck-monetizacao__table"><caption className="ck-sr">Posicionamentos de anúncios</caption>
          <thead><tr><th>Posicionamento</th><th>Formato</th><th>{adsense ? 'Ad Slot ID' : 'Ad Unit ID'}</th><th>Rota / evento</th><th>Situação</th><th>Ações</th></tr></thead>
          <tbody>{f.posicionamentos.map((p, i) => <tr key={`${i}-${p.identificador}`}>
            <td><strong>{p.nome}</strong><small>{p.identificador}</small></td><td>{FORMATOS[p.formato]}</td><td><code>{p.unidade_id || 'Não configurado'}</code></td><td>{p.rota || '—'}<small>{p.evento}</small></td>
            <td><Badge tone={p.habilitado ? 'success' : 'outline'}>{p.habilitado ? 'Habilitado' : 'Desativado'}</Badge></td>
            <td><div className="ck-monetizacao__actions"><IconButton size="sm" icon="pencil" label={`Editar ${p.nome}`} onClick={() => setPosicao({ index: i, valor: { ...p } })} /><IconButton size="sm" icon="trash" label={`Remover ${p.nome}`} onClick={() => setExcluir(i)} /></div></td>
          </tr>)}{!f.posicionamentos.length ? <tr><td colSpan={6}>Nenhum posicionamento cadastrado.</td></tr> : null}</tbody>
        </table></div>
      </Panel> : null}
      {aba === 'diagnostico' ? <>
        <Panel title="Diagnóstico de configuração">
          <ul className="ck-monetizacao__checks"><li>Plataforma: <strong>{f.plataforma}</strong></li><li>Aplicação: <strong>{f.aplicacao_nome}</strong></li><li>Integração de exibição: <Badge tone="warning">Pendente</Badge></li><li>Aprovação Google: <strong>Consultar a conta do provedor</strong></li></ul>
          {alertas.map((a) => <Callout key={a} tone="warning">{a}</Callout>)}
          {!alertas.length ? <Callout tone="success">Os campos necessários estão preenchidos. Isso não confirma aprovação, execução de anúncios ou integração do SDK.</Callout> : null}
        </Panel>
        <Panel title="Auditoria" description="Últimas 50 gravações deste provedor e ambiente.">
          <div className="ck-pc__wrap"><table className="ck-pc__table ck-monetizacao__audit"><thead><tr><th>Data</th><th>Aplicação</th><th>Responsável</th><th>Versão</th><th>Detalhes</th></tr></thead>
            <tbody>{dados.auditoria.map((a) => <tr key={a.id}><td>{dataHora(a.created_at)}</td><td>{a.depois.aplicacao_nome}</td><td>{a.usuario_id ? `Usuário #${a.usuario_id}` : 'Sistema'}</td><td>{a.antes.versao} → {a.depois.versao}</td><td><IconButton size="sm" icon="eye" label={`Ver alteração ${a.id}`} onClick={() => setAuditoria(a)} /></td></tr>)}
              {!dados.auditoria.length ? <tr><td colSpan={5}>Nenhuma alteração registrada.</td></tr> : null}</tbody>
          </table></div>
        </Panel>
      </> : null}
      <div className="ck-monetizacao__save"><span className="ck-mc-hint">{sujo ? 'Há alterações não salvas.' : 'Configuração salva.'}</span>
        <Button variant="ghost" disabled={!sujo || saving} onClick={() => { setF(clone(atual)); setGlobal(dados.global.habilitado); }}>Descartar</Button>
        <Button variant="primary" icon="save" disabled={!sujo} loading={saving} onClick={salvar}>Salvar alterações</Button>
      </div>
    </fieldset>}
    {posicao ? <PosicionamentoModal item={posicao} adsense={adsense} onClose={() => setPosicao(null)} onSave={(p) => {
      if (f.posicionamentos.some((x, i) => x.identificador === p.identificador && i !== posicao.index)) return 'Identificador de posicionamento duplicado.';
      const list = [...f.posicionamentos]; if (posicao.index === null) list.push(p); else list[posicao.index] = p;
      set('posicionamentos', list); setPosicao(null);
    }} /> : null}
    <Modal open={!!auditoria} size="lg" title="Detalhes da alteração" description={auditoria ? dataHora(auditoria.created_at) : ''} onClose={() => setAuditoria(null)}>
      {auditoria ? <div className="ck-mc-form"><TextAreaField label="Antes" readOnly rows={10} value={JSON.stringify(auditoria.antes, null, 2)} /><TextAreaField label="Depois" readOnly rows={10} value={JSON.stringify(auditoria.depois, null, 2)} /></div> : null}
    </Modal>
    <Modal open={excluir !== null} size="sm" title="Remover posicionamento?" description="A remoção será gravada ao salvar as alterações da aplicação." onClose={() => setExcluir(null)}
      footer={<><span style={{ flex: 1 }} /><Button variant="ghost" onClick={() => setExcluir(null)}>Cancelar</Button><Button variant="danger" onClick={() => { set('posicionamentos', f.posicionamentos.filter((_, i) => i !== excluir)); setExcluir(null); }}>Remover</Button></>} />
    <Modal open={!!contexto} size="sm" title="Descartar alterações não salvas?" description="A troca de contexto descarta as alterações desta aplicação." onClose={() => setContexto(null)}
      footer={<><Button variant="ghost" onClick={() => setContexto(null)}>Continuar editando</Button><Button variant="danger" onClick={() => { const c = contexto; setContexto(null); if (c.tipo === 'ambiente') setAmbiente(c.valor); else if (c.tipo === 'aplicacao') setId(c.valor); else setRecarregar((v) => v + 1); }}>Descartar e continuar</Button></>} />
  </div>;
}

export default function MonetizacaoScreen({ provedor }) {
  return <AppMobileLayout title={provedor === 'adsense' ? 'Google AdSense' : 'Google AdMob'} description={provedor === 'adsense' ? 'Configurações de monetização das aplicações web.' : 'Configurações de monetização das aplicações Android e iOS.'}>
    <MonetizacaoEditor key={provedor} provedor={provedor} />
  </AppMobileLayout>;
}
