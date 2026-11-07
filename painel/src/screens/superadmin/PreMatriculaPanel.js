import React, { useCallback, useEffect, useState } from 'react';
import { Badge, Button, Callout, Modal, SegmentedControl, SelectField, Switch, TextAreaField, TextField } from '../../components/ck';
import { Panel } from '../../components/ck/pessoas';
import { superAdminService } from '../../services/superAdminService';
import preMatriculaService from '../../services/preMatriculaService';
import { showError, showSuccess } from '../../utils/toast';
import { DIAS_SEMANA, agruparPorTurma, exportarCsvPorTurma, exportarPdfPorTurma, rotuloPlano, rotuloTurma } from '../../utils/relatorioPreMatriculaTurmas';

const dias = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
const statusLabel = { pendente: 'Aguardando aprovação', aprovada: 'Aprovada', reprovada: 'Reprovada' };
const matriculaStatusLabel = { ativa: 'ativa', pendente: 'pendente', vencida: 'vencida', cancelada: 'cancelada', aguardando_vaga: 'aguardando vaga' };
const publicoLabel = {
  sem_matricula: 'Sem matrícula ativa',
  com_matricula: 'Com matrícula ativa',
  ambos: 'Com ou sem matrícula ativa',
};
const contagemLabel = {
  dia_vencimento: 'A partir do dia de vencimento do aluno',
  aprovacao: 'A partir da aprovação',
};
const contagemHint = {
  dia_vencimento: 'Ex.: aprovado dia 06 com vencimento dia 10, o ciclo vai de 10/11 a 10/12 (plano de 30 dias): o acesso é gratuito até 10/12 e a cobrança começa pela compra do plano pago no app.',
  aprovacao: 'Ex.: aprovado dia 06, o acesso gratuito dura a duração do plano (30 dias), até 06/12.',
};
const semAcento = (texto) => String(texto || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const novoPar = () => ({ plano_free_id: '', plano_pago_id: '' });
// A API envia o instante em UTC (ISO com "Z"); exibe no fuso da academia
const formatarData = (valor) => {
  if (!valor) return '—';
  const data = new Date(valor);
  return Number.isNaN(data.getTime())
    ? String(valor)
    : data.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Sao_Paulo' });
};

const formatarDia = (valor) => {
  const [ano, mes, dia] = String(valor).slice(0, 10).split('-');
  return ano && mes && dia ? `${dia}/${mes}/${ano}` : String(valor);
};

export default function PreMatriculaPanel({ modoAcademia = false }) {
  const [academias, setAcademias] = useState([]);
  const [tenantId, setTenantId] = useState('');
  const [dados, setDados] = useState(null);
  const [config, setConfig] = useState({ ativa: false, obrigatoria: false, publico: 'sem_matricula', contagem_gratuito: 'dia_vencimento', pares: [] });
  const [configAberta, setConfigAberta] = useState(false);
  const [excluirConfig, setExcluirConfig] = useState(false);
  const [filtroStatus, setFiltroStatus] = useState('pendente');
  const [busca, setBusca] = useState('');
  const [filtroPlano, setFiltroPlano] = useState('');
  const [filtroDia, setFiltroDia] = useState('');
  const [filtroTurma, setFiltroTurma] = useState('');
  const [revisao, setRevisao] = useState(null);
  const [excluir, setExcluir] = useState(null);
  const [edicaoPlanoId, setEdicaoPlanoId] = useState('');
  const [edicaoGrade, setEdicaoGrade] = useState([]);
  const [edicaoVencimento, setEdicaoVencimento] = useState('');
  const [observacao, setObservacao] = useState('');
  const [erro, setErro] = useState('');
  const [erroModal, setErroModal] = useState('');
  const [carregando, setCarregando] = useState(true);
  const [salvando, setSalvando] = useState(false);

  const carregar = useCallback(async (id = tenantId) => {
    if (!modoAcademia && !id) return;
    setCarregando(true);
    setErro('');
    try {
      const resultado = modoAcademia
        ? await preMatriculaService.listarAcademia()
        : await preMatriculaService.listar(id);
      setDados(resultado);
      if (resultado.configuracao) {
        setConfig({
          ativa: !!resultado.configuracao.ativa,
          obrigatoria: !!resultado.configuracao.obrigatoria,
          publico: resultado.configuracao.publico || 'sem_matricula',
          contagem_gratuito: resultado.configuracao.contagem_gratuito || 'dia_vencimento',
          pares: (resultado.configuracao.pares || []).map((par) => ({
            plano_free_id: String(par.plano_free_id),
            plano_pago_id: String(par.plano_pago_id),
          })),
        });
      }
    } catch (error) {
      setErro(error.message);
    } finally {
      setCarregando(false);
    }
  }, [tenantId, modoAcademia]);

  useEffect(() => {
    if (modoAcademia) return undefined;
    let active = true;
    superAdminService.listarAcademias()
      .then((resultado) => {
        if (!active) return;
        const lista = (resultado.academias || []).map((academia) => ({ id: Number(academia.id), nome: academia.nome }));
        setAcademias(lista);
        if (lista.length) setTenantId(String(lista[0].id));
        else setCarregando(false);
      })
      .catch((error) => {
        if (!active) return;
        setErro(mensagem(error, 'Não foi possível carregar as academias.'));
        setCarregando(false);
      });
    return () => { active = false; };
  }, [modoAcademia]);

  useEffect(() => {
    if (modoAcademia) void carregar('');
    else if (tenantId) void carregar(tenantId);
  }, [modoAcademia, tenantId, carregar]);

  const catalogo = dados?.catalogo || { gratuitos: [], pagos: [] };
  const paresInvalidos = (dados?.configuracao?.pares || []).filter((par) => !par.valido);

  const atualizarPar = (indice, campos) => setConfig((atual) => ({
    ...atual,
    pares: atual.pares.map((par, i) => (i === indice ? { ...par, ...campos } : par)),
  }));

  const salvar = async () => {
    if (!tenantId || salvando) return;
    if (config.pares.some((par) => !par.plano_free_id || !par.plano_pago_id)) {
      setErroModal('Complete ou remova os vínculos sem plano gratuito ou plano pago.');
      return;
    }
    setSalvando(true);
    setErroModal('');
    try {
      await preMatriculaService.salvarConfiguracao({
        tenant_id: Number(tenantId),
        ativa: config.ativa,
        obrigatoria: config.obrigatoria,
        publico: config.publico,
        contagem_gratuito: config.contagem_gratuito,
        pares: config.pares.map((par) => ({
          plano_free_id: Number(par.plano_free_id),
          plano_pago_id: Number(par.plano_pago_id),
        })),
      });
      setConfigAberta(false);
      showSuccess('Configuração da pré-matrícula salva.');
      await carregar(tenantId);
    } catch (error) {
      setErroModal(error.message);
      showError(error.message);
    } finally {
      setSalvando(false);
    }
  };

  const removerConfiguracao = async () => {
    if (!tenantId || salvando) return;
    setSalvando(true);
    setErro('');
    try {
      await preMatriculaService.excluirConfiguracao(Number(tenantId));
      setExcluirConfig(false);
      showSuccess('Configuração removida. Os pedidos recebidos foram preservados.');
      await carregar(tenantId);
    } catch (error) {
      setErro(error.message);
      showError(error.message);
    } finally {
      setSalvando(false);
    }
  };

  const abrirRevisao = (item) => {
    setRevisao(item);
    setErroModal('');
    setObservacao('');
    setEdicaoPlanoId(String(item.plano_id));
    setEdicaoGrade(item.grade.map((slot) => ({
      turma_fixa_id: String(slot.turma_fixa_id),
      dia_semana: Number(slot.dia_semana),
    })));
    setEdicaoVencimento(item.dia_vencimento ? String(item.dia_vencimento) : '');
  };

  const planoEdicao = (dados?.planos || []).find((plano) => plano.id === Number(edicaoPlanoId));
  const gradeCompleta = !!planoEdicao
    && edicaoGrade.length === Number(planoEdicao.checkins_semanais)
    && edicaoGrade.every((slot) => slot.turma_fixa_id && slot.dia_semana);
  const vencimentoValido = edicaoVencimento !== '' && Number.isInteger(Number(edicaoVencimento)) && Number(edicaoVencimento) >= 1 && Number(edicaoVencimento) <= 31;
  const formularioValido = gradeCompleta && vencimentoValido;
  const dadosEdicao = () => ({
    plano_id: Number(edicaoPlanoId),
    grade: edicaoGrade.map((slot) => ({
      turma_fixa_id: Number(slot.turma_fixa_id),
      dia_semana: Number(slot.dia_semana),
    })),
    dia_vencimento: Number(edicaoVencimento),
  });
  const houveAjuste = () => {
    if (!revisao) return false;
    const atual = dadosEdicao();
    const original = revisao.grade.map((slot) => `${slot.turma_fixa_id}:${slot.dia_semana}`).sort().join('|');
    const novo = atual.grade.map((slot) => `${slot.turma_fixa_id}:${slot.dia_semana}`).sort().join('|');
    return atual.plano_id !== revisao.plano_id || novo !== original || (atual.dia_vencimento ?? null) !== (revisao.dia_vencimento ?? null);
  };

  const persistirAjuste = () => (modoAcademia
    ? preMatriculaService.atualizarAcademia(revisao.id, dadosEdicao())
    : preMatriculaService.atualizar(revisao.id, Number(tenantId), dadosEdicao()));

  const salvarAjuste = async () => {
    if (!revisao || !formularioValido || salvando) return;
    setSalvando(true);
    setErroModal('');
    try {
      const resultado = await persistirAjuste();
      setRevisao(null);
      showSuccess(resultado?.message || 'Pré-matrícula atualizada.');
      await carregar(tenantId);
    } catch (error) {
      setErroModal(error.message);
      showError(error.message);
    } finally {
      setSalvando(false);
    }
  };

  const decidir = async (acao) => {
    if (!revisao || salvando) return;
    if (acao === 'aprovar' && !formularioValido) {
      setErroModal('Complete o plano, os dias e horários e o dia de vencimento antes de aprovar.');
      return;
    }
    setSalvando(true);
    setErroModal('');
    try {
      // Aprovar usa os dados da modal: o ajuste é gravado antes de criar a matrícula
      if (acao === 'aprovar' && houveAjuste()) await persistirAjuste();
      const resultado = modoAcademia
        ? await preMatriculaService.avaliarAcademia(revisao.id, acao, observacao)
        : await preMatriculaService.avaliar(revisao.id, Number(tenantId), acao, observacao);
      showSuccess(acao === 'aprovar'
        ? `Pré-matrícula aprovada. Matrícula #${resultado.matricula_id} criada${resultado.acesso_gratuito_ate ? `; acesso gratuito até ${formatarDia(resultado.acesso_gratuito_ate)}` : ''}.`
        : 'Pré-matrícula reprovada.');
      setRevisao(null);
      await carregar(tenantId);
    } catch (error) {
      setErroModal(error.message);
      showError(error.message);
      // O ajuste pode ter sido gravado mesmo com a aprovação recusada: recarrega a fila
      await carregar(tenantId);
    } finally {
      setSalvando(false);
    }
  };

  const confirmarExclusao = async () => {
    if (!excluir || salvando) return;
    setSalvando(true);
    setErro('');
    try {
      const resultado = modoAcademia
        ? await preMatriculaService.excluirAcademia(excluir.id)
        : await preMatriculaService.excluir(excluir.id, Number(tenantId));
      setExcluir(null);
      showSuccess(resultado?.message || 'Pré-matrícula excluída.');
      await carregar(tenantId);
    } catch (error) {
      setErro(error.message);
      showError(error.message);
      setExcluir(null);
    } finally {
      setSalvando(false);
    }
  };

  const solicitacoes = dados?.solicitacoes || [];
  const contagens = {
    pendente: solicitacoes.filter((item) => item.status === 'pendente').length,
    aprovada: solicitacoes.filter((item) => item.status === 'aprovada').length,
    reprovada: solicitacoes.filter((item) => item.status === 'reprovada').length,
  };
  // Filtros de busca: status + nome/contato + plano + dia + turma (dia e turma valem para o mesmo horário da grade)
  const termo = semAcento(busca).trim();
  const filtrosDeGrade = Boolean(filtroDia || filtroTurma);
  const combinaGrade = (slot) => (!filtroDia || Number(slot.dia_semana) === Number(filtroDia))
    && (!filtroTurma || Number(slot.turma_fixa_id) === Number(filtroTurma));
  const filtradas = solicitacoes.filter((item) => (filtroStatus === 'todas' || item.status === filtroStatus)
    && (!termo || semAcento([item.nome, item.email, item.cpf, item.telefone, item.responsavel_nome].join(' ')).includes(termo))
    && (!filtroPlano || String(item.plano_id) === filtroPlano)
    && (!filtrosDeGrade || item.grade.some(combinaGrade)));
  const opcoesPlano = [...new Map(solicitacoes.map((item) => [String(item.plano_id), rotuloPlano(item)])).entries()]
    .map(([value, label]) => ({ value, label }));
  const opcoesTurma = [...new Map(solicitacoes.flatMap((item) => item.grade).map((slot) => [String(slot.turma_fixa_id), rotuloTurma(slot)])).entries()]
    .map(([value, label]) => ({ value, label })).sort((a, b) => a.label.localeCompare(b.label, 'pt-BR'));
  const filtrosAtivos = Boolean(busca || filtroPlano || filtroDia || filtroTurma);
  const limparFiltros = () => { setBusca(''); setFiltroPlano(''); setFiltroDia(''); setFiltroTurma(''); };
  const descricaoFiltros = [
    `status: ${filtroStatus === 'todas' ? 'todos' : (statusLabel[filtroStatus] || filtroStatus).toLowerCase()}`,
    busca && `busca "${busca}"`,
    filtroPlano && `plano ${opcoesPlano.find((o) => o.value === filtroPlano)?.label || ''}`,
    filtroDia && DIAS_SEMANA[Number(filtroDia) - 1],
    filtroTurma && opcoesTurma.find((o) => o.value === filtroTurma)?.label,
  ].filter(Boolean).join(' · ');
  const gruposTurma = () => agruparPorTurma(filtradas, { dia: filtroDia, turma: filtroTurma });
  const nomeAcademia = academias.find((academia) => String(academia.id) === tenantId)?.nome || 'Academia';
  const pendenteNaModal = revisao?.status === 'pendente';
  const opcoesPlanoModal = (dados?.planos || []).map((plano) => ({
    value: String(plano.id),
    label: `${plano.checkins_semanais}x por semana · ${plano.modalidade_nome}${plano.descricao ? ` · ${plano.descricao}` : ''}`,
  }));

  return (
    <>
      <Panel title="Pré-matrícula de migração" description="O aluno envia plano, dias/horários e dia de vencimento pelo app. O dia de vencimento é obrigatório. A solicitação não reserva vaga. Ao aprovar, a matrícula gratuita é criada com os dados revisados; ao fim do período gratuito, o aluno contrata o plano pago vinculado no app.">
        {erro ? <Callout tone="danger" action={<Button size="sm" onClick={() => carregar()}>Tentar novamente</Button>}>{erro}</Callout> : null}
        <div className="ck-mc-form">
          {!modoAcademia ? <SelectField label="Academia" value={tenantId} options={academias.map((academia) => ({ value: String(academia.id), label: academia.nome }))} onChange={(event) => setTenantId(event.target.value)} /> : null}
          {carregando && !dados ? <p className="ck-mc-hint" role="status">Carregando…</p> : null}
          {!modoAcademia && tenantId ? <section className="ck-card ck-mc-form">
            <div>
              <strong>Configuração da pré-matrícula</strong>
              <p className="ck-mc-hint">
                {dados?.configuracao?.configurada
                  ? `${config.ativa ? 'Ativa' : 'Inativa'} · ${config.obrigatoria ? 'Envio obrigatório' : 'Envio opcional'} · ${publicoLabel[config.publico] || publicoLabel.sem_matricula} · Gratuito ${(contagemLabel[config.contagem_gratuito] || contagemLabel.dia_vencimento).toLowerCase()}`
                  : 'Ainda não configurada para esta academia.'}
              </p>
              {(dados?.configuracao?.pares || []).length ? <ul className="ck-mc-hint">
                {dados.configuracao.pares.map((par) => <li key={par.plano_free_id}>
                  {par.plano_free_nome} → {par.plano_pago_nome} ({par.checkins_semanais}x por semana · {par.modalidade_nome}){par.valido ? '' : ' — vínculo inválido: revise os planos'}
                </li>)}
              </ul> : <p className="ck-mc-hint">Nenhum plano gratuito vinculado a um plano pago.</p>}
              {dados?.configuracao?.configurada && config.ativa && !(dados?.configuracao?.pares || []).some((par) => par.valido)
                ? <Callout tone="danger">Pré-matrícula ativa sem nenhum vínculo válido: os alunos não veem planos no app. Clique em Editar configuração e vincule cada plano gratuito ao plano pago correspondente.</Callout>
                : null}
              {paresInvalidos.length ? <Callout tone="warning">Há vínculos com plano inativo ou alterado. Eles não são oferecidos aos alunos até serem corrigidos.</Callout> : null}
            </div>
            <div className="ck-cad-table__actions">
              <Button variant="primary" icon={dados?.configuracao?.configurada ? 'pencil' : 'plus'} disabled={carregando} onClick={() => {
                if (!dados?.configuracao?.configurada) setConfig({ ativa: true, obrigatoria: false, publico: 'sem_matricula', contagem_gratuito: 'dia_vencimento', pares: [novoPar()] });
                setErroModal('');
                setConfigAberta(true);
              }}>{dados?.configuracao?.configurada ? 'Editar configuração' : 'Montar pré-matrícula'}</Button>
              {dados?.configuracao?.configurada ? <Button variant="danger" disabled={salvando || carregando} onClick={() => setExcluirConfig(true)}>Excluir</Button> : null}
            </div>
          </section> : null}
        </div>
        <div className="ck-mc-form">
          <div>
            <h3>Pedidos de pré-cadastro</h3>
            <p className="ck-mc-hint">{contagens.pendente} aguardando aprovação. As decisões anteriores continuam disponíveis nos filtros.</p>
          </div>
          <SegmentedControl
            label="Filtrar pedidos por status"
            value={filtroStatus}
            onChange={setFiltroStatus}
            options={[
              { value: 'pendente', label: `Aguardando aprovação (${contagens.pendente})` },
              { value: 'aprovada', label: `Aprovados (${contagens.aprovada})` },
              { value: 'reprovada', label: `Reprovados (${contagens.reprovada})` },
              { value: 'todas', label: `Todos (${solicitacoes.length})` },
            ]}
          />
        </div>
        <div className="ck-mc-form" role="search" aria-label="Filtrar pedidos">
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(190px, 1fr))', gap: 16 }}>
            <TextField label="Buscar aluno" icon="search" value={busca} placeholder="Nome, e-mail, telefone ou CPF" onChange={(event) => setBusca(event.target.value)} />
            <SelectField label="Plano" value={filtroPlano} options={[{ value: '', label: 'Todos os planos' }, ...opcoesPlano]} onChange={(event) => setFiltroPlano(event.target.value)} />
            <SelectField label="Dia da semana" value={filtroDia} options={[{ value: '', label: 'Todos os dias' }, ...DIAS_SEMANA.map((nome, i) => ({ value: String(i + 1), label: nome }))]} onChange={(event) => setFiltroDia(event.target.value)} />
            <SelectField label="Turma" value={filtroTurma} options={[{ value: '', label: 'Todas as turmas' }, ...opcoesTurma]} onChange={(event) => setFiltroTurma(event.target.value)} />
          </div>
          <div className="ck-cad-table__actions" style={{ alignItems: 'center', flexWrap: 'wrap' }}>
            <p className="ck-mc-hint" role="status" style={{ margin: 0, flex: 1 }}>
              Mostrando {filtradas.length} de {solicitacoes.length} pedido(s){filtrosAtivos ? ' com os filtros aplicados' : ''}.
            </p>
            {filtrosAtivos ? <Button size="sm" variant="ghost" icon="x" onClick={limparFiltros}>Limpar filtros</Button> : null}
            <Button size="sm" variant="secondary" icon="download" disabled={!filtradas.length} onClick={() => exportarCsvPorTurma(gruposTurma())}>Exportar CSV por turma</Button>
            <Button size="sm" variant="secondary" icon="download" disabled={!filtradas.length} onClick={() => exportarPdfPorTurma(gruposTurma(), { academia: nomeAcademia, filtros: descricaoFiltros })}>Exportar PDF por turma</Button>
          </div>
        </div>
        {!filtradas.length ? <p className="ck-mc-hint">{filtrosAtivos ? 'Nenhum pedido combina com os filtros.' : filtroStatus === 'pendente' ? 'Não há pedidos aguardando aprovação para esta academia.' : 'Nenhum pedido neste filtro.'}</p> : (
          <div className="ck-pc__wrap">
            <table className="ck-pc__table">
              <thead><tr><th>Aluno</th><th>Enviado em</th><th>Plano solicitado</th><th>Grade semanal</th><th>Vencimento</th><th>Status</th><th>Ações</th></tr></thead>
              <tbody>{filtradas.map((item) => (
                <tr key={item.id}>
                  <td>
                    <strong>{item.nome}</strong>{item.dependente ? <> <Badge tone="info">Dependente</Badge></> : null}
                    <span className="ck-cad-table__summary">{item.email}{item.telefone ? ` · ${item.telefone}` : ''}{item.cpf ? ` · CPF ${item.cpf}` : ''}</span>
                    {item.dependente && item.responsavel_nome ? <span className="ck-cad-table__summary">Responsável: {item.responsavel_nome}{item.responsavel_telefone ? ` · ${item.responsavel_telefone}` : ''}{item.responsavel_email ? ` · ${item.responsavel_email}` : ''}</span> : null}
                    {item.enviada_por_nome ? <span className="ck-cad-table__summary">Enviado por {item.enviada_por_nome}</span> : null}
                  </td>
                  <td>{formatarData(item.created_at)}</td>
                  <td><strong>{item.checkins_semanais}x por semana</strong><span className="ck-cad-table__summary">{item.plano_descricao || 'Sem descrição'}</span>{item.plano_destino_nome ? <span className="ck-cad-table__summary">Plano pago: {item.plano_destino_nome}</span> : null}</td>
                  <td>{item.grade.map((slot) => `${dias[slot.dia_semana - 1]} · ${slot.turma_nome} · ${String(slot.horario_inicio || '').slice(0, 5)}`).join('; ')}</td>
                  <td>{item.dia_vencimento ? `Dia ${item.dia_vencimento}` : 'Não informado'}</td>
                  <td>
                    <Badge tone={item.status === 'aprovada' ? 'success' : item.status === 'reprovada' ? 'danger' : 'warning'}>{statusLabel[item.status] || item.status}</Badge>
                    {item.matricula_id ? <span className="ck-cad-table__summary">Matrícula #{item.matricula_id}{item.matricula_status ? ` · ${matriculaStatusLabel[item.matricula_status] || item.matricula_status}` : ''}</span> : null}
                    {item.observacao ? <span className="ck-cad-table__summary">{item.observacao}</span> : null}
                  </td>
                  <td><div className="ck-cad-table__actions">
                    {item.status === 'pendente'
                      ? <Button size="sm" variant="primary" disabled={salvando} onClick={() => abrirRevisao(item)}>Revisar</Button>
                      : <>
                        <Button size="sm" variant="secondary" icon="pencil" disabled={salvando} onClick={() => abrirRevisao(item)}>Editar</Button>
                        <Button size="sm" variant="danger" icon="trash" disabled={salvando} onClick={() => setExcluir(item)}>Excluir</Button>
                      </>}
                  </div></td>
                </tr>
              ))}</tbody>
            </table>
          </div>
        )}
      </Panel>

      <Modal open={configAberta} size="lg" title={dados?.configuracao?.configurada ? 'Editar pré-matrícula' : 'Montar pré-matrícula'} dismissable={!salvando} onClose={() => setConfigAberta(false)}
        description="Defina como o pedido de pré-cadastro será apresentado aos alunos desta academia e quais planos gratuitos levam a quais planos pagos."
        footer={<><Button variant="ghost" disabled={salvando} onClick={() => setConfigAberta(false)}>Cancelar</Button><Button variant="primary" icon="save" loading={salvando} onClick={salvar}>Salvar configuração</Button></>}>
        <div className="ck-mc-form">
          {erroModal ? <Callout tone="danger">{erroModal}</Callout> : null}
          <Switch label="Pré-matrícula ativa" description="Alunos elegíveis poderão enviar o pedido pelo app. Exige ao menos um vínculo válido com turmas em dias suficientes." checked={!!config.ativa} onChange={(value) => setConfig((atual) => ({ ...atual, ativa: value }))} />
          <Switch label="Envio obrigatório" description="Quando ativo, o app exige o envio antes de continuar." checked={!!config.obrigatoria} onChange={(value) => setConfig((atual) => ({ ...atual, obrigatoria: value }))} />
          <SelectField label="Público" hint="A configuração controla quem pode iniciar um novo pedido. Solicitações já enviadas continuam disponíveis para acompanhamento."
            value={config.publico}
            options={[
              { value: 'sem_matricula', label: publicoLabel.sem_matricula },
              { value: 'com_matricula', label: publicoLabel.com_matricula },
              { value: 'ambos', label: publicoLabel.ambos },
            ]}
            onChange={(event) => setConfig((atual) => ({ ...atual, publico: event.target.value }))} />
          <SelectField label="Contagem do período gratuito" hint={contagemHint[config.contagem_gratuito] || contagemHint.dia_vencimento}
            value={config.contagem_gratuito}
            options={[
              { value: 'dia_vencimento', label: contagemLabel.dia_vencimento },
              { value: 'aprovacao', label: contagemLabel.aprovacao },
            ]}
            onChange={(event) => setConfig((atual) => ({ ...atual, contagem_gratuito: event.target.value }))} />
          <div>
            <strong>Planos da migração (gratuito → pago)</strong>
            <p className="ck-mc-hint">O plano pago precisa ter a mesma modalidade e a mesma frequência semanal do gratuito. É ele que o aluno poderá contratar ao fim do período gratuito.</p>
          </div>
          {config.pares.map((par, indice) => {
            const gratuito = catalogo.gratuitos.find((plano) => String(plano.id) === par.plano_free_id);
            const usados = config.pares.filter((_, i) => i !== indice).map((outro) => outro.plano_free_id);
            const pagos = catalogo.pagos.filter((plano) => gratuito
              && plano.modalidade_id === gratuito.modalidade_id && plano.checkins_semanais === gratuito.checkins_semanais);
            return <div key={`par-${indice}`} className="ck-mc-form">
              <SelectField label={`Plano gratuito ${indice + 1}`} value={par.plano_free_id}
                options={[{ value: '', label: 'Selecione o plano gratuito' }, ...catalogo.gratuitos
                  .filter((plano) => !usados.includes(String(plano.id)))
                  .map((plano) => ({ value: String(plano.id), label: `${plano.nome} · ${plano.checkins_semanais}x por semana · ${plano.modalidade_nome}` }))]}
                onChange={(event) => atualizarPar(indice, { plano_free_id: event.target.value, plano_pago_id: '' })} />
              <SelectField label="Plano pago correspondente" value={par.plano_pago_id} disabled={!gratuito}
                hint={gratuito && !pagos.length ? 'Não há plano pago ativo com a mesma modalidade e frequência.' : undefined}
                options={[{ value: '', label: 'Selecione o plano pago' }, ...pagos.map((plano) => ({ value: String(plano.id), label: `${plano.nome} · ${plano.checkins_semanais}x por semana · ${plano.modalidade_nome}` }))]}
                onChange={(event) => atualizarPar(indice, { plano_pago_id: event.target.value })} />
              <div className="ck-cad-table__actions">
                <Button size="sm" variant="ghost" icon="trash" disabled={salvando} onClick={() => setConfig((atual) => ({ ...atual, pares: atual.pares.filter((_, i) => i !== indice) }))}>Remover vínculo</Button>
              </div>
            </div>;
          })}
          {!catalogo.gratuitos.length ? <Callout tone="warning">Esta academia não tem planos gratuitos ativos com aulas semanais. Cadastre-os em Planos.</Callout> : null}
          <div className="ck-cad-table__actions">
            <Button size="sm" variant="secondary" icon="plus" disabled={salvando || config.pares.length >= catalogo.gratuitos.length} onClick={() => setConfig((atual) => ({ ...atual, pares: [...atual.pares, novoPar()] }))}>Adicionar vínculo</Button>
          </div>
        </div>
      </Modal>

      <Modal open={excluirConfig} size="sm" title="Excluir configuração?" dismissable={!salvando} onClose={() => setExcluirConfig(false)}
        description="A pré-matrícula deixará de ser oferecida aos alunos. Os pedidos já recebidos e as matrículas criadas serão preservados."
        footer={<><Button variant="ghost" disabled={salvando} onClick={() => setExcluirConfig(false)}>Cancelar</Button><Button variant="danger" loading={salvando} onClick={removerConfiguracao}>Excluir configuração</Button></>}>
        <p>Deseja excluir a configuração desta academia?</p>
      </Modal>

      <Modal open={!!excluir} size="sm" title="Excluir pré-matrícula?" dismissable={!salvando} onClose={() => setExcluir(null)}
        description={`O pedido de ${excluir?.nome || 'este aluno'} sairá das listas e do status do aluno. A exclusão fica registrada para auditoria e a matrícula vinculada, se houver, é preservada.`}
        footer={<><Button variant="ghost" disabled={salvando} onClick={() => setExcluir(null)}>Cancelar</Button><Button variant="danger" icon="trash" loading={salvando} onClick={confirmarExclusao}>Excluir pedido</Button></>}>
        <p>Esta ação não pode ser desfeita pelo painel.</p>
      </Modal>

      <Modal open={!!revisao} size="lg" title={pendenteNaModal ? 'Revisar pré-matrícula' : 'Editar solicitação'} dismissable={!salvando} onClose={() => setRevisao(null)}
        description={revisao ? `${revisao.nome}${revisao.dependente && revisao.responsavel_nome ? ` (dependente de ${revisao.responsavel_nome})` : ''} · ${revisao.email}${revisao.telefone ? ` · ${revisao.telefone}` : ''} · enviado em ${formatarData(revisao.created_at)}` : ''}
        footer={<>
          <Button variant="ghost" disabled={salvando} onClick={() => setRevisao(null)}>Cancelar</Button>
          <Button variant="secondary" icon="save" disabled={salvando || !formularioValido} onClick={salvarAjuste}>{pendenteNaModal ? 'Salvar ajuste' : 'Salvar alterações'}</Button>
          {pendenteNaModal ? <>
            <Button variant="danger" disabled={salvando} onClick={() => decidir('reprovar')}>Reprovar</Button>
            <Button variant="primary" loading={salvando} disabled={!formularioValido} onClick={() => decidir('aprovar')}>Aprovar e criar matrícula</Button>
          </> : null}
        </>}>
        <div className="ck-mc-form">
          {erroModal ? <Callout tone="danger">{erroModal}</Callout> : null}
          {revisao?.status === 'aprovada' ? <Callout tone="info">Estes são os dados da solicitação. A matrícula já criada não é alterada; mudanças no plano efetivo, na grade em uso ou na cobrança são feitas na gestão de matrículas.</Callout> : null}
          {pendenteNaModal ? <Callout tone="info">Ao aprovar, a matrícula gratuita é criada com os dados abaixo. Se não houver vaga ou houver conflito com outra matrícula, o pedido continua aguardando.</Callout> : null}
          {revisao && !planoEdicao ? <Callout tone="warning">O plano solicitado não está mais disponível para pré-matrícula. Selecione outro plano.</Callout> : null}
          <SelectField label="Plano gratuito" value={planoEdicao ? edicaoPlanoId : ''} options={planoEdicao ? opcoesPlanoModal : [{ value: '', label: 'Selecione o plano' }, ...opcoesPlanoModal]} onChange={(event) => {
            const id = event.target.value;
            const plano = (dados?.planos || []).find((item) => item.id === Number(id));
            setEdicaoPlanoId(id);
            setEdicaoGrade(plano ? Array.from({ length: plano.checkins_semanais }, () => ({ turma_fixa_id: '', dia_semana: 0 })) : []);
          }} />
          {planoEdicao?.plano_pago_nome ? <p className="ck-mc-hint">Plano pago vinculado: {planoEdicao.plano_pago_nome}</p> : null}
          {planoEdicao && planoEdicao.dias_disponiveis < planoEdicao.checkins_semanais
            ? <Callout tone="warning">Há turmas em {planoEdicao.dias_disponiveis} dia(s) diferente(s), mas este plano precisa de {planoEdicao.checkins_semanais}. Não é possível completar a grade com os horários ativos.</Callout>
            : null}
          {planoEdicao ? Array.from({ length: planoEdicao.checkins_semanais }, (_, index) => {
            const selecionadosEmOutrosCampos = edicaoGrade
              .filter((_, outroIndex) => outroIndex !== index)
              .map((slot) => Number(slot.dia_semana))
              .filter(Boolean);
            const opcoes = (dados?.turmas || []).flatMap((turma) => turma.modalidade_id === planoEdicao.modalidade_id
              ? turma.dias_semana.filter((dia) => !selecionadosEmOutrosCampos.includes(dia)
                || (Number(edicaoGrade[index]?.dia_semana) === dia && Number(edicaoGrade[index]?.turma_fixa_id) === turma.id))
                .map((dia) => ({
                  value: `${turma.id}:${dia}`,
                  label: `${dias[dia - 1]} · ${String(turma.horario_inicio || '').slice(0, 5)} · ${turma.nome}`,
                }))
              : []);
            const atual = edicaoGrade[index] || { turma_fixa_id: '', dia_semana: 0 };
            const value = atual.turma_fixa_id && atual.dia_semana ? `${atual.turma_fixa_id}:${atual.dia_semana}` : '';
            return <SelectField key={`grade-${index}`} label={`Dia e turma ${index + 1}`} value={value}
              options={[{ value: '', label: 'Selecione um horário' }, ...opcoes]}
              onChange={(event) => {
                const [turmaId, dia] = event.target.value.split(':');
                setEdicaoGrade((gradeAtual) => gradeAtual.map((slot, slotIndex) => slotIndex === index
                  ? { turma_fixa_id: turmaId || '', dia_semana: Number(dia) || 0 }
                  : slot));
              }} />;
          }) : null}
          <TextField label="Dia de vencimento (obrigatório)" type="number" min="1" max="31" value={edicaoVencimento}
            error={vencimentoValido ? undefined : 'Informe um dia de 1 a 31.'}
            onChange={(event) => setEdicaoVencimento(event.target.value)} placeholder="De 1 a 31" />
          {pendenteNaModal ? <TextAreaField label="Observação para o histórico (opcional)" value={observacao} onChange={(event) => setObservacao(event.target.value)} /> : null}
        </div>
      </Modal>
    </>
  );
}

function mensagem(error, fallback) {
  const data = error?.response?.data || {};
  const detalhes = Object.values(data.errors || {}).flat().join(' ');
  return detalhes || data.message || data.error || error?.message || fallback;
}
