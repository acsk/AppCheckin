import React, { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { ActionMenu, Badge, Button, Callout, PageHeader, SegmentedControl } from '../../components/ck';
import { titleCase } from '../../components/ck/pessoas';
import { WhatsappContato } from '../../components/ck/whatsapp';
import { AgendarModal, AlunoFoto, ScoreBadge, useFluxoContato } from '../../components/ck/prospeccao';
import prospeccaoService from '../../services/prospeccaoService';
import { telefoneDoAluno } from '../../utils/whatsapp';
import { dataBR, erroApi, paraApiDataHora } from '../../utils/prospeccao';
import { showError, showSuccess } from '../../utils/toast';
import { useProspeccaoContexto } from './useProspeccao';

const GRUPOS = [
  ['atrasados', 'Atrasados', 'danger', 'Nada atrasado.'],
  ['hoje', 'Hoje', 'warning', 'Nenhum contato para hoje.'],
  ['proximos', 'Próximos 30 dias', 'info', 'Nenhum retorno agendado.'],
];

export default function ContatosPendentesScreen() {
  const router = useRouter();
  const { ctx } = useProspeccaoContexto();
  const [responsavel, setResponsavel] = useState('meus');
  const [agenda, setAgenda] = useState(null);
  const [erro, setErro] = useState('');
  const [reagendar, setReagendar] = useState(null);

  const carregar = useCallback(async () => {
    setErro('');
    try { setAgenda(await prospeccaoService.agenda(responsavel ? { responsavel } : {})); }
    catch (e) { setErro(erroApi(e, 'Não foi possível carregar a agenda.')); }
  }, [responsavel]);
  useEffect(() => { carregar(); }, [carregar]);

  const fluxo = useFluxoContato({ ctx, registrar: prospeccaoService.registrarInteracao, depois: () => { showSuccess('Contato registrado'); carregar(); } });
  const acao = async (ag, a) => {
    try {
      await prospeccaoService.atualizarAgendamento(ag.id, { acao: a });
      if (a === 'concluir') fluxo.registrarDepoisDoWhatsapp({ id: ag.oportunidade_id, transicoes: [] });
      else showSuccess('Agendamento cancelado');
      carregar();
    } catch (e) { showError(erroApi(e, 'Não foi possível atualizar o agendamento.')); }
  };

  return <LayoutBase title="Contatos pendentes" pageHeader={false}>
    <div className="ck-pr">
      <PageHeader title="Contatos pendentes" description="Retornos agendados com alunos em recuperação. Concluir pede o registro do que aconteceu no contato."
        actions={<SegmentedControl label="Responsável" value={responsavel} onChange={setResponsavel} options={[{ value: 'meus', label: 'Meus' }, { value: '', label: 'Equipe toda' }]} />} />
      {erro ? <Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout> : null}
      {!agenda && !erro ? <p role="status" className="ck-enrollment-state">Carregando agenda…</p> : null}
      {agenda ? <div className="ck-pr-agenda">{GRUPOS.map(([chave, rotulo, tom, vazio]) => <section key={chave} className="ck-card ck-pr-grupo" aria-label={rotulo}>
        <header className="ck-pr-grupo__head"><h2>{rotulo}</h2><Badge tone={agenda[chave].length ? tom : 'neutral'}>{agenda[chave].length}</Badge></header>
        {agenda[chave].length ? <ul className="ck-pr-list">{agenda[chave].map((ag) => {
          const nome = titleCase(ag.aluno_nome);
          return <li key={ag.id}>
            <AlunoFoto nome={ag.aluno_nome} foto={ag.foto_caminho} />
            <span className="ck-pr-list__main">
              <a href={`/prospeccao/recuperacao/${ag.oportunidade_id}`} className="ck-ml__name" onClick={(e) => { e.preventDefault(); router.push(`/prospeccao/recuperacao/${ag.oportunidade_id}`); }}>{nome}</a>
              <small>{chave === 'hoje' ? '' : `${dataBR(ag.agendado_para)} · `}{String(ag.agendado_para).slice(11, 16)} · {ctx?.tipos_contato?.[ag.tipo_contato] || ag.tipo_contato}{responsavel ? '' : ` · ${titleCase(ag.responsavel_nome || '')}`}</small>
              {ag.observacao ? <small className="ck-pr-list__obs">{ag.observacao}</small> : null}
            </span>
            <ScoreBadge score={ag.score} potencial={ag.score >= 80 ? 'alto' : ag.score >= 50 ? 'medio' : 'baixo'} />
            <WhatsappContato variant="icon" telefone={telefoneDoAluno(ag)} nome={nome} bloqueado={ag.nao_contatar ? 'Aluno pediu para não ser contatado' : false}
              onAbrir={() => fluxo.registrarDepoisDoWhatsapp({ id: ag.oportunidade_id, transicoes: [] })} />
            <Button size="sm" onClick={() => acao(ag, 'concluir')}>Concluir</Button>
            <ActionMenu label={`Mais ações para ${nome}`} items={[
              { label: 'Reagendar', icon: 'calendar', onClick: () => setReagendar(ag) },
              { label: 'Abrir ficha', icon: 'arrow-right', onClick: () => router.push(`/prospeccao/recuperacao/${ag.oportunidade_id}`) },
              '-',
              { label: 'Cancelar agendamento', icon: 'x', danger: true, onClick: () => acao(ag, 'cancelar') },
            ]} />
          </li>;
        })}</ul> : <p className="ck-pr-hint ck-pr-pad">{vazio}</p>}
      </section>)}</div> : null}
    </div>
    {fluxo.modais}
    <AgendarModal open={!!reagendar} reagendar ctx={ctx} alvoNome={reagendar?.aluno_nome} onClose={() => setReagendar(null)}
      onSalvar={async (f) => { await prospeccaoService.atualizarAgendamento(reagendar.id, { acao: 'reagendar', agendado_para: paraApiDataHora(f.agendado_para), observacao: f.observacao }); setReagendar(null); showSuccess('Reagendado'); carregar(); }} />
  </LayoutBase>;
}
