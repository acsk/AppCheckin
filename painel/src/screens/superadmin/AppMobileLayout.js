import React, { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { PageHeader } from '../../components/ck';
import { authService } from '../../services/authService';
import { showError } from '../../utils/toast';

// As três páginas do App Mobile compartilham a mesma restrição de acesso.
export default function AppMobileLayout({ title, description, children, pageHeader = true }) {
  const router = useRouter();
  const [autorizado, setAutorizado] = useState(false);

  useEffect(() => {
    let ativo = true;
    authService.getCurrentUser().then((usuario) => {
      if (!ativo) return;
      if (authService.getEffectivePapelId(usuario) !== 4) {
        showError('Acesso negado. Apenas Super Admin pode acessar esta página.');
        router.replace('/');
        return;
      }
      setAutorizado(true);
    }).catch(() => {
      if (ativo) router.replace('/login');
    });
    return () => { ativo = false; };
  }, [router]);

  return (
    <LayoutBase title={title} pageHeader={false}>
      {autorizado ? (
        <>
          {pageHeader ? <PageHeader title={title} description={description} /> : null}
          {children}
        </>
      ) : <p className="ck-mc-hint" role="status">Carregando…</p>}
    </LayoutBase>
  );
}
