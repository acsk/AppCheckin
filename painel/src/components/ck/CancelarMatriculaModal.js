import React, { useEffect, useState } from 'react';
import { Button, Callout, Icon, Modal, SelectField, TextAreaField } from './index';
import { matriculaService } from '../../services/matriculaService';
import { obterMensagemErro } from '../../utils/errorHandler';

const moeda = (v) => Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

/**
 * Cancela a matrícula com motivo obrigatório (App\Support\MotivoCancelamento).
 * O motivo alimenta o BI (saídas por motivo) e vira a categoria da oportunidade na Prospecção.
 * Com crédito: a simulação (só plano vigente, proporcional ao uso) aparece antes de confirmar e o admin logado
 * fica registrado como autor do cancelamento e do crédito. O aluno usa o crédito ao contratar o novo plano no app.
 */
export default function CancelarMatriculaModal({ matricula, alunoNome, temParcelasAbertas, onClose, onCancelada }) {
  const [motivos, setMotivos] = useState([]);
  const [motivo, setMotivo] = useState('');
  const [detalhe, setDetalhe] = useState('');
  const [cancelarAbertas, setCancelarAbertas] = useState(true);
  const [simulacao, setSimulacao] = useState(null);
  const [simulando, setSimulando] = useState(false);
  const [gerarCredito, setGerarCredito] = useState(false);
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState('');
  const [tentou, setTentou] = useState(false);

  useEffect(() => {
    if (!matricula) return undefined;
    let ativo = true;
    setMotivo(''); setDetalhe(''); setCancelarAbertas(true); setErro(''); setTentou(false);
    setSimulacao(null); setGerarCredito(false); setSimulando(true);
    matriculaService.motivosCancelamento()
      .then((lista) => { if (ativo) setMotivos(lista); })
      .catch((e) => { if (ativo) setErro(obterMensagemErro(e, 'Não foi possível carregar os motivos.')); });
    matriculaService.simularCancelamento(matricula.id)
      .then((sim) => {
        if (!ativo) return;
        setSimulacao(sim);
        setGerarCredito(Number(sim?.valor_proporcional_credito || 0) > 0);
      })
      .catch((e) => { if (ativo) setErro(obterMensagemErro(e, 'Não foi possível simular o crédito.')); })
      .finally(() => { if (ativo) setSimulando(false); });
    return () => { ativo = false; };
  }, [matricula]);

  if (!matricula) return null;
  const precisaDetalhe = motivo === 'outro' && !detalhe.trim();
  const credito = Number(simulacao?.valor_proporcional_credito || 0);
  const comCredito = gerarCredito && credito > 0;
  const responsavel = simulacao?.responsavel?.nome;

  const confirmar = async () => {
    setTentou(true);
    if (!motivo || precisaDetalhe) return;
    try {
      setEnviando(true);
      setErro('');
      if (comCredito) {
        // Cancela também as parcelas em aberto (regra do cancelamento com crédito)
        await matriculaService.cancelarComCredito(matricula.id, {
          gerar_credito: true,
          motivo_codigo: motivo,
          motivo_cancelamento: detalhe.trim() || undefined,
        });
      } else {
        await matriculaService.cancelar(matricula.id, {
          motivo_codigo: motivo,
          motivo_cancelamento: detalhe.trim() || undefined,
          cancelar_parcelas_abertas: temParcelasAbertas ? cancelarAbertas : false,
        });
      }
      onCancelada();
    } catch (e) {
      setErro(obterMensagemErro(e, 'Não foi possível cancelar a matrícula.'));
    } finally {
      setEnviando(false);
    }
  };

  return (
    <Modal open onClose={onClose} dismissable={!enviando} size="sm"
      leading={<span className="ck-xm__icon" aria-hidden="true"><Icon name="ban" size={20} /></span>}
      title={`Cancelar a matrícula #${matricula.id}?`}
      description={`${alunoNome || 'O aluno'} perde o acesso às aulas. O histórico de pagamentos e check-ins continua.`}
      footer={<>
        <Button variant="ghost" disabled={enviando} onClick={onClose}>Manter matrícula</Button>
        <Button variant="danger" icon="ban" loading={enviando} disabled={simulando} onClick={confirmar}>
          {comCredito ? `Cancelar e gerar ${moeda(credito)}` : 'Cancelar matrícula'}
        </Button>
      </>}>
      <div className="ck-cm">
        {erro ? <Callout tone="danger" title="Não foi possível cancelar">{erro}</Callout> : null}
        <SelectField label="Motivo" value={motivo} placeholder="Escolha o motivo"
          options={motivos.map((m) => ({ value: m.codigo, label: m.rotulo }))}
          onChange={(e) => setMotivo(e.target.value)}
          error={tentou && !motivo ? 'Escolha o motivo do cancelamento.' : undefined}
          hint="Usado nos indicadores de saída e na Prospecção." />
        <TextAreaField label={motivo === 'outro' ? 'Descreva o motivo' : 'Detalhes (opcional)'} value={detalhe}
          onChange={(e) => setDetalhe(e.target.value)} maxLength={500}
          error={tentou && precisaDetalhe ? 'Descreva o motivo.' : undefined} />

        {simulando ? <Callout tone="neutral" title="Simulando o crédito…" /> : null}
        {simulacao && !simulando ? (
          credito > 0 ? (
            <Callout tone="info" title={`Simulação: crédito de ${moeda(credito)}`}>
              {simulacao.motivo_credito}. Consumido: {simulacao.dias_utilizados} de {simulacao.dias_totais} dias
              {Number(simulacao.checkins_direito) > 0 ? ` e ${simulacao.checkins_usados} de ${simulacao.checkins_direito} check-ins` : ''}.
              {' '}O aluno usa o crédito ao contratar o novo plano pelo app (abatido do valor).
            </Callout>
          ) : (
            <Callout tone="neutral" title="Sem crédito a gerar">
              {simulacao.motivo_credito || 'Plano sem período vigente pago a devolver.'}
            </Callout>
          )
        ) : null}
        {credito > 0 ? (
          <label className="ck-cm__check">
            <input type="checkbox" checked={gerarCredito} onChange={(e) => setGerarCredito(e.target.checked)} />
            <span><strong>Gerar crédito de {moeda(credito)}</strong><span>Proporcional ao uso do plano vigente (o maior entre dias e check-ins consumidos).</span></span>
          </label>
        ) : null}
        {responsavel ? (
          <Callout tone="neutral" icon="user" title={`Responsável: ${responsavel}`}>
            Fica registrado como autor do cancelamento{comCredito ? ' e do crédito' : ''}.
          </Callout>
        ) : null}

        {temParcelasAbertas && !comCredito ? (
          <label className="ck-cm__check">
            <input type="checkbox" checked={cancelarAbertas} onChange={(e) => setCancelarAbertas(e.target.checked)} />
            <span><strong>Cancelar as cobranças em aberto</strong><span>Parcelas aguardando ou atrasadas deixam de ser cobradas aqui. Cobranças já geradas no Asaas ou Mercado Pago continuam lá.</span></span>
          </label>
        ) : null}
        {temParcelasAbertas && comCredito ? (
          <Callout tone="warning" title="As cobranças em aberto serão canceladas">
            Cobranças já geradas no Asaas ou Mercado Pago continuam lá.
          </Callout>
        ) : null}
      </div>
    </Modal>
  );
}
