import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useRouter } from 'expo-router';
import superAdminService from '../../services/superAdminService';
import usuarioService from '../../services/usuarioService';
import { authService } from '../../services/authService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, Modal } from '../../components/ck';
import { UsuariosLista } from '../../components/ck/usuarios';
import { usuarioParaLayout } from '../../utils/usuarios';
import { showSuccess } from '../../utils/toast';

export default function UsuariosScreen() {
  const router = useRouter();
  const [usuarios, setUsuarios] = useState([]);
  const [loading, setLoading] = useState(true);
  const [erro, setErro] = useState('');
  const [isSuperAdmin, setIsSuperAdmin] = useState(false);
  const [alvo, setAlvo] = useState(null);
  const [salvando, setSalvando] = useState(false);
  const [erroStatus, setErroStatus] = useState('');
  const busy = useRef(false);
  const carregar = useCallback(async () => {
    setLoading(true); setErro('');
    try {
      const user = await authService.getCurrentUser();
      const suporte = Number(user?.papel_id) === 4;
      setIsSuperAdmin(suporte);
      const response = suporte ? await superAdminService.listarTodosUsuarios() : await usuarioService.listar();
      setUsuarios((Array.isArray(response) ? response : response.usuarios || []).map(usuarioParaLayout));
    } catch (e) { setErro(e.error || e.message || 'Não foi possível carregar os usuários.'); }
    finally { setLoading(false); }
  }, []);
  useEffect(() => { carregar(); }, [carregar]);
  const alterarStatus = async () => {
    if (busy.current || !alvo) return;
    busy.current = true; setSalvando(true); setErroStatus('');
    try {
      const r = await usuarioService.desativar(alvo.id, isSuperAdmin);
      showSuccess(r.message || `Usuário ${alvo.active ? 'desativado' : 'ativado'} com sucesso`);
      setAlvo(null); await carregar();
    } catch (e) { setErroStatus(e.error || e.message || 'Não foi possível alterar a situação.'); }
    finally { busy.current = false; setSalvando(false); }
  };
  return <LayoutBase title="Usuários" pageHeader={false}>
    {erro ? <Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar novamente</Button>}>{erro}</Callout> : null}
    <UsuariosLista users={usuarios} loading={loading} showAcademy={isSuperAdmin} onNew={() => router.push('/usuarios/novo')}
      onEdit={(u) => router.push(`/usuarios/${u.id}`)}
      onToggleActive={!loading ? (u) => { setErroStatus(''); setAlvo(u); } : undefined} />
    <Modal open={!!alvo} size="sm" title={alvo?.active ? 'Desativar usuário?' : 'Reativar usuário?'} dismissable={!salvando} onClose={() => setAlvo(null)}
      description={alvo ? `Confirme a alteração de situação de ${alvo.name}.` : ''}
      footer={<><Button variant="ghost" disabled={salvando} onClick={() => setAlvo(null)}>Cancelar</Button><Button variant={alvo?.active ? 'danger' : 'primary'} loading={salvando} onClick={alterarStatus}>{alvo?.active ? 'Desativar' : 'Reativar'}</Button></>}>
      {erroStatus ? <Callout tone="danger">{erroStatus}</Callout> : null}
    </Modal>
  </LayoutBase>;
}
