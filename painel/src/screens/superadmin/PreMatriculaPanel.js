import React, { useCallback, useEffect, useState } from 'react';
import { Badge, Button, Callout, Modal, SegmentedControl, SelectField, Switch, TextAreaField } from '../../components/ck';
import { Panel } from '../../components/ck/pessoas';
import { superAdminService } from '../../services/superAdminService';
import preMatriculaService from '../../services/preMatriculaService';
import { showError, showSuccess } from '../../utils/toast';

const dias = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
const statusLabel = { pendente: 'Pendente', aprovada: 'Aprovada', reprovada: 'Reprovada' };

export default function PreMatriculaPanel() {
  const [academias, setAcademias] = useState([]);
  const [tenantId, setTenantId] = useState('');
  const [dados, setDados] = useState(null);
  const [config, setConfig] = useState({ ativa: false, obrigatoria: false });
  const [configAberta, setConfigAberta] = useState(false);
  const [excluirConfig, setExcluirConfig] = useState(false);
  const [filtroStatus, setFiltroStatus] = useState('pendente');
  const [decisao, setDecisao] = useState(null);
  const [observacao, setObservacao] = useState('');
  const [erro, setErro] = useState('');
  const [carregando, setCarregando] = useState(true);
  const [salvando, setSalvando] = useState(false);

  const carregar = useCallback(async (id = tenantId) => {
    if (!id) return;
    setCarregando(true);
    setErro('');
    try {
      const resultado = await preMatriculaService.listar(id);
      setDados(resultado);
      setConfig({
        ativa: !!resultado.configuracao.ativa,
        obrigatoria: !!resultado.configuracao.obrigatoria,
      });
    } catch (error) {
      setErro(error.message);
    } finally {
      setCarregando(false);
    }
  }, [tenantId]);

  useEffect(() => {
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
  }, []);

  useEffect(() => { if (tenantId) carregar(tenantId); }, [tenantId, carregar]);

  const salvar = async () => {
    if (!tenantId || salvando) return;
    setSalvando(true);
    setErro('');
    try {
      await preMatriculaService.salvarConfiguracao({
        tenant_id: Number(tenantId),
        ...config,
      });
      setConfigAberta(false);
      showSuccess('Configuração da pré-matrícula salva.');
      await carregar(tenantId);
    } catch (error) {
      setErro(error.message);
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

  const confirmarDecisao = async () => {
    if (!decisao || salvando) return;
    setSalvando(true);
    setErro('');
    try {
      await preMatriculaService.avaliar(decisao.id, Number(tenantId), decisao.acao, observacao);
      showSuccess(decisao.acao === 'aprovar' ? 'Pré-matrícula aprovada.' : 'Pré-matrícula reprovada.');
      setDecisao(null);
      setObservacao('');
      await carregar(tenantId);
    } catch (error) {
      setErro(error.message);
      showError(error.message);
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
  const filtradas = filtroStatus === 'todas' ? solicitacoes : solicitacoes.filter((item) => item.status === filtroStatus);

  return (
    <>
      <Panel title="Pré-matrícula de migração" description="O aluno envia plano, dias/horários e dia de vencimento pelo app. A solicitação não reserva vaga. A aprovação apenas valida os dados: não cria matrícula nem cobrança; faça a matrícula manualmente após revisar.">
        {erro ? <Callout tone="danger" action={<Button size="sm" onClick={() => carregar()}>Tentar novamente</Button>}>{erro}</Callout> : null}
        <div className="ck-mc-form">
          <SelectField label="Academia" value={tenantId} options={academias.map((academia) => ({ value: String(academia.id), label: academia.nome }))} onChange={(event) => setTenantId(event.target.value)} />
          {carregando && !dados ? <p className="ck-mc-hint" role="status">Carregando…</p> : null}
          {tenantId ? <section className="ck-card ck-mc-form">
            <div>
              <strong>Configuração da pré-matrícula</strong>
              <p className="ck-mc-hint">
                {dados?.configuracao?.configurada
                  ? `${config.ativa ? 'Ativa' : 'Inativa'} · ${config.obrigatoria ? 'Envio obrigatório' : 'Envio opcional'} · disponível para alunos vinculados à academia`
                  : 'Ainda não configurada para esta academia.'}
              </p>
            </div>
            <div className="ck-cad-table__actions">
              <Button variant="primary" icon={dados?.configuracao?.configurada ? 'pencil' : 'plus'} disabled={carregando} onClick={() => {
                setConfig(dados?.configuracao?.configurada ? dados.configuracao : { ativa: true, obrigatoria: false });
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
              { value: 'aprovada', label: `Aprovadas (${contagens.aprovada})` },
              { value: 'reprovada', label: `Reprovadas (${contagens.reprovada})` },
              { value: 'todas', label: `Todas (${solicitacoes.length})` },
            ]}
          />
        </div>
        {!filtradas.length ? <p className="ck-mc-hint">{filtroStatus === 'pendente' ? 'Não há pedidos aguardando aprovação para esta academia.' : 'Nenhum pedido neste filtro.'}</p> : (
          <div className="ck-pc__wrap">
            <table className="ck-pc__table">
              <thead><tr><th>Aluno</th><th>Plano solicitado</th><th>Grade semanal</th><th>Vencimento</th><th>Status</th><th>Ações</th></tr></thead>
              <tbody>{filtradas.map((item) => (
                <tr key={item.id}>
                  <td><strong>{item.nome}</strong><span className="ck-cad-table__summary">{item.email}{item.cpf ? ` · CPF ${item.cpf}` : ''}</span></td>
                  <td><strong>{item.checkins_semanais}x por semana</strong><span className="ck-cad-table__summary">{item.plano_descricao || 'Sem descrição'}</span></td>
                  <td>{item.grade.map((slot) => `${dias[slot.dia_semana - 1]} · ${slot.turma_nome} · ${String(slot.horario_inicio || '').slice(0, 5)}`).join('; ')}</td>
                  <td>{item.dia_vencimento ? `Dia ${item.dia_vencimento}` : 'Não informado'}</td>
                  <td><Badge tone={item.status === 'aprovada' ? 'success' : item.status === 'reprovada' ? 'danger' : 'warning'}>{statusLabel[item.status] || item.status}</Badge>{item.observacao ? <span className="ck-cad-table__summary">{item.observacao}</span> : null}</td>
                  <td>{item.status === 'pendente' ? <div className="ck-cad-table__actions">
                    <Button size="sm" variant="primary" disabled={salvando} onClick={() => { setObservacao(''); setDecisao({ id: item.id, acao: 'aprovar', nome: item.nome }); }}>Aprovar</Button>
                    <Button size="sm" variant="danger" disabled={salvando} onClick={() => { setObservacao(''); setDecisao({ id: item.id, acao: 'reprovar', nome: item.nome }); }}>Reprovar</Button>
                  </div> : null}</td>
                </tr>
              ))}</tbody>
            </table>
          </div>
        )}
      </Panel>

      <Modal open={configAberta} size="md" title={dados?.configuracao?.configurada ? 'Editar pré-matrícula' : 'Montar pré-matrícula'} dismissable={!salvando} onClose={() => setConfigAberta(false)}
        description="Defina como o pedido de pré-cadastro será apresentado aos alunos desta academia."
        footer={<><Button variant="ghost" disabled={salvando} onClick={() => setConfigAberta(false)}>Cancelar</Button><Button variant="primary" icon="save" loading={salvando} onClick={salvar}>Salvar configuração</Button></>}>
        <div className="ck-mc-form">
          <Switch label="Pré-matrícula ativa" description="Alunos elegíveis poderão enviar o pedido pelo app." checked={!!config.ativa} onChange={(value) => setConfig((atual) => ({ ...atual, ativa: value }))} />
          <Switch label="Envio obrigatório" description="Quando ativo, o app exige o envio antes de continuar." checked={!!config.obrigatoria} onChange={(value) => setConfig((atual) => ({ ...atual, obrigatoria: value }))} />
          <p className="ck-mc-hint">O pedido aparecerá para os alunos vinculados a esta academia, tenham ou não matrícula ativa.</p>
        </div>
      </Modal>

      <Modal open={excluirConfig} size="sm" title="Excluir configuração?" dismissable={!salvando} onClose={() => setExcluirConfig(false)}
        description="A pré-matrícula deixará de ser oferecida aos alunos. Os pedidos já recebidos serão preservados para consulta e aprovação."
        footer={<><Button variant="ghost" disabled={salvando} onClick={() => setExcluirConfig(false)}>Cancelar</Button><Button variant="danger" loading={salvando} onClick={removerConfiguracao}>Excluir configuração</Button></>}>
        <p>Deseja excluir a configuração desta academia?</p>
      </Modal>

      <Modal open={!!decisao} size="sm" title={decisao?.acao === 'aprovar' ? 'Aprovar pré-matrícula?' : 'Reprovar pré-matrícula?'} dismissable={!salvando} onClose={() => setDecisao(null)}
        description={`${decisao?.nome || ''}. Esta decisão não cria uma matrícula nem gera cobrança.`}
        footer={<><Button variant="ghost" disabled={salvando} onClick={() => setDecisao(null)}>Cancelar</Button><Button variant={decisao?.acao === 'aprovar' ? 'primary' : 'danger'} loading={salvando} onClick={confirmarDecisao}>{decisao?.acao === 'aprovar' ? 'Aprovar' : 'Reprovar'}</Button></>}>
        <TextAreaField label="Observação para o histórico" value={observacao} onChange={(event) => setObservacao(event.target.value)} />
      </Modal>
    </>
  );
}

function mensagem(error, fallback) {
  const data = error?.response?.data || {};
  const detalhes = Object.values(data.errors || {}).flat().join(' ');
  return detalhes || data.message || data.error || error?.message || fallback;
}
