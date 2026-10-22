import React, { useEffect, useRef, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { authService } from '../../services/authService';
import superAdminService from '../../services/superAdminService';
import planosSistemaService from '../../services/planosSistemaService';
import cepService from '../../services/cepService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Modal, TextAreaField } from '../../components/ck';
import { AcademiaForm, planFromApi } from '../../components/ck/contratos';
import { showSuccess, showError } from '../../utils/toast';

const bool = (v) => v === undefined || v === null || v === true || v === 1 || v === '1';
const fdate = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : null);
const apiError = (error, fallback) => {
  const d = error?.response?.data || error || {};
  if (Array.isArray(d.errors) && d.errors.length) return d.errors.join(', ');
  return d.message || d.error || fallback;
};

const toAcademy = (a) => ({
  name: a.nome || '',
  cnpj: a.cnpj || '',
  phone: a.telefone || '',
  email: a.email || '',
  address: { cep: a.cep, street: a.logradouro, number: a.numero, complement: a.complemento, district: a.bairro, city: a.cidade, uf: a.estado },
  whatsapp: Array.isArray(a.whatsapp_links) ? a.whatsapp_links : [],
  active: bool(a.ativo),
});

const toAdmin = (a) => ({
  id: a.id,
  name: a.nome || '',
  email: a.email || '',
  phone: a.telefone || '',
  cpf: a.cpf || '',
  active: bool(a.ativo),
  roles: (a.papeis || []).map((p) => (typeof p === 'object' ? Number(p.id) : Number(p))),
});

const contractSummary = (c) => {
  if (!c) return null;
  const preco = { gratuito: 'Grátis', por_aluno: 'Cobrança por aluno ativo', fixo: 'Mensalidade fixa' }[c.tipo_cobranca] || c.status_nome;
  const fim = c.data_fim ? `até ${fdate(c.data_fim)}` : 'sem data de fim';
  const status = c.status_id && Number(c.status_id) !== 1 && c.status_nome ? `. ${c.status_nome}` : '';
  return { id: c.id, plan: c.plano_nome, note: `${preco}, ${fim}${status}` };
};

const adminPayload = (d) => ({
  nome: d.name,
  email: d.email,
  telefone: d.phone,
  cpf: d.cpf,
  papeis: d.roles,
  ...(d.password ? { senha: d.password } : {}),
});

