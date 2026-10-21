import React, { useEffect, useState } from 'react';
import { useRouter, useLocalSearchParams } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { AulaEditor } from '../../components/ck/aulas';
import { Button, Callout } from '../../components/ck';
import { turmaService } from '../../services/turmaService';
import { aulaError, localISO } from '../../utils/aulas';

export default function FormTurmaScreen({ turmaId }) {
  const router = useRouter();
  const params = useLocalSearchParams();
  const [context, setContext] = useState(null);
  const [error, setError] = useState('');
  const [retry, setRetry] = useState(0);
  useEffect(() => {
    let active = true;
    setContext(null); setError('');
    const date = /^\d{4}-\d{2}-\d{2}$/.test(params.data || '') ? params.data : localISO();
    const load = async () => {
      if (turmaId) {
        const aula = await turmaService.buscarPorId(turmaId);
        return { date: aula.dia_data?.slice(0, 10) || aula.data?.slice(0, 10) || date, diaId: aula.dia_id };
      }
      const response = await turmaService.listar(date);
      if (!response.dia?.id) throw new Error('Cadastre o dia selecionado antes de criar uma aula.');
      return { date, diaId: response.dia.id };
    };
    load().then(data => { if (active) setContext(data); })
      .catch(err => { console.error('Erro ao abrir formulário de aula:', err); if (active) setError(aulaError(err, 'Não foi possível carregar a aula.')); });
    return () => { active = false; };
  }, [turmaId, params.data, retry]);
  const back = () => router.replace(context ? `/turmas?data=${context.date}` : '/turmas');
  return <LayoutBase title={turmaId ? 'Editar aula' : 'Nova aula'} pageHeader={false}>
    <Button icon="arrow-left" onClick={back}>Voltar à agenda</Button>
    {error ? <Callout tone="danger" title="Não foi possível abrir" action={<Button onClick={() => setRetry(n => n + 1)}>Tentar novamente</Button>}>{error}</Callout> : null}
    {!context && !error ? <p role="status">Carregando aula…</p> : null}
    {context ? <AulaEditor id={turmaId} date={context.date} diaId={context.diaId} onCancel={back} onSaved={back} /> : null}
  </LayoutBase>;
}
