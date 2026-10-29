import React, { useEffect, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import alunoService from '../../services/alunoService';
import { authService } from '../../services/authService';
import { creditoService } from '../../services/creditoService';
import LayoutBase from '../../components/LayoutBase';
import { Button, IconButton, Modal, TextAreaField, TextField } from '../../components/ck';
import { MOBILE_WEB_URL } from '../../config/api';
import { AlunoDetalhe, maskPhone, titleCase } from '../../components/ck/pessoas';
import { showSuccess, showError } from '../../utils/toast';

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const MONTHS = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const bool = (v) => v === true || v === 1 || v === '1';
const isoDate = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const isoDateTime = (v) => (v ? String(v).replace(' ', 'T').slice(0, 16) : null);
const maskCpf = (v) => {
  const d = String(v || '').replace(/\D/g, '');
  return d.length === 11 ? d.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4') : (v || '');
};
const HISTORY_STATUS = { ativa: 'ativa', pendente: 'pendente', vencida: 'vencida', cancelada: 'cancelada', finalizada: 'finalizada', bloqueada: 'bloqueada' };

const toStudent = (a) => ({
  id: a.id,
  userId: a.usuario_id,
  name: a.nome,
  email: a.email,
  phone: a.telefone ? maskPhone(a.telefone) : '',
  cpf: a.cpf ? maskCpf(a.cpf) : '',
  birthDate: a.data_nascimento ? String(a.data_nascimento).slice(0, 10) : null,
  gender: a.sexo || '',
  status: bool(a.ativo) ? 'ativo' : 'inativo',
  avatar: a.foto_url || undefined,
  address: { cep: a.cep, street: a.logradouro, number: a.numero, complement: a.complemento, district: a.bairro, city: a.cidade, uf: a.estado },
  createdAt: isoDateTime(a.created_at),
  updatedAt: isoDateTime(a.updated_at),
});

const toEnrollment = (a) => (a.plano ? {
  id: a.matricula_id,
  plan: a.plano.nome,
  modality: a.plano.modalidade_nome || '',
  modalityIcon: a.plano.modalidade_icone || undefined,
  amount: Number(a.matricula_valor ?? a.plano.valor) || 0,
  accessUntil: a.acesso_ate ? String(a.acesso_ate).slice(0, 10) : null,
  payment: a.pagamento_ativo === true ? 'em_dia' : 'pendente',
} : null);

const toHistory = (m) => ({
  id: m.id,
  plan: m.plano_nome,
  modality: m.modalidade_nome || 'Plano',
  modalityIcon: m.modalidade_icone || undefined,
  start: m.data_inicio ? String(m.data_inicio).slice(0, 10) : null,
  end: (m.data_fim || m.proxima_data_vencimento) ? String(m.data_fim || m.proxima_data_vencimento).slice(0, 10) : null,
  amount: Number(m.valor ?? m.plano_valor) || 0,
  status: HISTORY_STATUS[m.status_codigo] || HISTORY_STATUS[m.status] || 'finalizada',
});

export default function DetalheAlunoScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const alunoId = id ? parseInt(id, 10) : null;
  const today = isoDate(new Date());

  const [aluno, setAluno] = useState(null);
  const [tab, setTab] = useState('geral');
  const [historico, setHistorico] = useState(null);
  const [creditos, setCreditos] = useState(null);
  const [saldo, setSaldo] = useState(0);
  const [summary, setSummary] = useState(null);
  const [month, setMonth] = useState(() => { const d = new Date(); return { year: d.getFullYear(), month: d.getMonth() + 1 }; });
  const [monthCache, setMonthCache] = useState({});
  const [confirmToggle, setConfirmToggle] = useState(false);
  const [toggling, setToggling] = useState(false);
  const [creditModal, setCreditModal] = useState(false);
  const [creditForm, setCreditForm] = useState({ valor: '', motivo: '' });
  const [savingCredit, setSavingCredit] = useState(false);
  const [cancelCredit, setCancelCredit] = useState(null);
  const [cancelingCredit, setCancelingCredit] = useState(false);
  const [podeVerApp, setPodeVerApp] = useState(false);
  const [verApp, setVerApp] = useState(false);
  const [motivoApp, setMotivoApp] = useState('');
  const [abrindoApp, setAbrindoApp] = useState(false);

  // Sessão do aluno vai no fragmento (#d=): não chega ao servidor do app nem aos logs
  const abrirApp = async () => {
    // Abre a aba no clique (antes do await) para o navegador não bloquear o pop-up
    const aba = window.open('about:blank', '_blank');
    setAbrindoApp(true);
    try {
      const sessao = await alunoService.acessarApp(alunoId, motivoApp.trim());
      const d = btoa(unescape(encodeURIComponent(JSON.stringify(sessao)))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
      const url = `${MOBILE_WEB_URL}/acesso-aluno#d=${d}`;
      if (aba) { aba.opener = null; aba.location.href = url; } else window.open(url, '_blank', 'noopener');
      setVerApp(false);
    } catch (error) {
      aba?.close();
      showError(error?.message || error?.error || 'Não foi possível abrir o app do aluno');
    } finally {
      setAbrindoApp(false);
    }
  };

  const loadAluno = async () => {
    try {
      const response = await alunoService.buscar(alunoId);
      setAluno(response.aluno || response);
    } catch (error) {
      showError(error?.message || 'Não foi possível carregar os dados do aluno');
      router.back();
    }
  };

  const loadHistorico = async () => {
    try {
      const response = await alunoService.historico(alunoId);
      setHistorico(response.historico || []);
    } catch {
      setHistorico([]);
      showError('Não foi possível carregar o histórico de planos');
    }
  };

  const loadCreditos = async () => {
    try {
      const [lista, saldoResp] = await Promise.all([creditoService.listar(alunoId), creditoService.consultarSaldo(alunoId)]);
      setCreditos(Array.isArray(lista) ? lista : []);
      setSaldo(Number(saldoResp?.saldo_total) || 0);
    } catch {
      setCreditos([]);
      setSaldo(0);
    }
  };

  const loadMonth = async ({ year, month: m }) => {
    const key = `${year}-${m}`;
    if (monthCache[key] !== undefined) return;
    try {
      const response = await alunoService.checkins(alunoId, { mes: m, ano: year });
      setMonthCache((prev) => ({ ...prev, [key]: response.checkins || [] }));
    } catch {
      setMonthCache((prev) => ({ ...prev, [key]: [] }));
    }
  };

  useEffect(() => {
    (async () => {
      try {
        const user = await authService.getCurrentUser();
        // Permissão (não papel): ex. Financeiro vê alunos. Super admin sempre.
        if (!user || (user.papel_id !== 4 && !(await authService.temPermissao('alunos.ver')))) {
          showError('Você não tem acesso a esta tela');
          router.replace('/');
          return;
        }
        // Admin da academia, inclusive o super admin no "acessar como academia" (a API confere)
        setPodeVerApp(user.papel_id === 3);
      } catch {
        router.replace('/');
        return;
      }
      if (!alunoId) return;
      loadAluno();
      // Contagens das abas e frequência do mês aparecem já na visão geral
      loadHistorico();
      alunoService.checkins(alunoId).then(setSummary).catch(() => setSummary(null));
      loadMonth(month);
    })();
  }, [alunoId]);

  useEffect(() => {
    if (tab === 'creditos' && creditos === null) loadCreditos();
  }, [tab]);

  useEffect(() => {
    if (alunoId) loadMonth(month);
  }, [month]);

  const shiftMonth = (delta) => {
    setMonth(({ year, month: m }) => {
      const d = new Date(year, m - 1 + delta, 1);
      return { year: d.getFullYear(), month: d.getMonth() + 1 };
    });
  };

  const toggleActive = async () => {
    const ativar = !bool(aluno.ativo);
    setToggling(true);
    try {
      await alunoService.toggleStatus(alunoId, ativar);
      setAluno((prev) => ({ ...prev, ativo: ativar ? 1 : 0 }));
      showSuccess(ativar ? 'Aluno reativado' : 'Aluno desativado');
      setConfirmToggle(false);
    } catch (error) {
      showError(error?.message || error?.error || 'Não foi possível alterar a situação do aluno');
    } finally {
      setToggling(false);
    }
  };

  const addCredit = async () => {
    const valor = parseFloat(String(creditForm.valor).replace(/\./g, '').replace(',', '.'));
    if (!valor || valor <= 0) {
      showError('Informe um valor maior que zero');
      return;
    }
    setSavingCredit(true);
    try {
      await creditoService.criar(alunoId, { valor, motivo: creditForm.motivo.trim() || 'Crédito manual' });
      showSuccess('Crédito lançado');
      setCreditModal(false);
      setCreditForm({ valor: '', motivo: '' });
      await loadCreditos();
    } catch (error) {
      showError(error?.message || 'Não foi possível lançar o crédito');
    } finally {
      setSavingCredit(false);
    }
  };

  const doCancelCredit = async () => {
    setCancelingCredit(true);
    try {
      await creditoService.cancelar(cancelCredit.id);
      showSuccess('Crédito cancelado');
      setCancelCredit(null);
      await loadCreditos();
    } catch (error) {
      showError(error?.message || 'Não foi possível cancelar o crédito');
    } finally {
      setCancelingCredit(false);
    }
  };

  if (!aluno) {
    return (
      <LayoutBase title="Aluno" pageHeader={false}>
        <p className="ck-al-stamp" role="status">Carregando aluno…</p>
      </LayoutBase>
    );
  }

  const student = toStudent(aluno);
  const name = titleCase(student.name);
  const monthKey = `${month.year}-${month.month}`;
  const monthList = monthCache[monthKey];
  const now = new Date();
  const isCurrentMonth = month.year === now.getFullYear() && month.month === now.getMonth() + 1;
  const thisMonth = monthCache[`${now.getFullYear()}-${now.getMonth() + 1}`];

  const checkins = {
    total: summary?.total_geral ?? Number(aluno.total_checkins) ?? 0,
    month: thisMonth ? thisMonth.length : undefined,
    last: isoDateTime(aluno.ultimo_checkin),
    recent: (monthList || []).slice().reverse().map((c) => ({
      at: c.data_aula && c.horario_inicio ? `${String(c.data_aula).slice(0, 10)}T${String(c.horario_inicio).slice(0, 5)}` : isoDateTime(c.data_checkin || c.created_at),
      modality: [c.modalidade, c.turma_nome].filter(Boolean).join(', '),
      by: bool(c.registrado_por_admin) ? 'recepcao' : 'app',
    })),
  };

  const credits = {
    balance: saldo,
    unit: 'dinheiro',
    entries: (creditos || []).map((c) => ({
      desc: [c.motivo || 'Crédito', c.status && c.status !== 'ativo' ? c.status : null, Number(c.valor_utilizado) > 0 ? `usado ${BRL.format(Number(c.valor_utilizado))}` : null].filter(Boolean).join(', '),
      date: c.created_at ? String(c.created_at).slice(0, 10) : null,
      amount: Number(c.valor) || 0,
      onCancel: c.status === 'ativo' ? () => setCancelCredit(c) : undefined,
    })),
  };

  const monthNav = (
    <div className="ck-al-monthnav">
      <IconButton icon="chevron-left" label="Mês anterior" size="sm" variant="secondary" onClick={() => shiftMonth(-1)} />
      <strong>{MONTHS[month.month - 1]} de {month.year}</strong>
      <span>{monthList ? `${monthList.length} ${monthList.length === 1 ? 'check-in' : 'check-ins'}` : 'Carregando…'}</span>
      <IconButton icon="chevron-right" label="Próximo mês" size="sm" variant="secondary" disabled={isCurrentMonth} onClick={() => shiftMonth(1)} />
    </div>
  );

  const reativar = student.status === 'inativo';

  return (
    <LayoutBase title={name} pageHeader={false}>
      <AlunoDetalhe
        student={student}
        enrollment={toEnrollment(aluno)}
        checkins={checkins}
        checkinsHeader={monthNav}
        checkinsEmpty={monthList ? `Nenhum check-in em ${MONTHS[month.month - 1]}` : 'Carregando check-ins…'}
        history={(historico || []).map(toHistory)}
        credits={credits}
        today={today}
        tab={tab}
        onTabChange={setTab}
        onBack={() => router.push('/alunos')}
        onEdit={() => router.push(`/alunos/${alunoId}?edit=true`)}
        onEnroll={() => router.push(`/matriculas/novo?usuario_id=${student.userId}`)}
        onOpenEnrollment={(e) => router.push(`/matriculas/detalhe?id=${e.id}`)}
        onToggleActive={() => setConfirmToggle(true)}
        onAddCredit={() => setCreditModal(true)}
        onViewApp={podeVerApp ? () => { setMotivoApp(''); setVerApp(true); } : undefined}
      />

      <Modal
        open={verApp}
        size="sm"
        title={`Ver app como ${name.split(' ')[0]}`}
        description="Abre o app web numa nova aba, logado como este aluno, por até 1 hora e só para visualizar: check-in, pagamentos e alterações ficam bloqueados. O motivo fica registrado."
        dismissable={!abrindoApp}
        onClose={() => setVerApp(false)}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={abrindoApp} onClick={() => setVerApp(false)}>Cancelar</Button>
            <Button variant="primary" icon="smartphone" loading={abrindoApp} disabled={motivoApp.trim().length < 10} onClick={abrirApp}>Abrir app</Button>
          </>
        )}
      >
        <TextAreaField label="Motivo" value={motivoApp} maxLength={500} placeholder="Ex.: aluno diz que o plano não aparece no app"
          onChange={(e) => setMotivoApp(e.target.value)} hint="Mínimo de 10 caracteres. Abrir outro aluno encerra este acesso." />
      </Modal>

      <Modal
        open={confirmToggle}
        size="sm"
        title={reativar ? `Reativar ${name}?` : `Desativar ${name}?`}
        description={reativar
          ? 'O aluno volta a entrar no app e a fazer check-in, se tiver plano ativo.'
          : 'O aluno deixa de entrar no app e de fazer check-in. O histórico fica guardado.'}
        dismissable={!toggling}
        onClose={() => setConfirmToggle(false)}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={toggling} onClick={() => setConfirmToggle(false)}>Cancelar</Button>
            <Button variant={reativar ? 'primary' : 'danger'} icon={reativar ? 'power' : 'ban'} loading={toggling} onClick={toggleActive}>{reativar ? 'Reativar' : 'Desativar'}</Button>
          </>
        )}
      />

      <Modal
        open={creditModal}
        size="sm"
        title="Lançar crédito"
        description={`O valor abate as próximas cobranças de ${name.split(' ')[0]}.`}
        dismissable={!savingCredit}
        onClose={() => setCreditModal(false)}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={savingCredit} onClick={() => setCreditModal(false)}>Cancelar</Button>
            <Button variant="primary" icon="plus" loading={savingCredit} onClick={addCredit}>Lançar crédito</Button>
          </>
        )}
      >
        <div className="ck-stack" style={{ gap: 'var(--space-4)' }}>
          <TextField label="Valor" inputMode="decimal" placeholder="0,00" icon="wallet" value={creditForm.valor}
            onChange={(e) => setCreditForm((prev) => ({ ...prev, valor: e.target.value.replace(/[^0-9.,]/g, '') }))} />
          <TextField label="Motivo" placeholder="Ex.: cortesia por indicação" value={creditForm.motivo}
            onChange={(e) => setCreditForm((prev) => ({ ...prev, motivo: e.target.value }))} />
        </div>
      </Modal>

      <Modal
        open={!!cancelCredit}
        size="sm"
        title="Cancelar crédito?"
        description={cancelCredit ? `${BRL.format(Number(cancelCredit.valor) || 0)}${cancelCredit.motivo ? `, ${cancelCredit.motivo}` : ''}. O saldo restante deixa de abater cobranças.` : undefined}
        dismissable={!cancelingCredit}
        onClose={() => setCancelCredit(null)}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={cancelingCredit} onClick={() => setCancelCredit(null)}>Manter crédito</Button>
            <Button variant="danger" icon="x" loading={cancelingCredit} onClick={doCancelCredit}>Cancelar crédito</Button>
          </>
        )}
      />
    </LayoutBase>
  );
}
