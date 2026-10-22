import React, { useId, useState } from 'react';
import { Avatar, Badge, Button, Callout, Icon, Modal, TextField } from './index';
import { maskPhone, titleCase } from './pessoas';
import { confirmaExclusao, resumoExclusao } from '../../utils/excluirMatricula';

const STATUS = { 1: 'ativa', 2: 'vencida', 3: 'cancelada', 4: 'finalizada', 5: 'pendente', 6: 'bloqueada' };
const ROTULOS = { ativa: 'Ativa', vencida: 'Vencida', cancelada: 'Cancelada', finalizada: 'Finalizada', pendente: 'Pendente', bloqueada: 'Bloqueada' };
const TONS = { ativa: 'success', vencida: 'danger', pendente: 'warning', bloqueada: 'danger' };
const dataBR = (valor) => valor ? String(valor).slice(0, 10).split('-').reverse().join('/') : '—';
const moeda = (valor) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(valor || 0));

export default function ExcluirMatriculaModal({ preview, deleting, pacoteId, onClose, onConfirm, onPacotes, onRetry, onVincular, vinculando }) {
  const [digitado, setDigitado] = useState('');
  const confirmId = useId();
  const { row, data, loading, error } = preview;
  const e = data?.matricula || row || {}, aluno = data?.aluno || {}, plano = data?.plano || {};
  const r = resumoExclusao(data || {});
  const status = (e.status_codigo === 'bloqueado' ? 'bloqueada' : e.status_codigo) || STATUS[e.status_id];
  // Com pagamento confirmado a API não exclui: a matrícula é histórico e deve ser vinculada à atual
  const comPagamento = data?.resumo?.pode_excluir === false;
  const sugerida = data?.resumo?.vinculo_sugerido || null;
  const bloqueado = loading || !data || !!error || !!pacoteId || deleting || comPagamento;
  const podeExcluir = !bloqueado && confirmaExclusao(e.id, digitado, r.exigeNumero);
  const confirmar = () => { if (podeExcluir) onConfirm(); };
  const itens = [
    [r.pagamentos, 'pagamento do plano', 'pagamentos do plano', r.valorPlano, 'receipt'],
    [r.pagamentosProvedor, 'pagamento registrado no Mercado Pago', 'pagamentos registrados no Mercado Pago', r.valorProvedor, 'credit-card'],
    [r.assinaturasProvedor, 'assinatura registrada do Mercado Pago', 'assinaturas registradas do Mercado Pago', null, 'repeat'],
  ].filter(([total]) => total > 0);
  const tecnicos = [
    ['Matrícula', e.id != null ? `#${e.id}` : null], ['Aluno', aluno.id != null ? `#${aluno.id}` : null],
    ['Usuário', aluno.usuario_id != null ? `#${aluno.usuario_id}` : null],
    ['Próximo vencimento', e.proxima_data_vencimento ? dataBR(e.proxima_data_vencimento) : null],
    ['Check-ins por semana', plano.checkins_semanais], ['Observações', e.observacoes],
  ].filter(([, valor]) => valor != null && valor !== '');

  return <Modal open={!!row} onClose={onClose} dismissable={!deleting} size="md" dialogClassName="ck-xm" autoFocus={false}
    leading={<span className="ck-xm__icon" aria-hidden="true"><Icon name={comPagamento ? 'alert-triangle' : 'trash'} size={20} /></span>}
    title={comPagamento ? `A matrícula #${e.id} não pode ser excluída` : `Excluir a matrícula #${e.id}?`}
    description={comPagamento ? 'Ela tem pagamento confirmado e faz parte do histórico financeiro da aluna.' : 'Apaga a matrícula e os registros indicados abaixo. Não dá para desfazer.'}
    footer={comPagamento ? <>
      <Button variant="ghost" disabled={vinculando} onClick={onClose}>Fechar</Button>
      {sugerida && onVincular ? <Button variant="primary" icon="check" loading={vinculando} onClick={() => onVincular(e.id, sugerida.id)}>{`Finalizar e vincular à #${sugerida.id}`}</Button> : null}
    </> : <>
      <Button variant="ghost" disabled={deleting} onClick={onClose}>Manter matrícula</Button>
      {!error && !pacoteId ? <Button variant="danger" icon="trash" loading={deleting} disabled={!podeExcluir} onClick={confirmar}>
        {r.excluidos ? `Excluir matrícula e ${r.excluidos} ${r.excluidos === 1 ? 'registro' : 'registros'}` : 'Excluir matrícula'}
      </Button> : null}
    </>}>
    <div className="ck-xm__body">
      {loading ? <p role="status" className="ck-xm__loading">Carregando prévia…</p> : error || pacoteId ? <div role="alert" className="ck-xm__body">
        <Callout tone="danger" title="Não foi possível continuar">{error || 'Esta matrícula faz parte de um pacote. A exclusão deve ser feita pelo contrato do pacote.'}</Callout>
        {pacoteId ? <Button onClick={onPacotes}>Ir para Pacotes (Contrato #{pacoteId})</Button> : <Button icon="refresh-cw" onClick={onRetry}>Tentar novamente</Button>}
      </div> : data ? <>
        <div className="ck-xm__card">
          <div className="ck-xm__who">
            <Avatar name={titleCase(aluno.nome || row?.usuario_nome || 'Aluno')} size="md" decorative />
            <div><strong>{titleCase(aluno.nome || row?.usuario_nome || 'Aluno')}</strong><span>{[aluno.email, aluno.telefone ? maskPhone(aluno.telefone) : null].filter(Boolean).join(', ') || 'Sem contato cadastrado'}</span></div>
            <Badge tone={TONS[status] || 'outline'} dot>{e.status_nome || ROTULOS[status] || '—'}</Badge>
          </div>
          <dl className="ck-xm__facts">
            <div><dt>Plano</dt><dd>{[plano.nome, plano.modalidade?.nome].filter(Boolean).join(', ') || '—'}</dd><dd className="ck-xm__sub">{moeda(e.valor ?? plano.valor)}{plano.duracao_dias ? ` a cada ${plano.duracao_dias} dias` : ''}</dd></div>
            <div><dt>Vigência</dt><dd>{dataBR(e.data_inicio)} a {dataBR(e.data_vencimento)}</dd><dd className="ck-xm__sub">Matriculado em {dataBR(e.data_matricula || e.data_inicio)}</dd></div>
            <div><dt>Cobrança</dt><dd>{{ avulso: 'Avulsa', recorrente: 'Recorrente' }[e.tipo_cobranca] || e.tipo_cobranca || '—'}</dd>{e.dia_vencimento ? <dd className="ck-xm__sub">Vence todo dia {e.dia_vencimento}</dd> : null}</div>
          </dl>
        </div>
        {comPagamento ? <>
          <Callout tone="warning" title="Excluir apagaria o registro do dinheiro recebido">{data.resumo.bloqueio?.message}</Callout>
          {data.resumo.continuada_por ? <Callout tone="success" title={`Já vinculada à matrícula #${data.resumo.continuada_por}`}>Nada a fazer: o ciclo pago fica no histórico e a #{data.resumo.continuada_por} é a continuação dele.</Callout> : null}
          {sugerida ? <Callout tone="info" title={`Próxima matrícula: #${sugerida.id} · ${sugerida.plano_nome}`}>
            {`Desde ${dataBR(sugerida.data_inicio)} (${sugerida.status_nome}). Ao vincular, a #${e.id} vira Finalizada e a #${sugerida.id} passa a ser a continuação dela. Os pagamentos continuam no ciclo em que foram feitos.`}
          </Callout> : data.resumo.continuada_por ? null : <p className="ck-xm__hint"><Icon name="info" size={15} />Não há matrícula posterior desta aluna na mesma modalidade. Ela pode ficar como está: o status já a separa das ativas.</p>}
        </> : null}
        {!comPagamento ? <div className="ck-field">
          <span className="ck-field__label">{itens.length ? 'Também será excluído' : 'Registros ligados'}</span>
          {itens.length ? <ul className="ck-xm__list">{itens.map(([total, singular, plural, valor, icon]) => <li key={plural}>
            <Icon name={icon} size={16} /><span><b>{total}</b> {total === 1 ? singular : plural}</span>{valor > 0 ? <em>{moeda(valor)}</em> : null}
          </li>)}</ul> : <p className="ck-xm__none"><Icon name="check-circle" size={16} />Nenhum pagamento ou assinatura do provedor será apagado.</p>}
        </div> : null}
        {!comPagamento && r.assinaturas > 0 ? <Callout tone="info" title={`${r.assinaturas} ${r.assinaturas === 1 ? 'assinatura será desvinculada' : 'assinaturas serão desvinculadas'}`}>As assinaturas internas permanecem no Check-in, sem vínculo com esta matrícula.</Callout> : null}
        {!comPagamento && (r.pagamentosProvedor > 0 || r.assinaturasProvedor > 0) ? <Callout tone="warning" title="Nada muda no Mercado Pago">O dinheiro recebido não é estornado e as assinaturas no provedor não são canceladas. Apenas os registros locais são apagados.</Callout> : null}
        {!comPagamento && (status === 'cancelada' || status === 'finalizada') ? <p className="ck-xm__hint"><Icon name="info" size={15} />A matrícula já está {ROTULOS[status].toLowerCase()}. Exclua só se ela foi criada por engano.</p> : null}
        {!comPagamento && r.exigeNumero ? <TextField id={confirmId} label={`Para confirmar, digite o número da matrícula: ${e.id}`} inputMode="numeric" autoComplete="off" value={digitado} placeholder={String(e.id)} disabled={deleting} onChange={(event) => setDigitado(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') { event.preventDefault(); confirmar(); } }} /> : null}
        <details className="ck-xm__tech"><summary tabIndex={0}>Dados técnicos</summary><dl>{tecnicos.map(([label, valor]) => <div key={label}><dt>{label}</dt><dd>{valor}</dd></div>)}</dl></details>
      </> : null}
    </div>
  </Modal>;
}
