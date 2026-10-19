import React, { useEffect, useRef, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import alunoService from '../../services/alunoService';
import { authService } from '../../services/authService';
import api from '../../services/api';
import LayoutBase from '../../components/LayoutBase';
import { AlunoForm, titleCase } from '../../components/ck/pessoas';
import { showSuccess, showError } from '../../utils/toast';

const bool = (v) => v === true || v === 1 || v === '1';
const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const toStudent = (a) => ({
  id: a.id,
  userId: a.usuario_id,
  name: a.nome || '',
  email: a.email || '',
  phone: a.telefone || '',
  cpf: a.cpf || '',
  birthDate: a.data_nascimento ? String(a.data_nascimento).slice(0, 10) : null,
  gender: a.sexo || '',
  status: bool(a.ativo) ? 'ativo' : 'inativo',
  address: { cep: a.cep, street: a.logradouro, number: a.numero, complement: a.complemento, district: a.bairro, city: a.cidade, uf: a.estado },
});

export default function FormAlunoScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const alunoId = id ? parseInt(id, 10) : null;
  const isEdit = !!alunoId;

  const [student, setStudent] = useState(null);
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  // Último cadastro encontrado pelo CPF: o vínculo usa o id do aluno, não o do usuário
  const found = useRef(null);

  useEffect(() => {
    (async () => {
      try {
        const user = await authService.getCurrentUser();
        if (!user || ![3, 4].includes(user.papel_id)) {
          showError('Acesso restrito aos administradores');
          router.replace('/');
          return;
        }
      } catch {
        router.replace('/');
        return;
      }
      if (!isEdit) return;
      try {
        const response = await alunoService.buscar(alunoId);
        setStudent(toStudent(response.aluno || response));
      } catch (error) {
        showError(error?.message || error?.error || 'Não foi possível carregar os dados do aluno');
        router.back();
      } finally {
        setLoading(false);
      }
    })();
  }, [alunoId]);

  const lookupCpf = async (cpf) => {
    const r = await alunoService.buscarPorCpf(cpf);
    if (!r?.found || !r.aluno) {
      found.current = null;
      return null;
    }
    found.current = r.aluno;
    const outras = (r.tenants || []).map((t) => t.nome).filter(Boolean);
    return {
      id: r.aluno.id,
      userId: r.aluno.usuario_id,
      name: r.aluno.nome,
      email: r.aluno.email,
      phone: r.aluno.telefone,
      cpf: r.aluno.cpf,
      origin: outras.length ? outras.join(', ') : undefined,
      alreadyHere: !!r.ja_associado,
    };
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

  const save = async (data) => {
    setSaving(true);
    try {
      if (data.linkedUserId && found.current) {
        await alunoService.associar(found.current.id);
        showSuccess(`${titleCase(found.current.nome)} agora é aluno desta academia`);
        router.push(`/alunos/${found.current.id}`);
        return;
      }

      const payload = {
        nome: data.name,
        email: data.email,
        telefone: data.phone || '',
        cpf: data.cpf || '',
        data_nascimento: data.birthDate || null,
        cep: data.address.cep || '',
        logradouro: data.address.street || '',
        numero: data.address.number || '',
        complemento: data.address.complement || '',
        bairro: data.address.district || '',
        cidade: data.address.city || '',
        estado: data.address.uf || '',
        ativo: data.status === 'ativo' ? 1 : 0,
      };
      if (data.gender) payload.sexo = data.gender;
      if (data.password) payload.senha = data.password;

      if (isEdit) {
        await alunoService.atualizar(alunoId, payload);
        showSuccess('Alterações salvas');
        router.push(`/alunos/${alunoId}`);
      } else {
        const response = await alunoService.criar(payload);
        const novoId = response?.aluno?.id || response?.id;
        showSuccess(`${titleCase(data.name)} cadastrado`);
        router.push(novoId ? `/alunos/${novoId}` : '/alunos');
      }
    } catch (error) {
      const apiErrors = Array.isArray(error?.errors) ? error.errors : [];
      showError(apiErrors.join('\n') || error?.message || error?.error || `Não foi possível ${isEdit ? 'salvar as alterações' : 'cadastrar o aluno'}`);
    } finally {
      setSaving(false);
    }
  };

  const back = () => router.push(isEdit ? `/alunos/${alunoId}` : '/alunos');

  return (
    <LayoutBase title={isEdit ? 'Editar aluno' : 'Novo aluno'} pageHeader={false}>
      {loading ? (
        <p className="ck-al-stamp" role="status">Carregando aluno…</p>
      ) : (
        <AlunoForm
          key={student ? `edit-${student.id}` : 'novo'}
          mode={isEdit ? 'editar' : 'novo'}
          student={student}
          today={today()}
          saving={saving}
          requireCpf
          onLookupCpf={lookupCpf}
          onCepLookup={lookupCep}
          onOpenExisting={(s) => router.push(`/alunos/${s.id}`)}
          onSave={save}
          onBack={back}
          onCancel={back}
        />
      )}
    </LayoutBase>
  );
}
