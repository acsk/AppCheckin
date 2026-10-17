import React, { useEffect, useRef, useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { NovaMatricula, Button, Modal, TextField } from '../../components/ck';
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

export default function FormMatriculaScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [showConfirmModal, setShowConfirmModal] = useState(false);
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
  const [loadingPlans, setLoadingPlans] = useState(false);
  const [loadingCycles, setLoadingCycles] = useState(false);
  const [tipo, setTipo] = useState('plano');
  const [searchDependentes, setSearchDependentes] = useState('');
  const [form, setForm] = useState(initialForm);
  const busy = useRef(false);
  const today = new Date();
  const todayISO = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

  useEffect(() => {
    let active = true;
    const load = async () => {
      setLoading(true);
      setLoadError('');
      try {
        const [users, mods, basic, packages] = await Promise.all([
          usuarioService.listar(), modalidadeService.listar(), alunoService.listarBasico(), pacoteService.listar(),
        ]);
        if (!active) return;
        setAlunos(asList(users, 'usuarios').filter((user) => Number(user.papel_id) === 1 && (user.ativo === true || Number(user.ativo) === 1)));
        setModalidades(asList(mods, 'modalidades'));
        setAlunosBasico(asList(basic, 'alunos'));
        setPacotes(asList(packages, 'pacotes'));
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
        String(p.modalidade_id) === form.modalidade_id && p.ativo && p.atual));
    }).catch((err) => {
      if (!active) return;
      console.error('Erro ao carregar planos:', err);
      setSelectionError(obterMensagemErro(err, 'Não foi possível carregar os planos.'));
    }).finally(() => { if (active) setLoadingPlans(false); });
    return () => { active = false; };
  }, [form.modalidade_id, reload]);

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
  const confirm = async () => {
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
        usuario_id: Number(form.usuario_id),
        plano_id: Number(form.plano_id),
        plano_ciclo_id: Number(form.plano_ciclo_id),
      };
      await matriculaService.criar(payload);
      showSuccess('Matrícula realizada com sucesso');
      router.push('/matriculas');
    } catch (err) {
      console.error('Erro ao criar matrícula:', err);
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
            modalities={modalidades.map((m) => ({ id: String(m.id), name: m.nome }))}
            plans={planos.map((p) => ({ id: String(p.id), modalityId: String(p.modalidade_id), name: p.nome, price: Number(p.valor), frequency: `${p.checkins_semanais}x por semana`, durationDays: p.duracao_dias }))}
            cycles={ciclos.map((c) => ({ id: String(c.id), planId: form.plano_id, label: c.frequencia_nome || c.nome || 'Ciclo', months: Number(c.meses), price: Number(c.valor), discountPct: Number(c.desconto_percentual), note: c.permite_recorrencia ? 'Permite recorrência' : 'Pagamento avulso' }))}
            packages={pacotes.map((p) => ({ id: String(p.id), name: p.nome, people: Number(p.qtd_beneficiarios || 0), price: Number(p.valor_total), durationDays: p.duracao_dias }))}
            studentId={form.usuario_id}
            modalityId={form.modalidade_id}
            planId={form.plano_id}
            cycleId={form.plano_ciclo_id}
            packageId={form.pacote_id}
            type={tipo}
            onStudentChange={(value) => change('usuario_id', value, { dependentes: [] })}
            onModalityChange={(value) => change('modalidade_id', value, { plano_id: '', plano_ciclo_id: '' })}
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
                <header className="ck-nm__stephead"><h2>Dependentes ({form.dependentes.length}{maxDependentes > 0 ? `/${maxDependentes}` : ''})</h2></header>
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
              <Button variant="primary" loading={saving} onClick={confirm}>Confirmar matrícula</Button>
            </>}
          >
            <div className="ck-enrollment-review"><dl>
              <div><dt>Aluno</dt><dd>{aluno?.nome}<br />{aluno?.email}</dd></div>
              {tipo === 'plano' ? <>
                <div><dt>Modalidade</dt><dd>{modalidades.find((m) => String(m.id) === form.modalidade_id)?.nome}</dd></div>
                <div><dt>Plano</dt><dd>{plano?.nome}</dd></div>
                <div><dt>Ciclo</dt><dd>{ciclo?.frequencia_nome || ciclo?.nome} · {ciclo?.meses} mês(es)</dd></div>
                <div><dt>Primeira cobrança</dt><dd>{money(ciclo?.valor)}</dd></div>
              </> : <>
                <div><dt>Pacote</dt><dd>{pacote?.nome}</dd></div>
                <div><dt>Valor</dt><dd>{money(pacote?.valor_total)}</dd></div>
                <div><dt>Dependentes</dt><dd>{selectedDependentes.map((a) => a.nome).join(', ') || 'Nenhum'}</dd></div>
              </>}
            </dl></div>
          </Modal>
        </>
      )}
    </LayoutBase>
  );
}
