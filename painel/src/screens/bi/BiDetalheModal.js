import React, { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import { Avatar, Button, Callout, Modal } from '../../components/ck';
import { titleCase } from '../../components/ck/pessoas';
import { fdate, money } from '../../components/ck/financeiro';
import { buscarBiDetalhe } from '../../services/biService';

const num = (v) => new Intl.NumberFormat('pt-BR').format(v || 0);

const TITULOS = {
  alunos_ativos: ['Alunos ativos', 'Com matrícula vigente no último dia do período.'],
  novos_alunos: ['Novos alunos', 'Primeira matrícula na academia dentro do período.'],
  saidas: ['Saídas', 'Cancelaram no período e não começaram outro plano em até 7 dias.'],
  receita_recebida: ['Receita recebida', 'Parcelas pagas no período, pela data do pagamento.'],
  valor_vencido: ['Valor vencido', 'Parcelas do período que já venceram e não foram pagas.'],
};

/** Lista dos registros que compõem um indicador da Visão geral. `indicador` null = fechado. */
export default function BiDetalheModal({ indicador, periodo, onClose }) {
  const router = useRouter();
  const [dados, setDados] = useState(null);
  const [erro, setErro] = useState('');
  const [reload, setReload] = useState(0);

  useEffect(() => {
    if (!indicador) return undefined;
    let ativo = true;
    setDados(null);
    setErro('');
    buscarBiDetalhe(indicador, periodo)
      .then((d) => { if (ativo) setDados(d || { itens: [] }); })
      .catch((e) => { if (ativo) setErro(e?.mensagemLimpa || e?.response?.data?.error || 'Não foi possível carregar a lista.'); });
    return () => { ativo = false; };
  }, [indicador, periodo, reload]);

  if (!indicador) return null;
  const [titulo, descricao] = TITULOS[indicador] || [indicador, ''];
  const itens = dados?.itens || [];
  const resumo = dados
    ? `${num(dados.quantidade)} ${dados.valor_total != null ? (dados.quantidade === 1 ? 'parcela' : 'parcelas') : (dados.quantidade === 1 ? 'aluno' : 'alunos')}`
      + (dados.valor_total != null ? `, ${money(dados.valor_total)}` : '')
      + ` · ${fdate(dados.periodo.inicio)} a ${fdate(dados.periodo.fim)}`
    : 'Carregando…';

  const abrir = (matriculaId) => {
    onClose();
    router.push(`/matriculas/detalhe?id=${matriculaId}`);
  };

  return (
    <Modal open onClose={onClose} size="md" title={titulo} description={`${descricao} ${resumo}`}
      footer={<Button onClick={onClose}>Fechar</Button>}>
      {erro ? (
        <Callout tone="warning" title="A lista não carregou" action={<Button size="sm" icon="refresh-cw" onClick={() => setReload((x) => x + 1)}>Tentar de novo</Button>}>{erro}</Callout>
      ) : !dados ? (
        <p className="ck-fin-empty" role="status">Carregando…</p>
      ) : !itens.length ? (
        <p className="ck-fin-empty">Nenhum registro no período.</p>
      ) : (
        <>
          {dados.truncado ? <Callout tone="info" title={`Mostrando os primeiros ${num(itens.length)}`}>O total acima considera todos os registros do período.</Callout> : null}
          <ul className="ck-db__list">
            {itens.map((x) => (
              <li key={x.parcela_id || `${x.aluno_id}-${x.matricula_id}`}>
                <Avatar name={titleCase(x.aluno_nome)} size="sm" decorative />
                <button type="button" className="ck-db__who ck-db__link" onClick={() => abrir(x.matricula_id)} title="Abrir a matrícula">
                  <strong>{titleCase(x.aluno_nome) || 'Aluno não identificado'}</strong><span>{x.plano_nome || 'Sem plano'}</span>
                </button>
                <span className="ck-db__when">
                  <strong>{x.valor != null ? money(x.valor) : fdate(x.data)}</strong>
                  <span>{x.valor != null ? `${x.detalhe} ${fdate(x.data)}` : x.detalhe}</span>
                </span>
              </li>
            ))}
          </ul>
        </>
      )}
    </Modal>
  );
}
