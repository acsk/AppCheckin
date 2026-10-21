import React, { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { authService, PAPEL_COMERCIAL } from '../../services/authService';
import { showError } from '../../utils/toast';
import { Button, Icon, Modal } from '../../components/ck';
import { LoginScreen as CkLoginScreen } from '../../components/ck/pessoas';

const CREDENTIALS_KEY = '@appcheckin:login_credentials';

export default function LoginScreen() {
  const router = useRouter();

  // Em localhost, pré-preenche as credenciais de teste
  const isLocalhost = typeof window !== 'undefined'
    && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');

  const [saved, setSaved] = useState(isLocalhost ? { email: 'sa@appcheckin.com.br', senha: '123456' } : null);
  const [ready, setReady] = useState(isLocalhost);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [errorTitle, setErrorTitle] = useState(null);
  const [remember, setRemember] = useState(true);
  const [credentials, setCredentials] = useState(null);
  const [tenants, setTenants] = useState([]);
  const [user, setUser] = useState(null);
  const [selectingTenant, setSelectingTenant] = useState(null);

  useEffect(() => {
    if (isLocalhost) return;
    (async () => {
      try {
        const raw = await AsyncStorage.getItem(CREDENTIALS_KEY);
        const data = raw ? JSON.parse(raw) : null;
        if (data?.email) setSaved(data);
      } catch (err) {
        console.error('Erro ao carregar credenciais salvas:', err);
      } finally {
        setReady(true);
      }
    })();
  }, [isLocalhost]);

  const persistCredentials = async (creds, keep) => {
    if (keep) {
      await AsyncStorage.setItem(CREDENTIALS_KEY, JSON.stringify({ email: creds.email, senha: creds.senha }));
    } else {
      await AsyncStorage.removeItem(CREDENTIALS_KEY);
    }
  };

  // Quem é só da equipe comercial entra direto no módulo Prospecção.
  const goToDashboard = (user) => {
    const destino = user && authService.getEffectivePapelId(user) === PAPEL_COMERCIAL ? '/prospeccao' : '/dashboard/';
    setTimeout(() => router.replace(destino), 100);
  };

  const handleSubmit = async ({ email, password, remember: keep }) => {
    setLoading(true);
    setError(null);
    setErrorTitle(null);
    const creds = { email, senha: password };
    try {
      const response = await authService.login(email, password);

      // Administradores (3), super admins (4) e equipe comercial (5) acessam o painel
      if (!authService.podeProspeccao(response.user)) {
        await authService.logout();
        setErrorTitle('Acesso não permitido');
        setError('Este painel é exclusivo para a equipe da academia. Alunos usam o app do Check-in.');
        return;
      }

      if (response.requires_tenant_selection && response.tenants?.length > 0) {
        setUser(response.user);
        setTenants(response.tenants);
        setCredentials(creds);
        setRemember(keep);
      } else if (response.token) {
        await persistCredentials(creds, keep);
        goToDashboard(response.user);
      }
    } catch (err) {
      setError(err?.error || err?.message || 'E-mail ou senha incorretos. Confira e tente de novo, ou use "Esqueci minha senha".');
    } finally {
      setLoading(false);
    }
  };

  const handleSelectTenant = async (tenantId) => {
    setSelectingTenant(tenantId);
    try {
      const response = await authService.selectTenant(tenantId);
      if (response.token) {
        setTenants([]);
        if (credentials) await persistCredentials(credentials, remember);
        goToDashboard(response.user);
      }
    } catch (err) {
      showError(err?.error || err?.message || 'Não foi possível entrar nesta academia');
    } finally {
      setSelectingTenant(null);
    }
  };

  const handleForgot = async (email) => {
    try {
      await authService.requestPasswordRecovery(email);
    } catch (err) {
      // A API responde igual exista ou não o e-mail; só limite de tentativas e reCAPTCHA impedem o envio
      if (['RATE_LIMIT_EXCEEDED', 'RECAPTCHA_REQUIRED', 'RECAPTCHA_VALIDATION_FAILED'].includes(err?.code)) {
        showError(err.message || 'Não foi possível enviar o link agora. Tente de novo em alguns minutos.');
        throw err;
      }
    }
  };

  if (!ready) return <div className="ck-login" style={{ minHeight: '100vh' }} />;

  return (
    <>
      <CkLoginScreen
        height="100vh"
        defaultEmail={saved?.email || ''}
        defaultPassword={saved?.senha || ''}
        defaultRemember
        loading={loading}
        error={error}
        errorTitle={errorTitle}
        onSubmit={handleSubmit}
        onForgot={handleForgot}
      />
      <Modal
        open={tenants.length > 0}
        size="sm"
        title="Escolha a academia"
        description={user?.nome || user?.name ? `${user.nome || user.name} tem acesso a mais de uma academia.` : 'Seu acesso vale para mais de uma academia.'}
        dismissable={!selectingTenant}
        onClose={() => setTenants([])}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={!!selectingTenant} onClick={() => setTenants([])}>Cancelar</Button>
          </>
        )}
      >
        <ul className="ck-nm__results ck-login__tenants" role="listbox" aria-label="Academias">
          {tenants.map((t) => (
            <li key={t.tenant.id}>
              <button type="button" role="option" aria-selected={false} disabled={!!selectingTenant} onClick={() => handleSelectTenant(t.tenant.id)}>
                <span className="ck-ml__mod" aria-hidden><Icon name={selectingTenant === t.tenant.id ? 'loader' : 'building-2'} size={16} className={selectingTenant === t.tenant.id ? 'ck-spin' : undefined} /></span>
                <span>
                  <strong>{t.tenant.nome}</strong>
                  <span>{t.tenant.cnpj ? `CNPJ ${t.tenant.cnpj}` : 'Academia'}</span>
                </span>
              </button>
            </li>
          ))}
        </ul>
      </Modal>
    </>
  );
}
