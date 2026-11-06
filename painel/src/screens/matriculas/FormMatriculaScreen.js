import React, { useEffect, useRef, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { NovaMatricula, Button, Modal, TextField } from '../../components/ck';
import { acessoHibridoService } from '../../services/acessoHibridoService';
import parametrosService from '../../services/parametrosService';
import { matriculaService } from '../../services/matriculaService';
import usuarioService from '../../services/usuarioService';
import alunoService from '../../services/alunoService';
import modalidadeService from '../../services/modalidadeService';
import planoService from '../../services/planoService';
import pacoteService from '../../services/pacoteService';
import { obterMensagemErro } from '../../utils/errorHandler';
import { showSuccess } from '../../utils/toast';

const asList = (response, key) => Array.isArray(response) ? response : response?.[key] || response?.data?.[key] || [];
const money = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
const initialForm = { usuario_id: '', modalidade_id: '', plano_id: '', plano_ciclo_id: '', pacote_id: '', dependentes: [] };
const diasSemana = ['segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado', 'domingo'];
const diasTurma = [
  { value: 1, short: 'Seg', name: 'Segunda-feira' },
  { value: 2, short: 'Ter', name: 'Terça-feira' },
  { value: 3, short: 'Qua', name: 'Quarta-feira' },
  { value: 4, short: 'Qui', name: 'Quinta-feira' },
  { value: 5, short: 'Sex', name: 'Sexta-feira' },
  { value: 6, short: 'Sáb', name: 'Sábado' },
  { value: 7, short: 'Dom', name: 'Domingo' },
];

function TurmasNaSemana({ tipoAcesso, onTipoAcessoChange, grade, onGradeChange, turmas, modalidadeId, plano }) {
  const frequencia = Number(plano?.checkins_semanais) || 0;
  const limite = frequencia > 0 ? frequencia : null;
  const disponiveis = turmas.filter((turma) => (
    Number(turma.modalidade_id) === Number(modalidadeId)
    && Number(turma.ativo) === 1
    && !turma.deleted_at
    && !turma.vigencia_fim
    && !['pausada', 'arquivada'].includes(turma.situacao)
  ));
  const escolhidas = grade.filter((item) => item.turma_fixa_id && item.dia_semana);
  const porDia = diasTurma.map((dia) => ({
    ...dia,
    turmas: disponiveis.filter((turma) => turma.dias_semana?.map(Number).includes(dia.value))
      .sort((a, b) => String(a.horario_inicio).localeCompare(String(b.horario_inicio))),
  })).filter((dia) => dia.turmas.length);
  const selecionar = (turma, dia) => {
    const existente = escolhidas.find((item) => Number(item.dia_semana) === dia);
    if (existente && String(existente.turma_fixa_id) === String(turma.id)) {
      onGradeChange(grade.filter((item) => !(Number(item.dia_semana) === dia && String(item.turma_fixa_id) === String(turma.id))));
      return;
    }
    const outras = escolhidas.filter((item) => Number(item.dia_semana) !== dia);
    if (limite === 1) {
      onGradeChange([{ turma_fixa_id: String(turma.id), dia_semana: dia }]);
      return;
    }
    if (limite != null && outras.length >= limite) return;
    onGradeChange([...outras, { turma_fixa_id: String(turma.id), dia_semana: dia }]);
  };

  const ultima = escolhidas.length ? disponiveis.find((turma) => String(turma.id) === String(escolhidas[escolhidas.length - 1].turma_fixa_id)) : null;
  const sugestoes = ultima && limite !== 1
    ? ultima.dias_semana.map(Number).filter((dia) => !escolhidas.some((item) => Number(item.dia_semana) === dia))
      .slice(0, limite == null ? 7 : Math.max(0, limite - escolhidas.length))
    : [];

  return (
    <div className="ck-nmt">
      <div className="ck-nmt__access" role="group" aria-label="Como o aluno frequenta">
        <button type="button" className="ck-nmt__mode" aria-pressed={tipoAcesso === 'turma_fixa'} onClick={() => onTipoAcessoChange('turma_fixa')}>
          <strong>Turma fixa</strong><span>Vaga nos dias e horários escolhidos.</span>
        </button>
        <button type="button" className="ck-nmt__mode" aria-pressed={tipoAcesso === 'horario_livre'} onClick={() => onTipoAcessoChange('horario_livre')}>
          <strong>Horário livre</strong><span>Check-in em qualquer aula permitida pelo plano.</span>
        </button>
      </div>
      {tipoAcesso === 'turma_fixa' && !plano ? (
        <p className="ck-nmt__empty">Escolha o plano para definir quantas turmas por semana o aluno pode selecionar.</p>
      ) : tipoAcesso === 'turma_fixa' ? (
        <div className="ck-nmt__selector">
          <div className="ck-nmt__head">
            <strong aria-live="polite">{limite == null ? `${escolhidas.length} horário(s)` : `${escolhidas.length} de ${limite} horário(s)`}</strong>
            {escolhidas.length ? <button type="button" className="ck-nmt__clear" onClick={() => onGradeChange([])}>Limpar</button> : null}
          </div>
          {frequencia > 0 ? <p className="ck-nmt__hint">O plano exige exatamente {frequencia} turma(s) por semana.</p> : <p className="ck-nmt__hint">Este plano não limita a quantidade de dias por semana.</p>}
          {sugestoes.length && ultima ? (
            <button type="button" className="ck-nmt__sugg" onClick={() => onGradeChange([
              ...escolhidas,
              ...sugestoes.map((dia) => ({ turma_fixa_id: String(ultima.id), dia_semana: dia })),
            ])}>
              + Adicionar {ultima.nome} também {sugestoes.map((dia) => diasTurma[dia - 1].name.toLowerCase()).join(' e ')}
            </button>
          ) : null}
          {porDia.length ? <div className="ck-nmt__days">
            {porDia.map((dia) => {
              const doDia = escolhidas.find((item) => Number(item.dia_semana) === dia.value);
              return <div key={dia.value} className={`ck-nmt__day${doDia ? ' is-taken' : ''}`} role="group" aria-label={dia.name}>
                <strong className="ck-nmt__dname">{dia.short}</strong>
                <div className="ck-nmt__opts">{dia.turmas.map((turma) => {
                  const selecionada = doDia && String(doDia.turma_fixa_id) === String(turma.id);
                  const bloqueada = !selecionada && !doDia && limite != null && limite !== 1 && escolhidas.length >= limite;
                  return <button key={turma.id} type="button" className="ck-nmt__opt" aria-pressed={!!selecionada} disabled={bloqueada} onClick={() => selecionar(turma, dia.value)}>
                    <span className="ck-nmt__check" aria-hidden="true">{selecionada ? '✓' : ''}</span>
                    <span className="ck-nmt__class">
                      <strong>{String(turma.horario_inicio || '').slice(0, 5)} · {turma.nome}</strong>
                      <small>{turma.capacidade ? `Capacidade: ${turma.capacidade} por aula` : 'Turma recorrente'}</small>
                    </span>
                  </button>;
                })}</div>
              </div>;
            })}
          </div> : <p className="ck-nmt__empty">Nenhuma turma ativa disponível para esta modalidade.</p>}
        </div>
      ) : <p className="ck-nmt__hint">{plano ? 'O plano define quantos check-ins o aluno pode fazer por semana.' : 'Escolha o plano para continuar.'}</p>}
    </div>
  );
}

export default function FormMatriculaScreen() {
  const router = useRouter();
  // ?usuario_id= vem do atalho "Matricular" da lista e do detalhe do aluno
  const { usuario_id: usuarioParam } = useLocalSearchParams();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  // O período já tem cobrança na matrícula do aluno: pede confirmação antes de cobrar de novo
  const [conflitoPeriodo, setConflitoPeriodo] = useState(null);
  const [error, setError] = useState('');
  const [loadError, setLoadError] = useState('');
  const [selectionError, setSelectionError] = useState('');
  const [reload, setReload] = useState(0);
  const [alunos, setAlunos] = useState([]);
  const [alunosBasico, setAlunosBasico] = useState([]);
  const [modalidades, setModalidades] = useState([]);
  const [planos, setPlanos] = useState([]);
  const [ciclos, setCiclos] = useState([]);
  const [pacotes, setPacotes] = useState([]);
  const [turmasFixas, setTurmasFixas] = useState([]);
  const [tipoAcesso, setTipoAcesso] = useState('horario_livre');
  const [grade, setGrade] = useState([{ turma_fixa_id: '', dia_semana: '' }]);
  const [loadingPlans, setLoadingPlans] = useState(false);
  const [loadingCycles, setLoadingCycles] = useState(false);
  const [tipo, setTipo] = useState('plano');
  const [searchDependentes, setSearchDependentes] = useState('');
  const [form, setForm] = useState(() => ({ ...initialForm, usuario_id: usuarioParam ? String(usuarioParam) : '' }));
  const busy = useRef(false);
  const today = new Date();
  const todayISO = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

  useEffect(() => {
    let active = true;
    const load = async () => {
      setLoading(true);
      setLoadError('');
      try {
        const [users, mods, basic, packages, fixas, parametros] = await Promise.all([
          usuarioService.listar(), modalidadeService.listar(), alunoService.listarBasico(), pacoteService.listar(),
          acessoHibridoService.listarTurmas(), parametrosService.listar(),
        ]);
        if (!active) return;
        setAlunos(asList(users, 'usuarios').filter((user) => Number(user.papel_id) === 1 && (user.ativo === true || Number(user.ativo) === 1)));
        setModalidades(asList(mods, 'modalidades'));
        setAlunosBasico(asList(basic, 'alunos'));
        setPacotes(asList(packages, 'pacotes'));
        setTurmasFixas(fixas);
        const padrao = parametros.data.flatMap(c => c.parametros || []).find(p => p.codigo === 'tipo_acesso_padrao');
        setTipoAcesso(padrao?.valor || 'horario_livre');
      } catch (err) {
        if (!active) return;
        console.error('Erro ao carregar dados da matrícula:', err);
        setLoadError(obterMensagemErro(err, 'Não foi possível carregar os dados.'));
      } finally {
        if (active) setLoading(false);
      }
    };
    load();
    return () => { active = false; };
  }, [reload]);

  useEffect(() => {
    let active = true;
    setPlanos([]);
    setSelectionError('');
    if (!form.modalidade_id) {
      setLoadingPlans(false);
      return;
    }
    setLoadingPlans(true);
    planoService.listar(true).then((response) => {
      if (active) setPlanos(asList(response, 'planos').filter((p) =>
        String(p.modalidade_id) === form.modalidade_id && p.ativo && (tipoAcesso === 'turma_fixa' || p.atual)));
    }).catch((err) => {
      if (!active) return;
      console.error('Erro ao carregar planos:', err);
      setSelectionError(obterMensagemErro(err, 'Não foi possível carregar os planos.'));
    }).finally(() => { if (active) setLoadingPlans(false); });
    return () => { active = false; };
  }, [form.modalidade_id, reload, tipoAcesso]);

  useEffect(() => {
    let active = true;
    setCiclos([]);
    const selected = planos.find((p) => String(p.id) === form.plano_id);
    if (!selected) {
      setLoadingCycles(false);
      return;
    }
    setLoadingCycles(true);
    setSelectionError('');
    const load = async () => {
      try {
        const response = selected.ciclos?.length ? selected.ciclos : await planoService.listarCiclos(selected.id);
        if (!active) return;
        const list = asList(response, 'ciclos').slice().sort((a, b) => Number(a.meses) - Number(b.meses));
        setCiclos(list);
        setForm((prev) => ({ ...prev, plano_ciclo_id: list.length === 1 ? String(list[0].id) : '' }));
      } catch (err) {
        if (!active) return;
        console.error('Erro ao carregar ciclos:', err);
        setSelectionError(obterMensagemErro(err, 'Não foi possível carregar os ciclos.'));
      } finally {
        if (active) setLoadingCycles(false);
      }
    };
    load();
    return () => { active = false; };
  }, [form.plano_id, planos]);

  const aluno = alunos.find((a) => String(a.id) === form.usuario_id);
  const plano = planos.find((p) => String(p.id) === form.plano_id);
  const ciclo = ciclos.find((c) => String(c.id) === form.plano_ciclo_id);
  const pacote = pacotes.find((p) => String(p.id) === form.pacote_id);
  const maxDependentes = Number(pacote?.qtd_beneficiarios || 0);
  const dependentes = alunosBasico.filter((a) => String(a.usuario_id) !== form.usuario_id);
  const search = searchDependentes.trim().toLowerCase();
  const filteredDependentes = dependentes.filter((a) => `${a.nome || ''} ${a.email || ''}`.toLowerCase().includes(search));
  const selectedDependentes = dependentes.filter((a) => form.dependentes.includes(String(a.id)));
  const frequenciaPlano = Number(plano?.checkins_semanais) || 0;
  const maximoGrade = frequenciaPlano > 0 ? frequenciaPlano : null;
  const change = (key, value, reset = {}) => {
    setError('');
    setForm((prev) => ({ ...prev, [key]: value || '', ...reset }));
  };
  const toggleDependente = (id) => {
    const value = String(id);
    const exists = form.dependentes.includes(value);
    if (!exists && maxDependentes > 0 && form.dependentes.length >= maxDependentes) {
      setError(`Limite do pacote: ${maxDependentes} dependente(s).`);
      return;
    }
    change('dependentes', exists ? form.dependentes.filter((v) => v !== value) : [...form.dependentes, value]);
  };
  const validate = () => {
    if (tipoAcesso === 'turma_fixa') {
      if (tipo !== 'plano') return 'Turma fixa exige uma matrícula por plano.';
      if (!grade.length || grade.some((item) => !item.turma_fixa_id || !item.dia_semana)) return 'Selecione uma turma e um dia para cada entrada da grade.';
      if (maximoGrade != null && grade.length !== maximoGrade) return `O plano exige exatamente ${maximoGrade} dia(s) e horário(s) por semana.`;
      const chaves = grade.map((item) => `${item.turma_fixa_id}:${item.dia_semana}`);
      if (new Set(chaves).size !== chaves.length) return 'A mesma turma e o mesmo dia não podem aparecer mais de uma vez.';
      if (grade.some((item) => {
        const turma = turmasFixas.find((row) => String(row.id) === String(item.turma_fixa_id));
        return !turma
          || Number(turma.modalidade_id) !== Number(form.modalidade_id)
          || !turma.dias_semana?.map(Number).includes(Number(item.dia_semana));
      })) return 'A grade deve usar turmas da modalidade contratada e dias cadastrados nelas.';
    }
    if (!aluno) return 'Selecione um aluno.';
    if (tipo === 'plano' && (!plano || !ciclo || loadingPlans || loadingCycles)) return 'Selecione modalidade, plano e ciclo de pagamento.';
    if (tipo === 'pacote') {
      if (!pacote) return 'Selecione um pacote.';
      if (maxDependentes > 0 && !form.dependentes.length) return 'Selecione ao menos 1 dependente.';
      if (maxDependentes > 0 && form.dependentes.length > maxDependentes) return `Selecione no máximo ${maxDependentes} dependente(s).`;
      if (selectedDependentes.length !== form.dependentes.length) return 'Revise os dependentes selecionados.';
    }
    return '';
  };
  const review = () => {
    const message = validate();
    setError(message);
    if (!message) setShowConfirmModal(true);
  };
  const confirm = async (permitirCobrancaNoPeriodo = false) => {
    if (busy.current) return;
    const message = validate();
    if (message) {
      setError(message);
      setShowConfirmModal(false);
      return;
    }
    busy.current = true;
    setSaving(true);
    setError('');
    try {
      const basic = alunosBasico.find((a) => String(a.usuario_id) === form.usuario_id);
      const payload = tipo === 'pacote' ? {
        ...(basic?.id ? { aluno_id: Number(basic.id) } : { usuario_id: Number(form.usuario_id) }),
        pacote_id: Number(form.pacote_id),
        dependentes: form.dependentes.map(Number),
      } : {
        tipo_acesso: tipoAcesso,
        grade: tipoAcesso === 'turma_fixa' ? grade.map((item) => ({
          turma_fixa_id: Number(item.turma_fixa_id),
          dia_semana: Number(item.dia_semana),
        })) : [],
        usuario_id: Number(form.usuario_id),
        plano_id: Number(form.plano_id),
        plano_ciclo_id: Number(form.plano_ciclo_id),
        ...(permitirCobrancaNoPeriodo ? { permitir_cobranca_no_periodo: true } : {}),
      };
      await matriculaService.criar(payload);
      setConflitoPeriodo(null);
      showSuccess('Matrícula realizada com sucesso');
      router.push('/matriculas');
    } catch (err) {
      if (err?.code === 'COBRANCA_EXISTENTE_NO_PERIODO') {
        setConflitoPeriodo({ message: err.message || err.mensagemLimpa, matriculaId: err.matricula_id });
        return;
      }
      console.error('Erro ao criar matrícula:', err);
      setConflitoPeriodo(null);
      setError(obterMensagemErro(err, 'Não foi possível realizar a matrícula.'));
    } finally {
      setShowConfirmModal(false);
      setSaving(false);
      busy.current = false;
    }
  };

  return (
    <LayoutBase title="Nova matrícula" pageHeader={false}>
      {loading ? <p role="status">Carregando dados da matrícula…</p> : loadError ? (
        <div role="alert"><p>{loadError}</p><Button onClick={() => setReload((v) => v + 1)}>Tentar novamente</Button></div>
      ) : (
        <>
          {selectionError ? <div role="alert"><p>{selectionError}</p><Button onClick={() => setReload((v) => v + 1)}>Tentar novamente</Button></div> : null}
          <NovaMatricula
            students={alunos.map((a) => ({ id: String(a.id), name: a.nome, email: a.email || '' }))}
            modalities={modalidades.map((m) => ({ id: String(m.id), name: m.nome, icon: m.icone, color: m.cor }))}
            plans={planos.map((p) => ({ id: String(p.id), modalityId: String(p.modalidade_id), name: p.nome, price: Number(p.valor), frequency: `${p.checkins_semanais}x por semana`, durationDays: p.duracao_dias }))}
            hidePackages={tipoAcesso === 'turma_fixa'}
            cycles={ciclos.map((c) => ({ id: String(c.id), planId: form.plano_id, label: c.frequencia_nome || c.nome || 'Ciclo', months: Number(c.meses), price: Number(c.valor), discountPct: Number(c.desconto_percentual), note: c.permite_recorrencia ? 'Permite recorrência' : 'Pagamento avulso' }))}
            packages={pacotes.map((p) => ({ id: String(p.id), name: p.nome, people: Number(p.qtd_beneficiarios || 0), price: Number(p.valor_total), durationDays: p.duracao_dias }))}
            studentId={form.usuario_id}
            modalityId={form.modalidade_id}
            planId={form.plano_id}
            cycleId={form.plano_ciclo_id}
            packageId={form.pacote_id}
            type={tipo}
            schedule={tipo === 'plano' ? <TurmasNaSemana
              tipoAcesso={tipoAcesso}
              onTipoAcessoChange={(value) => {
                if (value === tipoAcesso) return;
                setTipoAcesso(value);
                setGrade([]);
                if (value === 'horario_livre' && plano && !plano.atual) {
                  setForm((current) => ({ ...current, plano_id: '', plano_ciclo_id: '' }));
                }
              }}
              grade={grade}
              onGradeChange={setGrade}
              turmas={turmasFixas}
              modalidadeId={form.modalidade_id}
              plano={plano}
            /> : null}
            scheduleComplete={tipoAcesso === 'horario_livre' || (maximoGrade == null
              ? grade.some((item) => item.turma_fixa_id && item.dia_semana)
              : grade.filter((item) => item.turma_fixa_id && item.dia_semana).length === maximoGrade)}
            scheduleSummary={tipo === 'plano' ? (tipoAcesso === 'horario_livre' ? 'Horário livre' : grade.some((item) => item.turma_fixa_id && item.dia_semana)
              ? grade.filter((item) => item.turma_fixa_id && item.dia_semana).map((item) => {
                const turma = turmasFixas.find((row) => String(row.id) === String(item.turma_fixa_id));
                return `${diasSemana[Number(item.dia_semana) - 1] || 'Dia'} ${String(turma?.horario_inicio || '').slice(0, 5)}`;
              }).join(', ')
              : 'Não escolhidas') : ''}
            onStudentChange={(value) => change('usuario_id', value, { dependentes: [] })}
            onModalityChange={(value) => {
              if (tipoAcesso === 'turma_fixa') setGrade([{ turma_fixa_id: '', dia_semana: '' }]);
              change('modalidade_id', value, { plano_id: '', plano_ciclo_id: '' });
            }}
            onPlanChange={(value) => change('plano_id', value, { plano_ciclo_id: '' })}
            onCycleChange={(value) => change('plano_ciclo_id', value)}
            onPackageChange={(value) => change('pacote_id', value, { dependentes: [] })}
            onTypeChange={(value) => { setTipo(value); setError(''); }}
            today={todayISO}
            startDateEditable={false}
            showAccessEstimate={false}
            loadingCycles={loadingCycles}
            submitting={saving}
            error={error || (loadingPlans ? 'Carregando planos…' : '')}
            onSubmit={review}
            onBack={() => router.push('/matriculas')}
            onCancel={() => router.push('/matriculas')}
            onCreateStudent={() => router.push('/alunos/novo')}
          >
            {tipo === 'pacote' && pacote ? (
              <section className="ck-nm__step">
                <header className="ck-nm__stephead"><span className="ck-nm__num" aria-hidden="true">4</span><h2>Dependentes ({form.dependentes.length}{maxDependentes > 0 ? `/${maxDependentes}` : ''})</h2></header>
                <div className="ck-nm__stepbody">
                  <p>{maxDependentes > 0 ? 'Selecione ao menos um dependente, respeitando o limite do pacote.' : 'Selecione os dependentes, se houver.'}</p>
                  <TextField label="Buscar dependente" value={searchDependentes} onChange={(e) => setSearchDependentes(e.target.value)} />
                  <div className="ck-enrollment-dependents">
                    {filteredDependentes.map((a) => (
                      <label key={a.id} className="ck-enrollment-dependent">
                        <input type="checkbox" checked={form.dependentes.includes(String(a.id))} onChange={() => toggleDependente(a.id)} />
                        <span>{a.nome}<small>{a.email}</small></span>
                      </label>
                    ))}
                    {!filteredDependentes.length ? <p>Nenhum aluno encontrado.</p> : null}
                  </div>
                  <p>Selecionados: {selectedDependentes.map((a) => a.nome).join(', ') || 'Nenhum dependente'}</p>
                </div>
              </section>
            ) : null}
          </NovaMatricula>
          <Modal
            open={showConfirmModal}
            title="Confirmar matrícula"
            description="Revise os dados antes de confirmar."
            dismissable={!saving}
            onClose={() => setShowConfirmModal(false)}
            footer={<>
              <Button variant="ghost" disabled={saving} onClick={() => setShowConfirmModal(false)}>Voltar</Button>
              <Button variant="primary" loading={saving} onClick={() => confirm()}>Confirmar matrícula</Button>
            </>}
          >
            <div className="ck-enrollment-review"><dl>
              <div><dt>Tipo de acesso</dt><dd>{tipoAcesso === 'turma_fixa' ? 'Turma fixa' : 'Horário livre'}</dd></div>
              <div><dt>Aluno</dt><dd>{aluno?.nome}<br />{aluno?.email}</dd></div>
              {tipo === 'plano' ? <>
                <div><dt>Modalidade</dt><dd>{modalidades.find((m) => String(m.id) === form.modalidade_id)?.nome}</dd></div>
                <div><dt>Plano</dt><dd>{plano?.nome}</dd></div>
                {tipoAcesso === 'turma_fixa' ? <div><dt>Grade semanal</dt><dd>{grade.map((item) => {
                  const turma = turmasFixas.find((row) => String(row.id) === String(item.turma_fixa_id));
                  return `${turma?.nome || 'Turma'} · ${diasSemana[Number(item.dia_semana) - 1] || 'Dia não selecionado'}${turma ? ` · ${String(turma.horario_inicio || '').slice(0, 5)}–${String(turma.horario_fim || '').slice(0, 5)}` : ''}`;
                }).join('; ')}</dd></div> : null}
                <div><dt>Ciclo</dt><dd>{ciclo?.frequencia_nome || ciclo?.nome} · {ciclo?.meses} mês(es)</dd></div>
                <div><dt>Primeira cobrança</dt><dd>{money(ciclo?.valor)}</dd></div>
              </> : <>
                <div><dt>Pacote</dt><dd>{pacote?.nome}</dd></div>
                <div><dt>Valor</dt><dd>{money(pacote?.valor_total)}</dd></div>
                <div><dt>Dependentes</dt><dd>{selectedDependentes.map((a) => a.nome).join(', ') || 'Nenhum'}</dd></div>
              </>}
            </dl></div>
          </Modal>
          <Modal
            open={!!conflitoPeriodo}
            title="Esse período já tem cobrança"
            description="Matricular agora gera uma nova cobrança para o mesmo período."
            dismissable={!saving}
            onClose={() => setConflitoPeriodo(null)}
            footer={<>
              {conflitoPeriodo?.matriculaId ? (
                <Button variant="ghost" disabled={saving} onClick={() => router.push(`/matriculas/detalhe?id=${conflitoPeriodo.matriculaId}`)}>Ver matrícula</Button>
              ) : null}
              <Button variant="ghost" disabled={saving} onClick={() => setConflitoPeriodo(null)}>Voltar</Button>
              <Button variant="danger" loading={saving} onClick={() => confirm(true)}>Cobrar mesmo assim</Button>
            </>}
          >
            <p>{conflitoPeriodo?.message}</p>
          </Modal>
        </>
      )}
    </LayoutBase>
  );
}
