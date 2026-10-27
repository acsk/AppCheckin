import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useRouter } from 'expo-router';
import usuarioService from '../../services/usuarioService';
import { authService } from '../../services/authService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, Modal } from '../../components/ck';
import { UsuariosLista } from '../../components/ck/usuarios';
import { usuarioParaLayout } from '../../utils/usuarios';
import { showSuccess } from '../../utils/toast';

export default function UsuariosScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [erro, setErro] = useState('');
  const [isSuperAdmin, setIsSuperAdmin] = useState(null);
  const [versao, setVersao] = useState(0);
  const [alvo, setAlvo] = useState(null);
  const [salvando, setSalvando] = useState(false);
  const [erroStatus, setErroStatus] = useState('');
  const busy = useRef(false);
  useEffect(() => {
    authService.getCurrentUser().then((user) => setIsSuperAdmin(Number(user?.papel_id) === 4)).catch(() => setIsSuperAdmin(false));
  }, []);
  // Busca, tipo, situação e paginação no servidor (a lista pede só a página atual)
  const consultar = useCallback(async ({ q, type, status, page, size }) => {
    setErro('');
    try {
      const r = await usuarioService.listarPagina({ pagina: page, porPagina: size, busca: q, tipo: type, status }, isSuperAdmin);
      return { rows: (r.usuarios || []).map(usuarioParaLayout), total: r.total || 0, counts: r.contagens || {} };
    } catch (e) {
      setErro(e.error || e.message || 'Não foi possível carregar os usuários.');
      throw e;
    } finally {
      setLoading(false);
    }
  }, [isSuperAdmin]);
  const carregar = () => setVersao((v) => v + 1);
  const alterarStatus = async () => {
    if (busy.current || !alvo) return;
    busy.current = true; setSalvando(true); setErroStatus('');
    try {
      const r = await usuarioService.desativar(alvo.id, isSuperAdmin);
      showSuccess(r.message || `Usuário ${alvo.active ? 'desativado' : 'ativado'} com sucesso`);
      setAlvo(null); carregar();
    } catch (e) { setErroStatus(e.error || e.message || 'Não foi possível alterar a situação.'); }
    finally { busy.current = false; setSalvando(false); }
  };
  return <LayoutBase title="Usuários" pageHeader={false}>
    {erro ? <Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar novamente</Button>}>{erro}</Callout> : null}
    {isSuperAdmin === null ? null : <UsuariosLista onQuery={consultar} version={versao} loading={loading} showAcademy={isSuperAdmin} onNew={() => router.push('/usuarios/novo')}
      onEdit={(u) => router.push(`/usuarios/${u.id}`)}
      onToggleActive={(u) => { setErroStatus(''); setAlvo(u); }} />}
    <Modal open={!!alvo} size="sm" title={alvo?.active ? 'Desativar usuário?' : 'Reativar usuário?'} dismissable={!salvando} onClose={() => setAlvo(null)}
      description={alvo ? `Confirme a alteração de situação de ${alvo.name}.` : ''}
      footer={<><Button variant="ghost" disabled={salvando} onClick={() => setAlvo(null)}>Cancelar</Button><Button variant={alvo?.active ? 'danger' : 'primary'} loading={salvando} onClick={alterarStatus}>{alvo?.active ? 'Desativar' : 'Reativar'}</Button></>}>
      {erroStatus ? <Callout tone="danger">{erroStatus}</Callout> : null}
    </Modal>
  </LayoutBase>;
}
