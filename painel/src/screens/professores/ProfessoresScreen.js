import React, { useCallback, useState } from 'react';
import { useFocusEffect, useRouter } from 'expo-router';
import { professorService } from '../../services/professorService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Modal } from '../../components/ck';
import { ProfessoresLista } from '../../components/ck/professores';
import { titleCase } from '../../components/ck/pessoas';
import { showSuccess, showError } from '../../utils/toast';

const bool = (v) => v === true || v === 1 || v === '1';
const localISO = (d = new Date()) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const DIAS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

// "hoje 19:00", "amanhã 07:00", "qua 18:30"
export const quandoAula = (aula) => {
  if (!aula) return null;
  const hoje = new Date();
  const amanha = new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate() + 1);
  const [y, m, d] = aula.data.split('-').map(Number);
  const dia = aula.data === localISO(hoje) ? 'hoje' : aula.data === localISO(amanha) ? 'amanhã' : DIAS[new Date(y, m - 1, d).getDay()];
  return `${dia} ${aula.horario_inicio}`;
};

// Professor da API → props do ProfessoresLista
const toTeacher = (p) => ({
  id: p.id,
  userId: p.usuario_id,
  name: p.nome || '',
  email: p.email || '',
  phone: p.telefone || '',
  cpf: p.cpf || '',
  photo: p.foto_url || undefined,
  active: bool(p.vinculo_ativo ?? p.ativo),
  modalities: (p.modalidades || []).map((m) => m.nome),
  weeklyClasses: Number(p.aulas_semana || 0),
  classesCount: Number(p.turmas_count || 0),
  nextClass: quandoAula(p.proxima_aula),
  nextClassDate: p.proxima_aula?.data || null,
  crefDue: p.cref_validade ? String(p.cref_validade).slice(0, 10) : null,
});

export default function ProfessoresScreen() {
  const router = useRouter();
  const [teachers, setTeachers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [deleting, setDeleting] = useState(null);
  const [busy, setBusy] = useState(false);

  const carregar = useCallback(async () => {
    try {
      const lista = await professorService.listar(false);
      setTeachers(lista.map(toTeacher));
    } catch (error) {
      showError(error?.message || 'Não foi possível carregar os professores');
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(useCallback(() => { carregar(); }, [carregar]));

  const toggleActive = async (t) => {
    try {
      await professorService.atualizar(t.id, { ativo: !t.active });
      showSuccess(`${titleCase(t.name)} ${t.active ? 'desativado' : 'reativado'}`);
      setTeachers((cur) => cur.map((x) => (x.id === t.id ? { ...x, active: !t.active } : x)));
    } catch (error) {
      showError(error?.message || 'Não foi possível alterar a situação');
    }
  };

  const confirmarExclusao = async () => {
    setBusy(true);
    try {
      await professorService.deletar(deleting.id);
      showSuccess(`${titleCase(deleting.name)} saiu da academia`);
      setTeachers((cur) => cur.filter((x) => x.id !== deleting.id));
      setDeleting(null);
    } catch (error) {
      showError(error?.message || 'Não foi possível excluir o professor');
    } finally {
      setBusy(false);
    }
  };

  return (
    <LayoutBase title="Professores" pageHeader={false}>
      <ProfessoresLista
        teachers={teachers}
        loading={loading}
        today={localISO()}
        onNew={() => router.push('/professores/novo')}
        onEdit={(t) => router.push(`/professores/${t.id}`)}
        onSeeClasses={(t) => router.push(t.nextClassDate ? `/turmas?data=${t.nextClassDate}` : '/turmas')}
        onToggleActive={toggleActive}
        onDelete={(t) => setDeleting(t)}
      />
      <Modal
        open={!!deleting}
        size="sm"
        title="Excluir professor?"
        description={deleting ? `${titleCase(deleting.name)} sai desta academia e some da escolha de professor. O cadastro e o login continuam valendo em outras academias.` : ''}
        onClose={() => !busy && setDeleting(null)}
        footer={(
          <>
            <Button variant="ghost" onClick={() => setDeleting(null)} disabled={busy}>Cancelar</Button>
            <Button variant="danger" icon="trash" loading={busy} onClick={confirmarExclusao}>Excluir</Button>
          </>
        )}
      />
    </LayoutBase>
  );
}
