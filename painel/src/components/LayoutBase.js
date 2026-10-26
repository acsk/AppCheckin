import React, { useEffect, useMemo, useState } from 'react';
import { useWindowDimensions } from 'react-native';
import { useRouter, usePathname } from 'expo-router';
import { authService, PAPEL_COMERCIAL, PAPEL_FINANCEIRO } from '../services/authService';
import { AppShell, AppHeader, Button, Icon, IconButton, PageHeader, Sidebar } from './ck';

// Menu do painel em seções (design system: Geral · Gestão · Sistema).
// Itens de primeiro nível têm ícone; filhos não. `roles` = papéis que veem o item.
const SECTIONS = [
  {
    label: 'Geral',
    items: [
      { id: 'dashboard', label: 'Dashboard', path: '/', icon: 'layout-dashboard', roles: [3, 4], perm: 'financeiro.ver' },
      { id: 'meu-plano', label: 'Plano do Check-in', path: '/meu-plano', icon: 'layers', roles: [3], perm: 'conta.gerenciar' },
      { id: 'minha-conta', label: 'Minha conta', path: '/minha-conta', icon: 'user', roles: [3, 5, 6], perm: '*' },
      { id: 'academias', label: 'Academias', path: '/academias', icon: 'building-2', roles: [4] },
      { id: 'cnpjs', label: 'CNPJs', path: '/cnpjs', icon: 'shield-check', roles: [4] },
      { id: 'unificar', label: 'Unificar cadastros', path: '/unificar-usuarios', icon: 'users', roles: [4] },
      { id: 'contratos', label: 'Contratos', path: '/contratos', icon: 'file-text', roles: [4] },
      { id: 'planos-sistema', label: 'Planos', path: '/planos-sistema', icon: 'layers', roles: [4] },
      {
        id: 'app-mobile',
        label: 'App Mobile',
        icon: 'smartphone',
        children: [
          { id: 'app-mobile-configuracoes', label: 'Configurações', path: '/app-mobile/configuracoes', roles: [4] },
          { id: 'app-mobile-cadastral', label: 'Atualização Cadastral', path: '/app-mobile/atualizacao-cadastral', roles: [4] },
          { id: 'app-mobile-versoes', label: 'Versões', path: '/app-mobile/versoes', roles: [4] },
        ],
      },
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
          { id: 'alunos', label: 'Alunos', path: '/alunos', roles: [3], perm: 'alunos.ver' },
          { id: 'matriculas', label: 'Matrículas', path: '/matriculas', roles: [3], perm: 'matriculas.ver' },
          { id: 'professores', label: 'Professores', path: '/professores', roles: [3], perm: 'aulas.gerenciar' },
          // Só o suporte Check-in (super admin, inclusive no "acessar como academia"); a academia usa Equipe/Alunos/Professores
          { id: 'usuarios', label: 'Usuários', path: '/usuarios', roles: [4], soSuporte: true },
          { id: 'modalidades', label: 'Modalidades', path: '/modalidades', roles: [3], perm: 'configuracoes.editar' },
          { id: 'planos', label: 'Planos', path: '/planos', roles: [3], perm: 'configuracoes.editar' },
          { id: 'pacotes', label: 'Pacotes', path: '/pacotes', roles: [3], perm: 'configuracoes.editar' },
        ],
      },
      {
        id: 'financeiro',
        label: 'Financeiro',
        icon: 'wallet',
        children: [
          { id: 'relatorio-financeiro', label: 'Relatório financeiro', path: '/financeiro/relatorio', roles: [3], perm: 'financeiro.ver' },
          { id: 'conciliacao-bancaria', label: 'Conciliação bancária', path: '/financeiro/conciliacao', roles: [3], perm: 'financeiro.baixar' },
          { id: 'assinaturas', label: 'Assinaturas', path: '/assinaturas', roles: [3], perm: 'financeiro.ver' },
          { id: 'formas-pagamento', label: 'Formas de pagamento', path: '/formas-pagamento', roles: [3], perm: 'configuracoes.editar' },
        ],
      },
      {
        id: 'academia',
        label: 'Academia',
        icon: 'dumbbell',
        children: [
          { id: 'wods', label: 'WOD', path: '/wods', roles: [3], perm: 'aulas.gerenciar' },
          { id: 'recordes', label: 'Recordes', path: '/recordes', roles: [3], perm: 'aulas.gerenciar' },
          { id: 'turmas', label: 'Aulas', path: '/turmas', roles: [3], perm: 'aulas.gerenciar' },
        ],
      },
      {
        // Comercial (5) só vê esta seção. Novas funcionalidades comerciais entram como filhos aqui.
        id: 'prospeccao',
        label: 'Prospecção',
        icon: 'target',
        children: [
          { id: 'prospeccao-painel', label: 'Painel de recuperação', path: '/prospeccao', roles: [3, 5], perm: 'prospeccao.usar' },
          { id: 'prospeccao-recuperacao', label: 'Recuperação de Alunos', path: '/prospeccao/recuperacao', roles: [3, 5], perm: 'prospeccao.usar' },
          { id: 'prospeccao-contatos', label: 'Contatos pendentes', path: '/prospeccao/contatos', roles: [3, 5], perm: 'prospeccao.usar' },
          { id: 'prospeccao-modelos', label: 'Modelos de mensagem', path: '/prospeccao/modelos', roles: [3, 5], perm: 'prospeccao.usar' },
          { id: 'prospeccao-config', label: 'Equipe e score', path: '/prospeccao/configuracoes', roles: [3], perm: 'prospeccao.gerenciar' },
        ],
      },
    ],
  },
  {
    label: 'Sistema',
    items: [
      { id: 'sa-pagamentos', label: 'Recebimentos', path: '/configuracoes-pagamento', icon: 'credit-card', roles: [4] },
      {
        id: 'monetizacao',
        label: 'Monetização',
        icon: 'target',
        children: [
          { id: 'monetizacao-adsense', label: 'Google AdSense', path: '/monetizacao/adsense', roles: [4] },
          { id: 'monetizacao-admob', label: 'Google AdMob', path: '/monetizacao/admob', roles: [4] },
        ],
      },
      {
        id: 'config',
        label: 'Configurações',
        icon: 'settings',
        children: [
          { id: 'equipe', label: 'Equipe e permissões', path: '/equipe', roles: [3], perm: 'equipe.gerenciar' },
          { id: 'pagamentos', label: 'Pagamentos', path: '/configuracoes-pagamento', roles: [3], perm: 'configuracoes.editar' },
          { id: 'whatsapp', label: 'WhatsApp e Instagram', path: '/grupos-whatsapp', roles: [3], perm: 'configuracoes.editar' },
        ],
      },
      {
        id: 'auditoria',
        label: 'Auditoria',
        icon: 'shield-check',
        children: [
          { id: 'logs', label: 'Logs Laravel (API v2)', path: '/superadmin/logs-laravel', roles: [3], perm: 'configuracoes.editar' },
          { id: 'credito', label: 'Crédito e migração de plano', path: '/auditoria/credito-migracao-plano', roles: [3], perm: 'configuracoes.editar' },
          { id: 'anomalias', label: 'Anomalias de datas', path: '/auditoria/anomalias-datas', roles: [3], perm: 'configuracoes.editar' },
          { id: 'duplicados', label: 'Pagamentos duplicados', path: '/auditoria/pagamentos-duplicados', roles: [3], perm: 'configuracoes.editar' },
          { id: 'reparar', label: 'Reparar próxima data', path: '/auditoria/reparar-proxima-data-vencimento', roles: [3], perm: 'configuracoes.editar' },
          { id: 'acima-limite', label: 'Check-ins acima do limite', path: '/auditoria/checkins-acima-do-limite', roles: [3], perm: 'configuracoes.editar' },
          { id: 'multiplos', label: 'Check-ins múltiplos no dia', path: '/auditoria/checkins-multiplos-no-dia', roles: [3], perm: 'configuracoes.editar' },
        ],
      },
    ],
  },
];

