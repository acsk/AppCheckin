import React, { useCallback, useEffect, useState } from 'react';
import { Badge, Button, Callout, Modal, SelectField, Switch, TextAreaField } from '../../components/ck';
import { Panel } from '../../components/ck/pessoas';
import { superAdminService } from '../../services/superAdminService';
import preMatriculaService from '../../services/preMatriculaService';
import { showError, showSuccess } from '../../utils/toast';

const dias = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
const publicoLabel = {
  sem_matricula: 'Sem matrícula ativa',
  com_matricula: 'Com matrícula ativa',
  ambos: 'Com ou sem matrícula ativa',
};
const statusLabel = { pendente: 'Pendente', aprovada: 'Aprovada', reprovada: 'Reprovada' };

export default function PreMatriculaPanel() {
  const [academias, setAcademias] = useState([]);
  const [tenantId, setTenantId] = useState('');
  const [dados, setDados] = useState(null);
  const [config, setConfig] = useState({ ativa: false, obrigatoria: false, publico: 'sem_matricula' });
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
      setConfig(resultado.configuracao);
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
      showSuccess('Configuração da pré-matrícula salva.');
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

  return (
    <>
      <Panel title="Pré-matrícula de migração" description="O aluno envia plano, dias/horários e dia de vencimento pelo app. A solicitação não reserva vaga. A aprovação apenas valida os dados: não cria matrícula nem cobrança; faça a matrícula manualmente após revisar.">
        {erro ? <Callout tone="danger" action={<Button size="sm" onClick={() => carregar()}>Tentar novamente</Button>}>{erro}</Callout> : null}
        <div className="ck-mc-form">
          <SelectField label="Academia" value={tenantId} options={academias.map((academia) => ({ value: String(academia.id), label: academia.nome }))} onChange={(event) => setTenantId(event.target.value)} />
          {carregando && !dados ? <p className="ck-mc-hint" role="status">Carregando…</p> : null}
          {tenantId ? <>
            <Switch label="Pré-matrícula ativa" description="Alunos elegíveis poderão preencher a solicitação no app." checked={!!config.ativa} onChange={(value) => setConfig((atual) => ({ ...atual, ativa: value }))} />
            <Switch label="Envio obrigatório" description="Quando ativo, o app exige que o aluno envie a pré-matrícula para continuar." checked={!!config.obrigatoria} onChange={(value) => setConfig((atual) => ({ ...atual, obrigatoria: value }))} />
            <SelectField label="Público" hint="A opção com matrícula ativa só inclui alunos com uma matrícula de status ativa nesta academia. Para alunos sem matrícula, selecione “Sem matrícula ativa” ou “Com ou sem matrícula ativa”." value={config.publico} options={[
              { value: 'sem_matricula', label: publicoLabel.sem_matricula },
              { value: 'com_matricula', label: publicoLabel.com_matricula },
              { value: 'ambos', label: publicoLabel.ambos },
            ]} onChange={(event) => setConfig((atual) => ({ ...atual, publico: event.target.value }))} />
            <div><Button variant="primary" icon="save" loading={salvando} disabled={carregando} onClick={salvar}>Salvar configuração</Button></div>
          </> : null}
        </div>
        <h3>Solicitações</h3>
        {!dados?.solicitacoes?.length ? <p className="ck-mc-hint">Nenhuma pré-matrícula recebida para esta academia.</p> : (
          <div className="ck-pc__wrap">
            <table className="ck-pc__table">
              <thead><tr><th>Aluno</th><th>Plano solicitado</th><th>Grade semanal</th><th>Vencimento</th><th>Status</th><th>Ações</th></tr></thead>
              <tbody>{dados.solicitacoes.map((item) => (
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
