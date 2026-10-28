import React, { useEffect, useState } from 'react';
import { useRouter, useLocalSearchParams } from 'expo-router';
import planoService from '../../services/planoService';
import modalidadeService from '../../services/modalidadeService';
import { authService } from '../../services/authService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Modal } from '../../components/ck';
import { PlanoForm, CicloModal, GerarCiclosModal, missingTypes, monthlyBase, isDailyPlan, UNLIMITED } from '../../components/ck/planos';
import { showSuccess, showError } from '../../utils/toast';
import TermosFidelizacaoModal from '../../components/ck/termosFidelizacao';
import useTermosFidelizacao from './useTermosFidelizacao';

const bool = (v) => v === true || v === 1 || v === '1';

const EMPTY = { modalidade_id: '', nome: '', descricao: '', checkins_semanais: '', duracao_dias: '30', ativo: true, atual: true };

const toCycle = (c, types) => {
  const type = types.find((t) => t.codigo === c.codigo || t.id === c.assinatura_frequencia_id || t.id === c.tipo_ciclo_id);
  return {
    id: c.id,
    typeId: c.assinatura_frequencia_id || type?.id,
    label: c.nome || type?.nome,
    codigo: c.codigo || type?.codigo,
    nome: c.nome || type?.nome,
    months: Number(c.meses) || 1,
    meses: Number(c.meses) || 1,
    amount: Number(c.valor) || 0,
    recurring: bool(c.permite_recorrencia),
    makeup: bool(c.permite_reposicao),
    active: bool(c.ativo),
    fidelity: {
      mensal: bool(c.cobranca_mensal),
      renovacao: c.politica_renovacao || 'mensal',
      regularizacao: bool(c.regularizacao_ativa),
      proporcional: bool(c.regularizacao_proporcional),
      arrependimento: Number(c.arrependimento_dias) || 7,
      termos: c.termos || '',
    },
  };
};

// Campos de fidelização do ciclo (assinatura mensal)
const fidelidadeParaApi = (f = {}) => ({
  cobranca_mensal: f.mensal ? 1 : 0,
  politica_renovacao: f.renovacao || 'mensal',
  regularizacao_ativa: f.regularizacao ? 1 : 0,
  regularizacao_proporcional: f.proporcional ? 1 : 0,
  arrependimento_dias: Number(f.arrependimento) || 7,
  termos: f.termos || '',
});

