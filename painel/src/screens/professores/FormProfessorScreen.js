import React, { useEffect, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { professorService } from '../../services/professorService';
import modalidadeService from '../../services/modalidadeService';
import api from '../../services/api';
import LayoutBase from '../../components/LayoutBase';
import BuscarProfessorCpfModal from '../../components/BuscarProfessorCpfModal';
import { Button, Modal } from '../../components/ck';
import { ProfessorForm } from '../../components/ck/professores';
import { titleCase } from '../../components/ck/pessoas';
import { quandoAula } from './ProfessoresScreen';
import { showSuccess, showError } from '../../utils/toast';

const bool = (v) => v === true || v === 1 || v === '1';
const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const iso = (v) => (v ? String(v).slice(0, 10) : null);

// Professor da API → props do ProfessorForm
const toTeacher = (p) => ({
  id: p.id,
  userId: p.usuario_id,
  name: p.nome || '',
  cpf: p.cpf || '',
  birthDate: iso(p.data_nascimento),
  email: p.email || '',
  phone: p.telefone || '',
  whatsapp: p.whatsapp || '',
  cref: p.cref || '',
  crefDue: iso(p.cref_validade),
  bond: p.vinculo_id != null ? String(p.vinculo_id) : '',
  hiredAt: iso(p.admitido_em),
  modalities: (p.modalidade_ids || []).map(String),
  emergency: { name: p.emergencia_nome, phone: p.emergencia_telefone, relation: p.emergencia_parentesco },
  address: { cep: p.cep, street: p.logradouro, number: p.numero, complement: p.complemento, district: p.bairro, city: p.cidade, uf: p.estado },
  notes: p.observacoes || '',
  active: bool(p.vinculo_ativo ?? p.ativo),
  weeklyClasses: Number(p.aulas_semana || 0),
  nextClass: quandoAula(p.proxima_aula),
  nextClassDate: p.proxima_aula?.data || null,
});

// Saída do ProfessorForm → payload da API (campo vazio vai como null e limpa)
const toPayload = (d) => ({
  nome: d.name,
  cpf: d.cpf,
  email: d.email,
  telefone: d.phone,
  whatsapp: d.whatsapp,
  data_nascimento: d.birthDate,
  cref: d.cref,
  cref_validade: d.crefDue,
  vinculo_id: d.bond ? Number(d.bond) : null,
  admitido_em: d.hiredAt,
  modalidade_ids: d.modalities.map(Number),
  emergencia_nome: d.emergency?.name || null,
  emergencia_telefone: d.emergency?.phone || null,
  emergencia_parentesco: d.emergency?.relation || null,
  cep: d.address.cep,
  logradouro: d.address.street,
  numero: d.address.number,
  complemento: d.address.complement,
  bairro: d.address.district,
  cidade: d.address.city,
  estado: d.address.uf,
  observacoes: d.notes,
  ativo: d.active,
});

export default function FormProfessorScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const professorId = id ? parseInt(id, 10) : null;
  const isEdit = !!professorId;

  const [teacher, setTeacher] = useState(null);
  const [bonds, setBonds] = useState([]);
  const [modalities, setModalities] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [buscarCpf, setBuscarCpf] = useState(!isEdit);
  const [server, setServer] = useState({ errors: null, values: null });
  const [credenciais, setCredenciais] = useState(null);

  useEffect(() => {
    (async () => {
      try {
        const [vinculos, mods, professor] = await Promise.all([
          professorService.listarVinculos().catch(() => []),
          modalidadeService.listar(true).catch(() => []),
          isEdit ? professorService.buscarPorId(professorId) : null,
        ]);
        setBonds(vinculos.map((v) => ({ value: String(v.id), label: v.nome })));
        setModalities(mods.map((m) => ({ value: String(m.id), label: m.nome })));
        if (professor) setTeacher(toTeacher(professor));
      } catch (error) {
        showError(error?.response?.data?.message || 'Não foi possível carregar o professor');
        router.push('/professores');
      } finally {
        setLoading(false);
      }
    })();
  }, [professorId]);

  // CPF encontrado em outra academia: o modal já associou
  const onProfessorEncontrado = (professor) => {
    showSuccess(`${titleCase(professor.nome)} agora é professor desta academia`);
    router.push(professor?.id ? `/professores/${professor.id}` : '/professores');
  };

  const onCriarNovo = (cpf) => {
    setBuscarCpf(false);
    if (cpf) setTeacher({ cpf });
  };

  const checkEmail = async (email) => {
    const r = await professorService.emailDisponivel(email, teacher?.userId);
    return r.disponivel ? null : { name: r.nome };
  };

  const lookupCep = async (cep) => {
    try {
      const response = await api.get(`/cep/${cep}`);
      const dados = response.data?.data;
      if (response.data?.type === 'warning' || !dados || dados.erro) return null;
      if (!dados.logradouro && !dados.bairro && !dados.cidade) return null;
      return { street: dados.logradouro, district: dados.bairro, city: dados.cidade, uf: dados.estado };
    } catch {
      return null;
    }
  };

  const save = async (data, values) => {
    setSaving(true);
    setServer({ errors: null, values: null });
    try {
      const payload = toPayload(data);
      if (isEdit) {
        await professorService.atualizar(professorId, payload);
        showSuccess('Alterações salvas');
        router.push('/professores');
        return;
      }
      const r = await professorService.criar(payload);
      const novo = r?.professor;
      if (novo?.id && !data.active) await professorService.atualizar(novo.id, { ativo: false });
      showSuccess(r?.professor_existia ? `${titleCase(data.name)} agora é professor desta academia` : `${titleCase(data.name)} cadastrado`);
      if (r?.credenciais) setCredenciais(r.credenciais);
      else router.push('/professores');
    } catch (error) {
      const d = error?.response?.data || {};
      if (d.campos) setServer({ errors: d.campos, values });
      const lista = Array.isArray(d.errors) ? d.errors : [];
      showError(lista.join('\n') || d.message || `Não foi possível ${isEdit ? 'salvar as alterações' : 'cadastrar o professor'}`);
    } finally {
      setSaving(false);
    }
  };

  const back = () => router.push('/professores');

  return (
    <LayoutBase title={isEdit ? 'Editar professor' : 'Novo professor'} pageHeader={false}>
      {!isEdit && (
        <BuscarProfessorCpfModal
          visible={buscarCpf}
          onClose={() => { setBuscarCpf(false); back(); }}
          onProfessorEncontrado={onProfessorEncontrado}
          onCriarNovoProfessor={onCriarNovo}
        />
      )}
      {loading ? (
        <p className="ck-al-stamp" role="status">Carregando professor…</p>
      ) : !buscarCpf && (
        <ProfessorForm
          key={teacher?.id ? `edit-${teacher.id}` : `novo-${teacher?.cpf || ''}`}
          teacher={teacher}
          bonds={bonds}
          modalities={modalities}
          today={today()}
          saving={saving}
          requireCpf={!isEdit}
          serverErrors={server.errors}
          serverValues={server.values}
          onCheckEmail={checkEmail}
          onCepLookup={lookupCep}
          onSeeClasses={teacher?.weeklyClasses ? () => router.push(teacher.nextClassDate ? `/turmas?data=${teacher.nextClassDate}` : '/turmas') : undefined}
          onSave={save}
          onBack={back}
          onCancel={back}
        />
      )}
      <Modal
        open={!!credenciais}
        size="sm"
        title="Acesso do professor criado"
        description={credenciais?.mensagem}
        dismissable={false}
        onClose={back}
        footer={<Button variant="primary" onClick={back}>Ir para professores</Button>}
      >
        {credenciais && (
          <dl className="ck-pf__cred">
            <dt>E-mail</dt><dd>{credenciais.email}</dd>
            <dt>Senha temporária</dt><dd><code>{credenciais.senha_temporaria}</code></dd>
          </dl>
        )}
      </Modal>
    </LayoutBase>
  );
}
