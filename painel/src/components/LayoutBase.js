import React, { useEffect, useMemo, useState } from 'react';
import { useWindowDimensions } from 'react-native';
import { useRouter, usePathname } from 'expo-router';
import { authService } from '../services/authService';
import { AppShell, AppHeader, IconButton, PageHeader, Sidebar } from './ck';

// Menu do painel em seções (design system: Geral · Gestão · Sistema).
// Itens de primeiro nível têm ícone; filhos não. `roles` = papéis que veem o item.
const SECTIONS = [
  {
    label: 'Geral',
    items: [
      { id: 'dashboard', label: 'Dashboard', path: '/', icon: 'layout-dashboard', roles: [3, 4] },
      { id: 'academias', label: 'Academias', path: '/academias', icon: 'building-2', roles: [4] },
      { id: 'contratos', label: 'Contratos', path: '/contratos', icon: 'file-text', roles: [4] },
      { id: 'planos-sistema', label: 'Planos', path: '/planos-sistema', icon: 'layers', roles: [4] },
    ],
  },
  {
    label: 'Gestão',
    items: [
      {
        id: 'manutencao',
        label: 'Manutenção',
        icon: 'wrench',
        children: [
          { id: 'alunos', label: 'Alunos', path: '/alunos', roles: [3] },
          { id: 'matriculas', label: 'Matrículas', path: '/matriculas', roles: [3] },
          { id: 'professores', label: 'Professores', path: '/professores', roles: [3] },
          { id: 'usuarios', label: 'Usuários', path: '/usuarios', roles: [3] },
          { id: 'modalidades', label: 'Modalidades', path: '/modalidades', roles: [3] },
          { id: 'planos', label: 'Planos', path: '/planos', roles: [3] },
          { id: 'pacotes', label: 'Pacotes', path: '/pacotes', roles: [3] },
        ],
      },
      {
        id: 'financeiro',
        label: 'Financeiro',
        icon: 'wallet',
        children: [
          { id: 'relatorio-financeiro', label: 'Relatório financeiro', path: '/financeiro/relatorio', roles: [3] },
          { id: 'assinaturas', label: 'Assinaturas', path: '/assinaturas', roles: [3] },
          { id: 'vencimentos', label: 'Vencimentos', path: '/matriculas/vencimentos', roles: [3] },
          { id: 'formas-pagamento', label: 'Formas de pagamento', path: '/formas-pagamento', roles: [3] },
        ],
      },
      {
        id: 'academia',
        label: 'Academia',
        icon: 'dumbbell',
        children: [
          { id: 'wods', label: 'WOD', path: '/wods', roles: [3] },
          { id: 'recordes', label: 'Recordes', path: '/recordes', roles: [3] },
          { id: 'turmas', label: 'Aulas', path: '/turmas', roles: [3] },
        ],
      },
      {
        id: 'mercadopago',
        label: 'Mercado Pago',
        icon: 'credit-card',
        children: [
          { id: 'pagamentos-mp', label: 'Pagamentos', path: '/pagamentos-mp', roles: [3] },
        ],
      },
    ],
  },
  {
    label: 'Sistema',
    items: [
      { id: 'sa-pagamentos', label: 'Recebimentos', path: '/configuracoes-pagamento', icon: 'credit-card', roles: [4] },
      {
        id: 'config',
        label: 'Configurações',
        icon: 'settings',
        children: [
          { id: 'pagamentos', label: 'Pagamentos', path: '/configuracoes-pagamento', roles: [3] },
          { id: 'whatsapp', label: 'Grupos WhatsApp', path: '/grupos-whatsapp', roles: [3] },
        ],
      },
      {
        id: 'auditoria',
        label: 'Auditoria',
        icon: 'shield-check',
        children: [
          { id: 'logs', label: 'Logs Laravel (API v2)', path: '/superadmin/logs-laravel', roles: [3] },
          { id: 'credito', label: 'Crédito e migração de plano', path: '/auditoria/credito-migracao-plano', roles: [3] },
          { id: 'anomalias', label: 'Anomalias de datas', path: '/auditoria/anomalias-datas', roles: [3] },
          { id: 'duplicados', label: 'Pagamentos duplicados', path: '/auditoria/pagamentos-duplicados', roles: [3] },
          { id: 'reparar', label: 'Reparar próxima data', path: '/auditoria/reparar-proxima-data-vencimento', roles: [3] },
          { id: 'acima-limite', label: 'Check-ins acima do limite', path: '/auditoria/checkins-acima-do-limite', roles: [3] },
          { id: 'multiplos', label: 'Check-ins múltiplos no dia', path: '/auditoria/checkins-multiplos-no-dia', roles: [3] },
        ],
      },
    ],
  },
];

const BREAKPOINT_MOBILE = 768;
const COLLAPSE_KEY = 'ck:sidebar-collapsed';

const papelLabel = (papelId) => (papelId === 4 ? 'Super admin' : papelId === 3 ? 'Administrador' : 'Usuário');