export default function FormPlanoScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const planoId = id ? parseInt(id, 10) : null;
  const isEdit = !!planoId;

  const [loading, setLoading] = useState(true);
  const [readOnly, setReadOnly] = useState(false);
  const termos = useTermosFidelizacao(isEdit);
  // Plano com matrículas: modalidade, aulas/semana e duração ficam fixas (a API também recusa)
  const [travado, setTravado] = useState(false);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const [modalidades, setModalidades] = useState([]);
  const [values, setValues] = useState(EMPTY);
  const [types, setTypes] = useState([]);
  const [cycles, setCycles] = useState([]);
  const [loadingCycles, setLoadingCycles] = useState(false);
  const [cycleModal, setCycleModal] = useState({ open: false, cycle: null });
  const [savingCycle, setSavingCycle] = useState(false);
  const [removeCycle, setRemoveCycle] = useState(null);
  const [removing, setRemoving] = useState(false);
  const [blockCycle, setBlockCycle] = useState(null);
  const [blockingId, setBlockingId] = useState(null);
  const [confirmGenerate, setConfirmGenerate] = useState(false);
  const [generating, setGenerating] = useState(false);

  const loadCycles = async (tipos = types) => {
    if (!planoId) return;
    setLoadingCycles(true);
    try {
      const response = await planoService.listarCiclos(planoId);
      setCycles((response.ciclos || []).map((c) => toCycle(c, tipos)).sort((a, b) => a.months - b.months));
    } catch {
      showError('Não foi possível carregar os ciclos do plano');
    } finally {
      setLoadingCycles(false);
    }
  };

  useEffect(() => {
    (async () => {
      try {
        const user = await authService.getCurrentUser();
        setReadOnly(user?.papel_id === 4);
        const [mods, tiposResp] = await Promise.all([
          modalidadeService.listar(true).catch(() => []),
          planoService.listarTiposCiclo().catch(() => ({ data: [] })),
        ]);
        setModalidades(Array.isArray(mods) ? mods : []);
        const tipos = tiposResp?.data || [];
        setTypes(tipos);
        if (isEdit) {
          const plano = await planoService.buscar(planoId);
          setValues({
            modalidade_id: plano.modalidade_id?.toString() || '',
            nome: plano.nome || '',
            descricao: plano.descricao || '',
            checkins_semanais: plano.checkins_semanais?.toString() || '',
            duracao_dias: plano.duracao_dias ? String(plano.duracao_dias) : '30',
            ativo: bool(plano.ativo),
            atual: bool(plano.atual),
          });
          setTravado(!!plano.possui_contratos);
          await loadCycles(tipos);
        }
      } catch {
        showError(isEdit ? 'Não foi possível carregar o plano' : 'Não foi possível carregar as modalidades');
        if (isEdit) router.replace('/planos');
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  const onChange = (next) => {
    const changed = Object.keys(next).filter((k) => next[k] !== values[k]);
    if (changed.includes('ativo') && !next.ativo) next.atual = false;
    setValues(next);
    if (changed.some((k) => errors[k])) setErrors((e) => { const c = { ...e }; changed.forEach((k) => delete c[k]); return c; });
  };

  const validate = () => {
    const e = {};
    if (!values.modalidade_id) e.modalidade_id = 'Escolha a modalidade.';
    if (!values.nome.trim()) e.nome = 'Dê um nome ao plano.';
    const ck = parseInt(values.checkins_semanais, 10);
    if (!ck || ck < 1) e.checkins_semanais = 'Escolha quantos check-ins por semana.';
    setErrors(e);
    return !Object.keys(e).length;
  };

  const save = async () => {
    if (!validate()) return;
    setSaving(true);
    try {
      const payload = {
        modalidade_id: parseInt(values.modalidade_id, 10),
        nome: values.nome.trim(),
        descricao: values.descricao,
        checkins_semanais: isDailyPlan(values) ? 1 : parseInt(values.checkins_semanais, 10) || UNLIMITED,
        duracao_dias: parseInt(values.duracao_dias, 10) || 30,
        ativo: values.ativo ? 1 : 0,
        atual: values.atual ? 1 : 0,
      };
      if (isEdit) {
        // planos.valor é mantido pelo backend a partir do ciclo Mensal.
        const result = await planoService.atualizar(planoId, payload);
        showSuccess(result.message || 'Plano atualizado');
        router.push('/planos');
      } else {
        const result = await planoService.criar({ ...payload, valor: 0 });
        showSuccess('Plano criado. Agora adicione os ciclos de cobrança.');
        const novoId = result?.plano?.id;
        router.replace(novoId ? `/planos/${novoId}` : '/planos');
      }
    } catch (err) {
      showError(err?.errors?.join('\n') || err?.error || `Não foi possível ${isEdit ? 'salvar' : 'criar'} o plano`);
    } finally {
      setSaving(false);
    }
  };

  const saveCycle = async (data) => {
    setSavingCycle(true);
    try {
      const payload = {
        valor: data.amount,
        permite_recorrencia: data.recurring ? 1 : 0,
        permite_reposicao: data.makeup ? 1 : 0,
        ativo: data.active ? 1 : 0,
        ...fidelidadeParaApi(data.fidelity),
      };
      if (data.id) {
        const res = await planoService.atualizarCiclo(planoId, data.id, payload);
        const r = res?.reajuste;
        // Novo preço: vendas novas já; alunos atuais na próxima renovação; assinatura em fidelidade só depois dela
        const partes = r ? [
          r.matriculas ? `${r.matriculas} aluno(s) à vista pagam o novo valor na próxima renovação` : null,
          r.contratos ? `${r.contratos} assinatura(s) sem fidelidade reajustada(s) a partir da próxima cobrança` : null,
          r.em_fidelidade ? `${r.em_fidelidade} em fidelidade mantêm o valor até o fim dela` : null,
          r.falhas ? `${r.falhas} assinatura(s) não atualizada(s) no Asaas: tente salvar de novo` : null,
        ].filter(Boolean) : [];
        showSuccess(partes.length ? `Ciclo atualizado. ${partes.join('; ')}.` : 'Ciclo atualizado');
      } else {
        await planoService.criarCiclo(planoId, { ...payload, assinatura_frequencia_id: data.typeId });
        showSuccess('Ciclo adicionado');
      }
      setCycleModal({ open: false, cycle: null });
      await loadCycles();
    } catch (err) {
      showError(err?.error || err?.errors?.join('\n') || 'Não foi possível salvar o ciclo');
    } finally {
      setSavingCycle(false);
    }
  };

  const confirmRemoveCycle = async () => {
    setRemoving(true);
    try {
      await planoService.excluirCiclo(planoId, removeCycle.id);
      showSuccess('Ciclo excluído');
      setRemoveCycle(null);
      await loadCycles();
    } catch (err) {
      showError(err?.error || 'Não foi possível excluir o ciclo');
    } finally {
      setRemoving(false);
    }
  };

  const setCycleBlocked = async (cycle, blocked) => {
    setBlockingId(cycle.id);
    try {
      await planoService.atualizarCiclo(planoId, cycle.id, { ativo: blocked ? 0 : 1 });
      showSuccess(`Ciclo ${cycle.label} ${blocked ? 'bloqueado' : 'desbloqueado'}`);
      setBlockCycle(null);
      await loadCycles();
    } catch (err) {
      showError(err?.error || `Não foi possível ${blocked ? 'bloquear' : 'desbloquear'} o ciclo`);
    } finally {
      setBlockingId(null);
    }
  };

  const toggleCycleBlock = (cycle) => {
    if (cycle.active) setBlockCycle(cycle);
    else setCycleBlocked(cycle, false);
  };

  const generate = async (list) => {
    setGenerating(true);
    const failed = [];
    for (const c of list) {
      try {
        await planoService.criarCiclo(planoId, {
          assinatura_frequencia_id: c.typeId,
          valor: c.amount,
          permite_recorrencia: c.recurring ? 1 : 0,
          permite_reposicao: c.makeup ? 1 : 0,
          ativo: c.active ? 1 : 0,
        });
      } catch (err) {
        failed.push(`${c.label}: ${err?.error || err?.errors?.join(', ') || 'erro ao criar'}`);
      }
    }
    const created = list.length - failed.length;
    if (created) showSuccess(created === 1 ? '1 ciclo criado' : `${created} ciclos criados`);
    if (failed.length) showError(`Não foi possível criar:\n${failed.join('\n')}`);
    if (!failed.length) setConfirmGenerate(false);
    await loadCycles();
    setGenerating(false);
  };

  const title = isEdit ? (readOnly ? 'Ver plano' : 'Editar plano') : 'Novo plano';

  return (
    <LayoutBase title={title} pageHeader={false}>
      {loading ? <p role="status" className="ck-enrollment-state">Carregando plano…</p> : (
        <PlanoForm
          values={values}
          onChange={onChange}
          errors={errors}
          modalities={modalidades}
          isEdit={isEdit}
          readOnly={readOnly}
          lockedRules={travado}
          saving={saving}
          onSave={save}
          onCancel={() => router.push('/planos')}
          onBack={() => router.push('/planos')}
          cycles={cycles}
          cyclesLoading={loadingCycles}
          onAddCycle={() => setCycleModal({ open: true, cycle: null })}
          onEditCycle={(cycle) => setCycleModal({ open: true, cycle })}
          onRemoveCycle={setRemoveCycle}
          onToggleCycleBlock={toggleCycleBlock}
          blockingCycleId={blockingId}
          onGenerateCycles={() => setConfirmGenerate(true)}
          generating={generating}
          missingCount={missingTypes(types, cycles).length}
        />
      )}

      <CicloModal
        open={cycleModal.open}
        cycle={cycleModal.cycle}
        types={types}
        cycles={cycles}
        monthly={monthlyBase(cycles)}
        saving={savingCycle}
        onSave={saveCycle}
        onCancel={() => setCycleModal({ open: false, cycle: null })}
        termosAcademia={termos.texto}
        onEditarTermos={termos.abrir}
      />

      <TermosFidelizacaoModal open={termos.aberto} dados={termos.dados} saving={termos.salvando} onCancel={termos.fechar}
        onSave={(payload) => termos.salvar(payload, () => loadCycles())} />

      <Modal
        open={!!removeCycle}
        size="sm"
        title={`Excluir ciclo ${removeCycle?.label || ''}?`.trim()}
        description="O ciclo deixa de ser oferecido para novas matrículas."
        dismissable={!removing}
        onClose={() => setRemoveCycle(null)}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={removing} onClick={() => setRemoveCycle(null)}>Cancelar</Button>
            <Button variant="danger" icon="trash" loading={removing} onClick={confirmRemoveCycle}>Excluir ciclo</Button>
          </>
        )}
      >
        <p className="ck-pl__confirm">Se quiser só parar de vender este ciclo, use "Bloquear ciclo" no menu dele.</p>
      </Modal>

      <Modal
        open={!!blockCycle}
        size="sm"
        title={`Bloquear o ciclo ${blockCycle?.label || ''}?`.trim()}
        description="Ninguém contrata este ciclo, nem pelo app nem por nova matrícula, até você desbloquear."
        dismissable={!blockingId}
        onClose={() => setBlockCycle(null)}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={!!blockingId} onClick={() => setBlockCycle(null)}>Cancelar</Button>
            <Button variant="danger" icon="lock" loading={!!blockingId} onClick={() => setCycleBlocked(blockCycle, true)}>Bloquear ciclo</Button>
          </>
        )}
      >
        <p className="ck-pl__confirm">Quem já tem matrícula neste ciclo continua normalmente. O valor e as opções do ciclo ficam guardados.</p>
      </Modal>

      <GerarCiclosModal
        open={confirmGenerate}
        types={types}
        cycles={cycles}
        monthly={monthlyBase(cycles)}
        saving={generating}
        onConfirm={generate}
        onCancel={() => setConfirmGenerate(false)}
      />
    </LayoutBase>
  );
}
