import React, { useEffect, useState } from 'react';
import { Button, Callout, Icon, Modal, SelectField, TextAreaField } from './index';
import { matriculaService } from '../../services/matriculaService';
import { obterMensagemErro } from '../../utils/errorHandler';

/**
 * Cancela a matrícula com motivo obrigatório (App\Support\MotivoCancelamento).
 * O motivo alimenta o BI (saídas por motivo) e vira a categoria da oportunidade na Prospecção.
 */
export default function CancelarMatriculaModal({ matricula, alunoNome, temParcelasAbertas, onClose, onCancelada }) {
  const [motivos, setMotivos] = useState([]);
  const [motivo, setMotivo] = useState('');
  const [detalhe, setDetalhe] = useState('');
  const [cancelarAbertas, setCancelarAbertas] = useState(true);
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState('');
  const [tentou, setTentou] = useState(false);

  useEffect(() => {
    if (!matricula) return undefined;
    let ativo = true;
    setMotivo(''); setDetalhe(''); setCancelarAbertas(true); setErro(''); setTentou(false);
    matriculaService.motivosCancelamento()
      .then((lista) => { if (ativo) setMotivos(lista); })
      .catch((e) => { if (ativo) setErro(obterMensagemErro(e, 'Não foi possível carregar os motivos.')); });
    return () => { ativo = false; };
  }, [matricula]);

  if (!matricula) return null;
  const precisaDetalhe = motivo === 'outro' && !detalhe.trim();

  const confirmar = async () => {
    setTentou(true);
    if (!motivo || precisaDetalhe) return;
    try {
      setEnviando(true);
      setErro('');
      await matriculaService.cancelar(matricula.id, {
        motivo_codigo: motivo,
        motivo_cancelamento: detalhe.trim() || undefined,
        cancelar_parcelas_abertas: temParcelasAbertas ? cancelarAbertas : false,
      });
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
        <Button variant="danger" icon="ban" loading={enviando} onClick={confirmar}>Cancelar matrícula</Button>
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
        {temParcelasAbertas ? (
          <label className="ck-cm__check">
            <input type="checkbox" checked={cancelarAbertas} onChange={(e) => setCancelarAbertas(e.target.checked)} />
            <span><strong>Cancelar as cobranças em aberto</strong><span>Parcelas aguardando ou atrasadas deixam de ser cobradas aqui. Cobranças já geradas no Asaas ou Mercado Pago continuam lá.</span></span>
          </label>
        ) : null}
      </div>
    </Modal>
  );
}
