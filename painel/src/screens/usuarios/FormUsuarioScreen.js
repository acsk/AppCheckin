import React, { useEffect, useRef, useState } from 'react';
import { useRouter, useLocalSearchParams } from 'expo-router';
import usuarioService from '../../services/usuarioService';
import { authService } from '../../services/authService';
import api from '../../services/api';
import LayoutBase from '../../components/LayoutBase';
import BuscarUsuarioCpfModal from '../../components/BuscarUsuarioCpfModal';
import { Button, Callout, Modal } from '../../components/ck';
import { UsuarioForm } from '../../components/ck/usuarios';
import { usuarioParaApi, usuarioParaLayout } from '../../utils/usuarios';
import { showSuccess } from '../../utils/toast';

export default function FormUsuarioScreen() {
  const router = useRouter();
  const { id, cpf } = useLocalSearchParams();
  const usuarioId = id ? Number(id) : null;
  const isEdit = !!usuarioId;
  const [usuario, setUsuario] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [isSuperAdmin, setIsSuperAdmin] = useState(false);
  const [buscarCpf, setBuscarCpf] = useState(!isEdit);
  const [encontrado, setEncontrado] = useState(null);
  const [associar, setAssociar] = useState(false);
  const [erro, setErro] = useState('');
  const [erros, setErros] = useState({});
  const busy = useRef(false);
  useEffect(() => {
    let ativo = true;
    (async () => {
      setLoading(true); setErro('');
      try {
        const me = await authService.getCurrentUser();
        const suporte = Number(me?.papel_id) === 4;
        if (!ativo) return;
        setIsSuperAdmin(suporte);
        let u = { cpf: cpf || '', nome: '', email: '', ativo: 1, papel_id: 1 };
        if (isEdit) {
          try { u = await usuarioService.buscar(usuarioId, suporte); }
          catch (e) { if (!suporte) throw e; u = await usuarioService.buscar(usuarioId, false); }
        }
        if (ativo) setUsuario(u);
      } catch (e) { if (ativo) setErro(e.error || e.message || 'Não foi possível carregar o usuário.'); }
      finally { if (ativo) setLoading(false); }
    })();
    return () => { ativo = false; };
  }, [usuarioId]);

  const salvar = async (d) => {
    if (busy.current) return;
    busy.current = true; setSaving(true); setErro(''); setErros({});
    try {
      const payload = usuarioParaApi(d, usuario?.papel_id || 1);
      if (isEdit) await usuarioService.atualizar(usuarioId, payload, isSuperAdmin);
      else await usuarioService.criar(payload);
      showSuccess(isEdit ? 'Usuário atualizado com sucesso' : 'Usuário cadastrado com sucesso');
      router.push('/usuarios');
    } catch (e) {
      const msgs = Array.isArray(e.errors) ? e.errors : Object.values(e.errors || {}).flat();
      const campos = {};
      msgs.forEach((msg) => { const m = String(msg).toLowerCase(); if (/email|e-mail/.test(m)) campos.email = String(msg); else if (m.includes('cpf')) campos.cpf = String(msg); else if (m.includes('senha')) campos.password = String(msg); else if (m.includes('nome')) campos.name = String(msg); });
      setErros(campos); setErro(msgs.join('\n') || e.error || e.message || 'Não foi possível salvar o usuário.');
    } finally { busy.current = false; setSaving(false); }
  };
  const confirmarAssociacao = async () => {
    if (busy.current || !encontrado) return;
    busy.current = true; setSaving(true); setErro('');
    try { await api.post('/tenant/usuarios/associar', { usuario_id: encontrado.id }); showSuccess('Aluno associado com sucesso'); router.push('/usuarios'); }
    catch (e) { setErro(e.response?.data?.error || 'Não foi possível associar o aluno.'); setAssociar(false); }
    finally { busy.current = false; setSaving(false); }
  };
  const buscarCep = async (cep) => {
    const r = await api.get(`/cep/${cep}`);
    const a = r.data?.data;
    if (r.data?.type === 'warning' || !a || a.erro || (!a.logradouro && !a.bairro && !a.cidade)) return null;
    return { street: a.logradouro, district: a.bairro, city: a.cidade, uf: a.estado };
  };
  return <LayoutBase title={isEdit ? 'Editar usuário' : 'Novo usuário'} pageHeader={false}>
    {erro ? <Callout tone="danger">{erro}</Callout> : null}
    {loading ? <p role="status" className="ck-us__loading">Carregando usuário…</p> : usuario ? <UsuarioForm key={`${usuario.id || 'novo'}-${!!encontrado}`} user={usuarioParaLayout(usuario)} creating={!isEdit} associating={!!encontrado}
      requireCpf={!isEdit && !encontrado} saving={saving} serverErrors={erros} onSave={salvar} onCepLookup={buscarCep}
      onAssociate={() => setAssociar(true)} onBack={!saving ? () => router.push('/usuarios') : undefined} onCancel={!saving ? () => router.push('/usuarios') : undefined}
      notice={encontrado ? <div className="ck-us__notice"><Callout tone="info" title="Usuário já cadastrado">Este cadastro será associado como aluno nesta academia. Os dados e a senha existentes serão mantidos.</Callout></div> : null} /> : <Button onClick={() => router.push('/usuarios')}>Voltar para usuários</Button>}
    <BuscarUsuarioCpfModal visible={buscarCpf && !loading} onClose={() => setBuscarCpf(false)}
      onUsuarioEncontrado={(u) => { setEncontrado(u); setUsuario(u); setBuscarCpf(false); setErro(''); setErros({}); }}
      onCriarNovoUsuario={() => { setEncontrado(null); setBuscarCpf(false); }} />
    <Modal open={associar} size="sm" title="Confirmar associação" description={`Associar ${encontrado?.nome || ''} como aluno desta academia?`} dismissable={!saving} onClose={() => setAssociar(false)}
      footer={<><Button variant="ghost" disabled={saving} onClick={() => setAssociar(false)}>Cancelar</Button><Button variant="primary" loading={saving} onClick={confirmarAssociacao}>Associar aluno</Button></>} />
  </LayoutBase>;
}