const BREAKPOINT_MOBILE = 768;
const COLLAPSE_KEY = 'ck:sidebar-collapsed';

const papelLabel = (papelId, user) => (papelId === 4 ? 'Super admin' : papelId === 3 ? 'Administrador'
  : authService.hasPapel(user, PAPEL_FINANCEIRO) ? 'Financeiro' : authService.hasPapel(user, PAPEL_COMERCIAL) ? 'Comercial' : 'Usuário');

// Filtra o menu e devolve as seções no formato do Sidebar.
// Super admin (fora do "acessar como academia"): itens com papel 4. Equipe da academia: itens cuja
// permissão `perm` ela tem (lista de authService.getPermissoes(); '*' = qualquer pessoa da equipe).
// A API confere a mesma permissão em cada rota (App\Support\PermissaoRotas).
function buildSections(role, perms, suporte = false) {
  const itemVisivel = (it) => (role === 4
    ? it.roles.includes(4)
    : it.soSuporte ? suporte : !!it.perm && (it.perm === '*' ? perms.length > 0 : perms.includes(it.perm)));
  return SECTIONS.map((s) => ({
    label: s.label,
    items: s.items
      .map((it) => {
        if (it.children) {
          const children = it.children.filter(itemVisivel);
          return children.length ? { ...it, children: children.map((c) => ({ ...c, href: c.path })) } : null;
        }
        return itemVisivel(it) ? { ...it, href: it.path } : null;
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
  const [tenantAtual, setTenantAtual] = useState(null);
  const [perms, setPerms] = useState(null); // null = carregando
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
    authService.getTenantAtual().then(setTenantAtual).catch(() => {});
    authService.getPermissoes().then(setPerms).catch(() => {});
  }, []);

  // Fecha o menu do celular ao trocar de rota.
  useEffect(() => {
    setDrawerOpen(false);
  }, [pathname]);

  const role = authService.getEffectivePapelId(usuarioInfo);
  const emSuporte = !!usuarioInfo?.acesso_suporte;
  const sections = useMemo(() => buildSections(role, perms || [], emSuporte), [role, perms, emSuporte]);

  // Tela fora das permissões da pessoa (ex.: comercial no dashboard): vai para a primeira tela permitida.
  // Telas fora do menu (perfil, parâmetros, detalhes) seguem a regra do item pai ou da própria API.
  useEffect(() => {
    if (!usuarioInfo || perms === null || role === 4) return;
    const caminho = (pathname || '/').startsWith('/dashboard') ? '/' : pathname || '/';
    if (['/minha-conta', '/parametros', '/login'].some((p) => caminho.startsWith(p))) return;
    if (findActive(sections, caminho)) return;
    const nos = sections.flatMap((sec) => sec.items.flatMap((it) => (it.children ? it.children : [it])));
    const primeira = nos.find((n) => n.path !== '/minha-conta') || nos[0]; // tela de trabalho antes do perfil
    if (primeira?.path && primeira.path !== caminho) router.replace(primeira.path);
  }, [usuarioInfo, perms, role, pathname, sections]);
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

  const acessoSuporte = usuarioInfo?.acesso_suporte || null;
  const [encerrando, setEncerrando] = useState(false);
  const encerrarAcesso = async () => {
    setEncerrando(true);
    const voltou = await authService.encerrarAcessoSuporte();
    const destino = voltou ? (acessoSuporte?.tenant_id ? `/academias/${acessoSuporte.tenant_id}` : '/academias') : '/login';
    if (!authService.irParaNovaSessao(destino)) router.replace(destino);
  };

  const handleLogout = async () => {
    await authService.logout();
    router.replace('/login');
  };

  const nome = usuarioInfo?.nome || 'Usuário';
  const canAccessParametros = (perms || []).includes('configuracoes.editar');

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
      actions={tenantAtual ? (
        <span className="ck-tenant-chip" title={`Academia logada: ${tenantAtual.nome}`}>
          <Icon name="building-2" size={15} />
          <span className="ck-tenant-chip__name">{tenantAtual.nome}</span>
        </span>
      ) : null}
      notifications={false}
      user={{ name: nome, role: usuarioInfo?.acesso_suporte ? 'Suporte Check-in' : papelLabel(role, usuarioInfo), email: usuarioInfo?.email }}
      menuItems={[
        ...(role === 3 || role === 4 ? [{ label: 'Minha conta', icon: 'user', onClick: () => router.push('/minha-conta') }] : []),
        ...(canAccessParametros ? [{ label: 'Parâmetros', icon: 'settings', onClick: () => router.push('/parametros') }] : []),
      ]}
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
        {acessoSuporte ? (
          <div className="ck-suporte-bar" role="status">
            <Icon name="key" size={16} />
            <span className="ck-suporte-bar__text">
              <strong>{`Acesso de suporte: ${acessoSuporte.tenant_nome}`}</strong>
              {` · até ${String(acessoSuporte.expira_em || '').slice(11, 16)}. Suas alterações ficam registradas e a academia vê este acesso.`}
            </span>
            <Button size="sm" variant="primary" loading={encerrando} onClick={encerrarAcesso}>Encerrar acesso</Button>
          </div>
        ) : null}
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