export default function AcademiaFormScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const academiaId = id ? parseInt(id, 10) : null;
  const isEdit = !!academiaId;

  const [academy, setAcademy] = useState(null);
  const [contract, setContract] = useState(null);
  const [admins, setAdmins] = useState([]);
  const [adminsLoading, setAdminsLoading] = useState(false);
  const [plans, setPlans] = useState([]);
  const [plansLoading, setPlansLoading] = useState(!isEdit);
  const [planId, setPlanId] = useState(null);
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [toToggle, setToToggle] = useState(null);
  const [toggling, setToggling] = useState(false);
  const localKey = useRef(0);
  const [acessar, setAcessar] = useState(false);
  const [motivo, setMotivo] = useState('');
  const [acessando, setAcessando] = useState(false);

  // "Acessar como academia": entra como admin desta academia por até 2h. Motivo e alterações
  // ficam registrados e a academia vê o acesso em Minha conta → Segurança.
  const iniciarAcesso = async () => {
    setAcessando(true);
    try {
      await authService.iniciarAcessoSuporte(academiaId, motivo.trim());
      router.replace('/');
    } catch (error) {
      showError(apiError(error, 'Não foi possível acessar a academia'));
      setAcessando(false);
    }
  };

  useEffect(() => {
    (async () => {
      const user = await authService.getCurrentUser();
      if (!user || user.papel_id !== 4) {
        showError('Acesso negado. Apenas Super Admin pode acessar esta página.');
        router.replace('/');
        return;
      }
      if (isEdit) loadAcademy();
      else loadPlans();
    })();
  }, []);

  const loadPlans = async () => {
    setPlansLoading(true);
    try {
      const res = await planosSistemaService.listarDisponiveis();
      const list = (res.planos || []).map(planFromApi);
      setPlans(list);
      const padrao = list.find((p) => p.code === 'free') || list.find((p) => p.type === 'gratuito') || list[0];
      setPlanId(padrao ? padrao.id : null);
    } catch (error) {
      showError(apiError(error, 'Erro ao carregar planos do sistema'));
    } finally {
      setPlansLoading(false);
    }
  };

  const loadAcademy = async () => {
    setLoading(true);
    try {
      const res = await superAdminService.buscarAcademia(academiaId);
      setAcademy(toAcademy(res.academia || {}));
      setContract(contractSummary(res.academia?.contrato));
      loadAdmins();
    } catch (error) {
      showError(apiError(error, 'Erro ao carregar dados da academia'));
      router.push('/academias');
    } finally {
      setLoading(false);
    }
  };

  const loadAdmins = async () => {
    setAdminsLoading(true);
    try {
      const res = await superAdminService.listarAdmins(academiaId);
      setAdmins((res.admins || res || []).map(toAdmin));
    } catch (error) {
      showError(apiError(error, 'Erro ao carregar administradores'));
    } finally {
      setAdminsLoading(false);
    }
  };

  const lookupCep = async (cep) => {
    const d = await cepService.buscar(cep);
    return d ? { street: d.logradouro, district: d.bairro, city: d.cidade, uf: d.estado } : null;
  };

  // Na criação os admins ficam só na tela: o primeiro vai junto com a academia, os outros logo depois
  const saveAdmin = async (data, target) => {
    if (!isEdit) {
      if (target) {
        setAdmins((list) => list.map((a) => (a.key === target.key ? { ...a, ...data, password: data.password || a.password } : a)));
      } else {
        localKey.current += 1;
        setAdmins((list) => [...list, { ...data, key: `novo-${localKey.current}`, active: true }]);
      }
      return true;
    }
    try {
      if (target) {
        await superAdminService.atualizarAdmin(academiaId, target.id, adminPayload(data));
        showSuccess('Administrador atualizado');
      } else {
        await superAdminService.criarAdmin(academiaId, adminPayload(data));
        showSuccess('Administrador adicionado');
      }
      loadAdmins();
      return true;
    } catch (error) {
      showError(apiError(error, 'Erro ao salvar administrador'));
      return false;
    }
  };

  const confirmToggle = async () => {
    const a = toToggle;
    if (!a) return;
    setToggling(true);
    try {
      if (a.active) {
        await superAdminService.desativarAdmin(academiaId, a.id, { papeis: Array.from(new Set([3, ...a.roles])) });
        showSuccess('Acesso desativado');
      } else {
        await superAdminService.reativarAdmin(academiaId, a.id);
        showSuccess('Acesso reativado');
      }
      setToToggle(null);
      loadAdmins();
    } catch (error) {
      showError(apiError(error, 'Não foi possível alterar o acesso'));
    } finally {
      setToggling(false);
    }
  };

  const save = async (v) => {
    const base = {
      nome: v.name,
      email: v.email,
      cnpj: v.cnpj,
      telefone: v.phone,
      cep: v.address.cep,
      logradouro: v.address.street,
      numero: v.address.number,
      complemento: v.address.complement,
      bairro: v.address.district,
      cidade: v.address.city,
      estado: v.address.uf,
      whatsapp_links: v.whatsapp,
    };
    setSaving(true);
    try {
      if (isEdit) {
        await superAdminService.atualizarAcademia(academiaId, { ...base, ativo: v.active });
        showSuccess('Academia atualizada');
        router.push('/academias');
        return;
      }
      const [first, ...rest] = admins;
      const res = await superAdminService.criarAcademia({
        ...base,
        responsavel_nome: first.name,
        responsavel_email: first.email,
        responsavel_telefone: first.phone,
        responsavel_cpf: first.cpf,
        senha_admin: first.password,
        plano_sistema_id: v.planId,
        data_fim: v.endDate,
      });
      const novoId = res.academia?.id;
      const pendencias = [];
      if (novoId) {
        if (res.admin?.id && first.roles.some((r) => r !== 3)) {
          pendencias.push(superAdminService.atualizarAdmin(novoId, res.admin.id, adminPayload({ ...first, password: null })));
        }
        rest.forEach((a) => pendencias.push(superAdminService.criarAdmin(novoId, adminPayload(a))));
        if (!v.active) pendencias.push(superAdminService.atualizarAcademia(novoId, { nome: v.name, email: v.email, ativo: false }));
      }
      const falhas = (await Promise.allSettled(pendencias)).filter((r) => r.status === 'rejected');
      if (falhas.length) {
        showError(`Academia criada, mas ${falhas.length === 1 ? '1 ajuste falhou' : `${falhas.length} ajustes falharam`}: ${apiError(falhas[0].reason, 'confira os administradores')}`);
        router.replace(`/academias/${novoId}`);
        return;
      }
      showSuccess('Academia cadastrada');
      router.push('/academias');
    } catch (error) {
      showError(apiError(error, 'Erro ao salvar academia'));
    } finally {
      setSaving(false);
    }
  };

  const title = isEdit ? 'Editar academia' : 'Nova academia';

  return (
    <LayoutBase title={isEdit && academy ? academy.name : title} pageHeader={false}>
      {loading || (isEdit && !academy) ? null : (
        <AcademiaForm
          mode={isEdit ? 'editar' : 'novo'}
          academy={academy}
          admins={admins}
          adminsLoading={adminsLoading}
          contract={contract}
          plans={plans}
          plansLoading={plansLoading}
          planId={planId}
          onPlanChange={setPlanId}
          saving={saving}
          onSave={save}
          onSaveAdmin={saveAdmin}
          onToggleAdmin={isEdit ? (a) => setToToggle(a) : undefined}
          onRemoveAdmin={isEdit ? undefined : (a) => setAdmins((list) => list.filter((x) => x.key !== a.key))}
          onCepLookup={lookupCep}
          onOpenContract={isEdit ? () => router.push(contract ? `/contratos/detalhe?id=${contract.id}` : `/contratos/novo?academiaId=${academiaId}`) : undefined}
          onBack={() => router.push('/academias')}
          onCancel={() => router.push('/academias')}
          headerActions={isEdit && academy?.active ? (
            <Button icon="key" onClick={() => { setMotivo(''); setAcessar(true); }}>Acessar como academia</Button>
          ) : null}
        />
      )}
      <Modal
        open={acessar}
        size="sm"
        title={`Acessar ${academy?.name || 'a academia'}`}
        description="Você entra no painel como administrador desta academia por até 2 horas. O motivo, o horário e cada alteração ficam registrados e aparecem para a academia."
        dismissable={!acessando}
        onClose={() => setAcessar(false)}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={acessando} onClick={() => setAcessar(false)}>Cancelar</Button>
            <Button variant="primary" icon="key" loading={acessando} disabled={motivo.trim().length < 10} onClick={iniciarAcesso}>Acessar</Button>
          </>
        )}
      >
        <TextAreaField label="Motivo do acesso" value={motivo} onChange={(e) => setMotivo(e.target.value)}
          hint="Mínimo de 10 caracteres. A academia vai ver este texto." />
      </Modal>
      <Modal
        open={!!toToggle}
        onClose={() => !toggling && setToToggle(null)}
        size="sm"
        title={toToggle?.active ? 'Desativar acesso?' : 'Reativar acesso?'}
        description={toToggle ? (toToggle.active
          ? `${toToggle.name} deixa de entrar no painel desta academia.`
          : `${toToggle.name} volta a entrar no painel desta academia.`) : ''}
        footer={
          <>
            <Button variant="ghost" onClick={() => setToToggle(null)} disabled={toggling}>Cancelar</Button>
            <Button variant={toToggle?.active ? 'danger' : 'primary'} icon={toToggle?.active ? 'ban' : 'power'} loading={toggling} onClick={confirmToggle}>
              {toToggle?.active ? 'Desativar' : 'Reativar'}
            </Button>
          </>
        }
      />
    </LayoutBase>
  );
}