// Filtra o menu pelo papel do usuário e devolve as seções no formato do Sidebar.
function buildSections(role) {
  return SECTIONS.map((s) => ({
    label: s.label,
    items: s.items
      .map((it) => {
        if (it.children) {
          const children = it.children.filter((c) => c.roles.includes(role));
          return children.length ? { ...it, children: children.map((c) => ({ ...c, href: c.path })) } : null;
        }
        return it.roles.includes(role) ? { ...it, href: it.path } : null;
      })
      .filter(Boolean),
  })).filter((s) => s.items.length);
}

// Item ativo = o caminho mais específico que bate com a rota atual.
function findActive(sections, pathname) {
  let best = null;
  sections.forEach((s) =>
    s.items.forEach((it) => {
      const nodes = it.children ? it.children.map((c) => ({ node: c, parent: it, section: s })) : [{ node: it, parent: null, section: s }];
      nodes.forEach((n) => {
        const p = n.node.path;
        const hit = pathname === p || (p !== '/' && pathname.startsWith(p + '/'));
        if (hit && (!best || p.length > best.node.path.length)) best = n;
      });
    }),
  );
  return best;
}

export default function LayoutBase({ children, title = 'Dashboard', subtitle = '', noPadding = false, pageHeader = true, parent = null }) {
  const router = useRouter();
  const pathname = usePathname();
  const { width } = useWindowDimensions();
  const isMobile = width < BREAKPOINT_MOBILE;

  const [usuarioInfo, setUsuarioInfo] = useState(null);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [collapsed, setCollapsed] = useState(() => {
    try {
      return typeof window !== 'undefined' && window.localStorage.getItem(COLLAPSE_KEY) === '1';
    } catch {
      return false;
    }
  });

  useEffect(() => {
    authService.getCurrentUser().then(setUsuarioInfo).catch(() => {});
  }, []);

  // Fecha o menu do celular ao trocar de rota.
  useEffect(() => {
    setDrawerOpen(false);
  }, [pathname]);

  const role = authService.getEffectivePapelId(usuarioInfo);
  const sections = useMemo(() => buildSections(role), [role]);
  const active = useMemo(() => findActive(sections, pathname || '/'), [sections, pathname]);

  const allNodes = useMemo(
    () => sections.flatMap((s) => s.items.flatMap((it) => (it.children ? it.children : [it]))),
    [sections],
  );
  const navigate = (id) => {
    const node = allNodes.find((n) => n.id === id);
    if (node?.path) router.push(node.path);
  };

  const onCollapsedChange = (v) => {
    setCollapsed(v);
    try {
      window.localStorage.setItem(COLLAPSE_KEY, v ? '1' : '0');
    } catch {
      // preferência só desta sessão
    }
  };

  const handleLogout = async () => {
    await authService.logout();
    router.replace('/login');
  };

  const nome = usuarioInfo?.nome || 'Usuário';
  const canAccessParametros = role === 3;

  // Trilha: seção/grupo → página atual.
  const breadcrumbs = [];
  if (active?.parent) breadcrumbs.push({ label: active.parent.label });
  else if (active?.section && active.node.id !== 'dashboard') breadcrumbs.push({ label: active.section.label });
  if (parent) breadcrumbs.push({ label: parent.label, href: parent.path, onClick: () => router.push(parent.path) });
  breadcrumbs.push({ label: title });

  const sidebar = (
    <Sidebar
      // Remonta quando o grupo ativo é conhecido (o menu depende do papel, carregado depois),
      // para o grupo da página atual abrir sozinho.
      key={`${role ?? 'x'}-${active?.parent?.id ?? 'root'}`}
      brand={{ name: 'Check-in', subtitle: 'Painel admin' }}
      sections={sections}
      activeId={active?.node?.id}
      onNavigate={navigate}
      collapsed={isMobile ? false : collapsed}
      onCollapsedChange={isMobile ? () => setDrawerOpen(false) : onCollapsedChange}
    />
  );

  const header = (
    <AppHeader
      leading={
        isMobile ? <IconButton icon="menu" label="Abrir menu" onClick={() => setDrawerOpen(true)} /> : null
      }
      breadcrumbs={breadcrumbs}
      search={false}
      notifications={false}
      user={{ name: nome, role: papelLabel(usuarioInfo?.papel_id), email: usuarioInfo?.email }}
      menuItems={canAccessParametros ? [{ label: 'Parâmetros', icon: 'settings', onClick: () => router.push('/parametros') }] : []}
      onSignOut={handleLogout}
    />
  );

  return (
    <div className="ck-painel" style={{ height: '100vh', width: '100%' }}>
      <AppShell
        sidebar={isMobile ? null : sidebar}
        header={header}
        className={noPadding ? 'ck-shell--flush' : undefined}
      >
        {/* Telas com cabeçalho próprio (ex.: Matricula) passam pageHeader={false} */}
        {pageHeader ? <PageHeader title={title} description={subtitle || undefined} /> : null}
        {children}
      </AppShell>

      {/* Celular: o mesmo Sidebar como gaveta sobre o conteúdo */}
      {isMobile && drawerOpen ? (
        <div className="ck-drawer" role="dialog" aria-modal="true" aria-label="Menu principal">
          <button type="button" className="ck-drawer__scrim" aria-label="Fechar menu" onClick={() => setDrawerOpen(false)} />
          <div className="ck-drawer__panel">{sidebar}</div>
        </div>
      ) : null}
    </div>
  );
}
