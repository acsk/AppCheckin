import React, { useEffect, useMemo, useState } from 'react';
import LayoutBase from '../../components/LayoutBase';
import {
  Avatar, Badge, Button, Callout, Icon, IconButton, Modal, PageHeader, ProviderLogo, Tabs,
} from '../../components/ck';
import { KpiCard, money } from '../../components/ck/financeiro';
import { LOGO_ASAAS, LOGO_MERCADO_PAGO } from '../../components/ck/providerLogos';
import assinaturaService from '../../services/assinaturaService';
import { authService } from '../../services/authService';
import { matriculaService } from '../../services/matriculaService';
import { superAdminService } from '../../services/superAdminService';
import { showError } from '../../utils/toast';

const PER_PAGE = 20;
const STATUS_CHIPS = [
  ['', 'Todas', null],
  ['ativa', 'Ativas', 'success'],
  ['pendente', 'Pendentes', 'warning'],
  ['paga', 'Pagas', 'success'],
  ['pausada', 'Pausadas', 'outline'],
  ['cancelada', 'Canceladas', 'danger'],
  ['expirada', 'Expiradas', 'outline'],
];
const GATEWAYS = [
  { value: '', label: 'Todos' },
  { value: 'asaas', label: 'ASAAS' },
  { value: 'mercadopago', label: 'Mercado Pago' },
];
const TIPOS = [
  { value: '', label: 'Todos' },
  { value: 'recorrente', label: 'Recorrente' },
  { value: 'avulso', label: 'Avulso' },
];

const titleCase = (n) => String(n || '').toLowerCase()
  .replace(/(^|\s)(\p{L})/gu, (m, s, c) => s + c.toUpperCase())
  .replace(/\s(Da|De|Do|Das|Dos|E)\s/g, (m) => m.toLowerCase());

const formatDate = (date) => {
  if (!date) return '—';
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(date));
  if (match) return `${match[3]}/${match[2]}/${match[1]}`;
  const parsed = new Date(date);
  return Number.isNaN(parsed.getTime()) ? '—' : parsed.toLocaleDateString('pt-BR');
};

const formatDateTime = (date) => {
  if (!date) return '—';
  const match = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(String(date));
  if (match) {
    const [, y, m, d, hh, mm] = match;
    if (!hh || (hh === '00' && mm === '00')) return `${d}/${m}/${y}`;
    return `${d}/${m}/${y} às ${hh}:${mm}`;
  }
  const parsed = new Date(date);
  if (Number.isNaN(parsed.getTime())) return '—';
  return parsed.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const codigoStatus = (assinatura) => (
  assinatura?.status?.codigo || assinatura?.status_codigo || ''
).toLowerCase();

const nomeStatus = (assinatura) => assinatura?.status?.nome
  || { ativa: 'Ativa', pendente: 'Pendente', paga: 'Paga', pausada: 'Pausada', cancelada: 'Cancelada', expirada: 'Expirada' }[codigoStatus(assinatura)]
  || codigoStatus(assinatura)
  || '—';

const toneStatus = (codigo) => ({
  ativa: 'success',
  paga: 'success',
  pendente: 'warning',
  pausada: 'warning',
  cancelada: 'danger',
  expirada: 'neutral',
}[codigo] || 'neutral');

const codigoGateway = (assinatura) => {
  const raw = String(assinatura?.gateway_codigo || assinatura?.gateway?.codigo || '').toLowerCase();
  const pref = String(assinatura?.preference_id || assinatura?.provedor_id || '');
  if (raw.includes('asaas') || pref.startsWith('pay_')) return 'asaas';
  if (raw.includes('mercado')) return 'mercadopago';
  const nome = String(assinatura?.gateway?.nome || assinatura?.gateway || '').toLowerCase();
  if (nome.includes('asaas')) return 'asaas';
  if (nome.includes('mercado')) return 'mercadopago';
  return raw || 'mercadopago';
};

const logoGateway = (codigo) => (codigo === 'asaas' ? LOGO_ASAAS : LOGO_MERCADO_PAGO);
const nomeGateway = (assinatura) => {
  const codigo = codigoGateway(assinatura);
  if (assinatura?.gateway?.nome) return assinatura.gateway.nome;
  if (typeof assinatura?.gateway === 'string' && assinatura.gateway.trim()) return assinatura.gateway;
  return codigo === 'asaas' ? 'Asaas' : 'Mercado Pago';
};

const planoSub = (assinatura) => {
  const parts = [assinatura?.modalidade_nome || assinatura?.plano_nome].filter(Boolean);
  const n = Number(assinatura?.plano_checkins);
  if (n > 0) parts.push(`${n}x por semana`);
  return parts.join(', ') || '—';
};

const periodoLinha = (assinatura) => {
  const inicio = formatDate(assinatura?.data_inicio);
  const fim = assinatura?.data_fim ? formatDate(assinatura.data_fim) : null;
  const proxima = assinatura?.proxima_cobranca ? formatDate(assinatura.proxima_cobranca) : null;
  if (assinatura?.tipo_cobranca === 'recorrente') {
    return { principal: inicio, extra: proxima ? `Próxima cobrança em ${proxima}` : 'Sem próxima cobrança' };
  }
  if (fim) return { principal: `${inicio} a ${fim}`, extra: null };
  return { principal: inicio, extra: 'Sem data de fim' };
};

const downloadCsv = (filename, rows) => {
  const csv = rows.map((r) => r.map((v) => {
    const s = String(v ?? '');
    return /[";\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  }).join(';')).join('\n');
  const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
};

const relativo = (iso) => {
  if (!iso) return 'agora';
  const t = new Date(iso).getTime();
  if (Number.isNaN(t)) return 'agora';
  const min = Math.max(0, Math.round((Date.now() - t) / 60000));
  if (min < 1) return 'há instantes';
  if (min === 1) return 'há 1 minuto';
  if (min < 60) return `há ${min} minutos`;
  const h = Math.round(min / 60);
  return h === 1 ? 'há 1 hora' : `há ${h} horas`;
};

export default function AssinaturasScreen() {
  const [assinaturas, setAssinaturas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [isSuperAdmin, setIsSuperAdmin] = useState(false);
  const [tenants, setTenants] = useState([]);
  const [selectedTenant, setSelectedTenant] = useState(null);
  const [tenantQuery, setTenantQuery] = useState('');

  const [statusFilter, setStatusFilter] = useState('');
  const [tipoCobrancaFilter, setTipoCobrancaFilter] = useState('');
  const [gatewayFilter, setGatewayFilter] = useState('');
  const [searchText, setSearchText] = useState('');
  const [serverSearch, setServerSearch] = useState('');

  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [contagens, setContagens] = useState({ todas: 0 });
  const [resumo, setResumo] = useState(null);

  const [showCobrancasModal, setShowCobrancasModal] = useState(false);
  const [cobrancasAssinatura, setCobrancasAssinatura] = useState(null);
  const [cobrancas, setCobrancas] = useState(null);
  const [loadingCobrancas, setLoadingCobrancas] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [tab, setTab] = useState('conciliacao');

  useEffect(() => {
    authService.getCurrentUser().then(async (user) => {
      const superAdmin = user?.papel_id === 4;
      setIsSuperAdmin(superAdmin);
      if (!superAdmin) return;
      try {
        const response = await superAdminService.listarAcademias();
        const lista = response?.academias || response?.data?.academias || response || [];
        setTenants(Array.isArray(lista) ? lista : []);
        if (lista[0]) setSelectedTenant(lista[0]);
      } catch (error) {
        showError(error.error || 'Erro ao carregar academias');
      }
    }).catch(() => {});
  }, []);

  useEffect(() => {
    const t = setTimeout(() => setServerSearch(searchText.trim()), 300);
    return () => clearTimeout(t);
  }, [searchText]);

  const loadAssinaturas = async (pageParam = 1) => {
    try {
      setLoading(true);
      const filtros = {
        status: statusFilter || undefined,
        tipo_cobranca: tipoCobrancaFilter || undefined,
        gateway: gatewayFilter || undefined,
        busca: serverSearch || undefined,
        page: pageParam,
        per_page: PER_PAGE,
      };
      const response = isSuperAdmin && selectedTenant
        ? await assinaturaService.listarTodas(selectedTenant.id, filtros)
        : await assinaturaService.listar(filtros);
      const lista = response?.assinaturas || response?.data?.assinaturas || [];
      setAssinaturas(Array.isArray(lista) ? lista : []);
      setTotal(response?.total || response?.data?.total || 0);
      setTotalPages(response?.total_pages || response?.data?.total_pages || 1);
      setPage(response?.page || pageParam);
      setContagens(response?.contagens || response?.data?.contagens || { todas: 0 });
      setResumo(response?.resumo || response?.data?.resumo || null);
    } catch (error) {
      showError(error.error || 'Erro ao carregar assinaturas');
      setAssinaturas([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (isSuperAdmin && !selectedTenant) return undefined;
    loadAssinaturas(1);
    return undefined;
  }, [statusFilter, tipoCobrancaFilter, gatewayFilter, serverSearch, selectedTenant, isSuperAdmin]);

  const fetchCobrancas = async (assinatura) => {
    if (!assinatura?.id) return;
    try {
      setLoadingCobrancas(true);
      setCobrancasAssinatura(assinatura);
      setShowCobrancasModal(true);
      setTab('conciliacao');
      const tenantId = isSuperAdmin ? selectedTenant?.id : null;
      const response = await assinaturaService.consultarCobrancas(assinatura.id, tenantId);
      setCobrancas(response);
    } catch (error) {
      showError(error.error || 'Erro ao consultar cobranças');
      setCobrancas(null);
    } finally {
      setLoadingCobrancas(false);
    }
  };

  const sincronizar = async () => {
    const matriculaId = cobrancasAssinatura?.matricula_id;
    if (!matriculaId) {
      showError('Esta assinatura não tem matrícula para sincronizar.');
      return;
    }
    try {
      setSyncing(true);
      if (codigoGateway(cobrancasAssinatura) === 'asaas') {
        await matriculaService.sincronizarAsaas(matriculaId);
      }
      await fetchCobrancas(cobrancasAssinatura);
    } catch (error) {
      showError(error.error || 'Não foi possível sincronizar agora.');
    } finally {
      setSyncing(false);
    }
  };

  const exportar = async () => {
    try {
      const filtros = {
        status: statusFilter || undefined,
        tipo_cobranca: tipoCobrancaFilter || undefined,
        gateway: gatewayFilter || undefined,
        busca: serverSearch || undefined,
        page: 1,
        per_page: 500,
      };
      const response = isSuperAdmin && selectedTenant
        ? await assinaturaService.listarTodas(selectedTenant.id, filtros)
        : await assinaturaService.listar(filtros);
      const lista = response?.assinaturas || [];
      downloadCsv('assinaturas.csv', [
        ['Aluno', 'Plano', 'Provedor', 'Tipo', 'Valor', 'Início', 'Fim', 'Próxima cobrança', 'Status'],
        ...lista.map((a) => [
          titleCase(a.aluno_nome),
          a.plano_nome,
          nomeGateway(a),
          a.tipo_cobranca,
          (Number(a.valor) || 0).toFixed(2).replace('.', ','),
          formatDate(a.data_inicio),
          formatDate(a.data_fim),
          formatDate(a.proxima_cobranca),
          nomeStatus(a),
        ]),
      ]);
    } catch (error) {
      showError(error.error || 'Não foi possível exportar.');
    }
  };

  const tenantResults = tenantQuery
    ? tenants.filter((t) => String(t.nome || '').toLowerCase().includes(tenantQuery.toLowerCase())).slice(0, 8)
    : [];

  const from = total === 0 ? 0 : ((page - 1) * PER_PAGE) + 1;
  const to = Math.min(page * PER_PAGE, total);
  const problemas = resumo?.problemas || { total: 0, webhooks_erro: 0, valores_divergentes: 0 };
  const problemaHint = [
    problemas.webhooks_erro ? `${problemas.webhooks_erro} webhook${problemas.webhooks_erro === 1 ? '' : 's'} com erro` : null,
    problemas.valores_divergentes ? `${problemas.valores_divergentes} valor divergente` : null,
  ].filter(Boolean).join(', ') || 'Nenhum problema detectado';

  const cobrancasProvedor = useMemo(() => {
    const lista = cobrancas?.provedor?.pagamentos || cobrancas?.mercadopago?.pagamentos;
    return Array.isArray(lista) ? lista : [];
  }, [cobrancas]);
  const pagamentosPlano = cobrancas?.local?.pagamentos_plano || [];
  const webhookPayloads = cobrancas?.local?.webhook_payloads || cobrancas?.local?.webhook_payloads_mercadopago || [];
  const conciliacao = cobrancas?.conciliacao;
  const gwModal = codigoGateway(cobrancasAssinatura || {});
  const invoiceUrl = cobrancas?.provedor?.invoice_url || cobrancasAssinatura?.payment_url;

  const formatLinha = (linha) => {
    if (linha.campo === 'valor') {
      return {
        provedor: linha.provedor == null ? '—' : money(linha.provedor),
        checkin: linha.checkin == null ? '—' : money(linha.checkin),
      };
    }
    if (linha.campo === 'vencimento') {
      return { provedor: formatDate(linha.provedor), checkin: formatDate(linha.checkin) };
    }
    return { provedor: linha.provedor || '—', checkin: linha.checkin || '—' };
  };

  return (
    <LayoutBase title="Assinaturas" pageHeader={false}>
      <div className="ck-fin ck-as">
        <PageHeader
          title="Assinaturas"
          description="Cobranças de planos geradas nos provedores de pagamento e na academia."
          actions={<Button icon="download" disabled={loading || !total} onClick={exportar}>Exportar</Button>}
        />

        {isSuperAdmin ? (
          <section className="ck-card ck-pl__tenant" aria-label="Academia">
            {selectedTenant ? (
              <div className="ck-nm__student">
                <span className="ck-ml__mod" aria-hidden><Icon name="building-2" size={16} /></span>
                <span style={{ flex: 1, minWidth: 0 }}>
                  <strong>{selectedTenant.nome}</strong>
                  <span>{[selectedTenant.cidade, selectedTenant.estado].filter(Boolean).join('/') || 'Academia selecionada'}</span>
                </span>
                <Button size="sm" variant="ghost" icon="repeat" onClick={() => { setSelectedTenant(null); setAssinaturas([]); }}>Trocar</Button>
              </div>
            ) : (
              <div className="ck-nm__combo">
                <div className="ck-control">
                  <Icon name="search" size={18} className="ck-control__icon" />
                  <input className="ck-control__input" placeholder="Buscar academia pelo nome" value={tenantQuery} aria-label="Buscar academia" onChange={(e) => setTenantQuery(e.target.value)} />
                  {tenantQuery ? <IconButton icon="x" label="Limpar busca" size="sm" onClick={() => setTenantQuery('')} /> : null}
                </div>
                {tenantQuery ? (
                  <ul className="ck-nm__results" role="listbox">
                    {tenantResults.length ? tenantResults.map((t) => (
                      <li key={t.id}>
                        <button type="button" role="option" onClick={() => { setSelectedTenant(t); setTenantQuery(''); }}>
                          <span className="ck-ml__mod" aria-hidden><Icon name="building-2" size={16} /></span>
                          <span><strong>{t.nome}</strong><span>{[t.cidade, t.estado].filter(Boolean).join('/')}</span></span>
                        </button>
                      </li>
                    )) : <li className="ck-nm__noresult">Nenhuma academia com esse nome.</li>}
                  </ul>
                ) : null}
              </div>
            )}
          </section>
        ) : null}

        <div className="ck-kpis">
          <KpiCard
            icon="repeat"
            label="Recorrentes ativas"
            value={loading ? '—' : String(resumo?.recorrentes_ativas?.total ?? 0)}
            hint={`${money(resumo?.recorrentes_ativas?.valor || 0)} por mês`}
            onClick={() => { setTipoCobrancaFilter('recorrente'); setStatusFilter('ativa'); }}
          />
          <KpiCard
            tone="success"
            icon="check-circle"
            label="Pagas este mês"
            value={loading ? '—' : money(resumo?.pagas_este_mes?.valor || 0)}
            hint={`${resumo?.pagas_este_mes?.total ?? 0} cobranças`}
            onClick={() => setStatusFilter('paga')}
          />
          <KpiCard
            tone="warning"
            icon="clock"
            label="Pendentes"
            value={loading ? '—' : money(resumo?.pendentes?.valor || 0)}
            hint={`${resumo?.pendentes?.total ?? 0} aguardando pagamento`}
            onClick={() => setStatusFilter('pendente')}
          />
          <KpiCard
            tone={problemas.total ? 'danger' : 'neutral'}
            icon="alert-triangle"
            label="Com problema"
            value={loading ? '—' : String(problemas.total ?? 0)}
            hint={problemaHint}
          />
        </div>

        <section className="ck-card ck-ml__panel" aria-label="Assinaturas">
          <div className="ck-ml__toolbar ck-as__toolbar">
            <div className="ck-control ck-ml__search">
              <Icon name="search" size={18} className="ck-control__icon" />
              <input
                type="search"
                className="ck-control__input"
                value={searchText}
                placeholder="Buscar aluno ou plano"
                aria-label="Buscar aluno ou plano"
                onChange={(e) => setSearchText(e.target.value)}
              />
              {searchText ? <IconButton icon="x" label="Limpar busca" size="sm" onClick={() => setSearchText('')} /> : null}
            </div>
            <div className="ck-as__segs" role="group" aria-label="Provedor">
              {GATEWAYS.map((g) => (
                <button key={g.value || 'gw'} type="button" className={`ck-as__seg${gatewayFilter === g.value ? ' is-on' : ''}`} aria-pressed={gatewayFilter === g.value} onClick={() => setGatewayFilter(g.value)}>
                  {g.value === 'asaas' ? <ProviderLogo src={LOGO_ASAAS} name="Asaas" size="sm" /> : null}
                  {g.value === 'mercadopago' ? <ProviderLogo src={LOGO_MERCADO_PAGO} name="Mercado Pago" size="sm" /> : null}
                  {g.label}
                </button>
              ))}
            </div>
            <div className="ck-as__segs" role="group" aria-label="Tipo">
              {TIPOS.map((t) => (
                <button key={t.value || 'tipo'} type="button" className={`ck-as__seg${tipoCobrancaFilter === t.value ? ' is-on' : ''}`} aria-pressed={tipoCobrancaFilter === t.value} onClick={() => setTipoCobrancaFilter(t.value)}>
                  {t.value === 'recorrente' ? <Icon name="repeat" size={14} /> : null}
                  {t.value === 'avulso' ? <Icon name="receipt" size={14} /> : null}
                  {t.label}
                </button>
              ))}
            </div>
          </div>

          <div className="ck-ml__filters ck-as__status" role="radiogroup" aria-label="Filtrar por status">
            {STATUS_CHIPS.map(([key, label, tone]) => (
              <button key={key || 'todas'} type="button" role="radio" aria-checked={statusFilter === key} className="ck-ml__chip" onClick={() => setStatusFilter(key)}>
                {tone ? <span className={`ck-ml__chipdot ck-ml__chipdot--${tone}`} aria-hidden /> : null}
                {label}<span className="ck-ml__count">{contagens[key || 'todas'] ?? 0}</span>
              </button>
            ))}
          </div>

          {loading ? <p role="status" className="ck-enrollment-state">Carregando assinaturas…</p>
            : assinaturas.length ? (
              <div className="ck-mt-table-wrap">
                <table className="ck-mt-table ck-ml__table">
                  <thead>
                    <tr>
                      <th scope="col">Aluno</th>
                      <th scope="col">Provedor</th>
                      <th scope="col">Tipo</th>
                      <th scope="col" className="is-num">Valor</th>
                      <th scope="col">Período</th>
                      <th scope="col">Status</th>
                      <th scope="col"><span className="ck-sr">Cobranças</span></th>
                    </tr>
                  </thead>
                  <tbody>
                    {assinaturas.map((assinatura) => {
                      const st = codigoStatus(assinatura);
                      const gw = codigoGateway(assinatura);
                      const periodo = periodoLinha(assinatura);
                      const avulso = assinatura.tipo_cobranca === 'avulso';
                      return (
                        <tr key={assinatura.id} className="ck-ml__row" onClick={(e) => { if (!e.target.closest('button, a')) fetchCobrancas(assinatura); }}>
                          <td>
                            <div className="ck-ml__who">
                              <Avatar name={assinatura.aluno_nome} size="sm" decorative />
                              <span>
                                <button type="button" className="ck-ml__name" onClick={() => fetchCobrancas(assinatura)}>{titleCase(assinatura.aluno_nome)}</button>
                                <span className="ck-as__sub">{planoSub(assinatura)}</span>
                              </span>
                            </div>
                          </td>
                          <td>
                            <span className="ck-as__prov">
                              <ProviderLogo src={logoGateway(gw)} name={nomeGateway(assinatura)} size="sm" />
                              <span>{gw === 'asaas' ? 'ASAAS' : 'Mercado Pago'}</span>
                            </span>
                          </td>
                          <td>
                            <Badge icon={avulso ? 'receipt' : 'repeat'}>{avulso ? 'Avulso' : 'Recorrente'}</Badge>
                          </td>
                          <td className="is-num">
                            <strong>{money(assinatura.valor)}</strong>
                            <span className="ck-as__sub">{avulso ? 'cobrança única' : 'por ciclo'}</span>
                          </td>
                          <td>
                            <strong>{periodo.principal}</strong>
                            {periodo.extra ? <span className="ck-as__sub">{periodo.extra}</span> : null}
                          </td>
                          <td><Badge tone={toneStatus(st)} dot>{nomeStatus(assinatura)}</Badge></td>
                          <td>
                            <Button size="sm" icon="receipt" onClick={() => fetchCobrancas(assinatura)}>Cobranças</Button>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="ck-ml__empty">
                <strong>Nenhuma assinatura encontrada</strong>
                <p>Ajuste os filtros ou a busca e tente novamente.</p>
              </div>
            )}

          {!loading && total ? (
            <div className="ck-ml__foot">
              <span>Mostrando {from} a {to} de {total}</span>
              <div className="ck-ml__pager">
                <IconButton icon="chevron-left" label="Página anterior" size="sm" variant="secondary" disabled={page <= 1} onClick={() => loadAssinaturas(page - 1)} />
                <span className="ck-ml__pageinfo">Página {page} de {totalPages}</span>
                <IconButton icon="chevron-right" label="Próxima página" size="sm" variant="secondary" disabled={page >= totalPages} onClick={() => loadAssinaturas(page + 1)} />
              </div>
            </div>
          ) : null}
        </section>
      </div>

      <Modal
        open={showCobrancasModal}
        size="lg"
        title={cobrancasAssinatura ? `Cobranças de ${titleCase(cobrancasAssinatura.aluno_nome)}` : 'Cobranças'}
        description={cobrancasAssinatura ? `${planoSub(cobrancasAssinatura)}${cobrancasAssinatura.matricula_id ? `, matrícula #${cobrancasAssinatura.matricula_id}` : ''}` : undefined}
        leading={cobrancasAssinatura ? <ProviderLogo src={logoGateway(gwModal)} name={nomeGateway(cobrancasAssinatura)} size="sm" /> : null}
        onClose={() => setShowCobrancasModal(false)}
        footer={(
          <>
            <span className="ck-as-modal__sync"><Icon name="refresh-cw" size={14} /> Sincronizado {relativo(cobrancas?.sincronizado_em)}</span>
            <span style={{ flex: 1 }} />
            {invoiceUrl ? (
              <Button variant="ghost" icon="arrow-right" onClick={() => window.open(invoiceUrl, '_blank', 'noopener')}>
                Abrir no {gwModal === 'asaas' ? 'Asaas' : 'Mercado Pago'}
              </Button>
            ) : null}
            <Button icon="refresh-cw" loading={syncing} onClick={sincronizar}>Sincronizar agora</Button>
          </>
        )}
      >
        <div className="ck-as-modal">
          <Tabs
            label="Detalhe da cobrança"
            value={tab}
            onChange={setTab}
            tabs={[
              { value: 'conciliacao', label: 'Conciliação', status: conciliacao?.ok ? 'success' : (conciliacao ? 'warning' : undefined) },
              { value: 'cobrancas', label: `Cobranças ${cobrancasProvedor.length}` },
              { value: 'webhooks', label: `Webhooks ${webhookPayloads.length}` },
              { value: 'tecnico', label: 'Dados técnicos' },
            ]}
          />

          {loadingCobrancas ? <p role="status">Carregando cobranças…</p> : null}

          {!loadingCobrancas && tab === 'conciliacao' ? (
            <>
              {conciliacao ? (
                <Callout tone={conciliacao.ok ? 'success' : 'warning'} title={conciliacao.titulo}>{conciliacao.detalhe}</Callout>
              ) : (
                <Callout tone="neutral" title="Sem conciliação">Ainda não há cobrança no provedor e parcela local para comparar.</Callout>
              )}
              {conciliacao ? (
                <table className="ck-as-cmp">
                  <thead>
                    <tr>
                      <th />
                      <th>
                        <span className="ck-as-cmp__head">
                          <span className="ck-as-mark ck-as-mark--gw">
                            <img src={logoGateway(gwModal)} alt="" />
                          </span>
                          No {conciliacao.provedor_nome}
                        </span>
                      </th>
                      <th>
                        <span className="ck-as-cmp__head">
                          <span className="ck-as-mark ck-as-mark--ck" aria-hidden><Icon name="activity" size={13} strokeWidth={2.4} /></span>
                          No Check-in
                        </span>
                      </th>
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {(conciliacao.linhas || []).map((linha) => {
                      const v = formatLinha(linha);
                      return (
                        <tr key={linha.campo}>
                          <th scope="row">{linha.label}</th>
                          <td>
                            {v.provedor}
                            {linha.provedor_detalhe ? <span className="ck-as__muted"> ({linha.provedor_detalhe})</span> : null}
                            {linha.campo === 'situacao' && linha.provedor_codigo ? <Badge className="ck-as-cmp__code">{linha.provedor_codigo}</Badge> : null}
                          </td>
                          <td>{v.checkin}</td>
                          <td>{linha.ok ? <Icon name="check-circle" size={18} className="ck-as-cmp__ok" /> : <Icon name="alert-triangle" size={18} className="ck-as-cmp__warn" />}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              ) : null}
              {conciliacao?.parcela ? (
                <div className="ck-as-facts">
                  <div>
                    <span>Parcela no Check-in</span>
                    <strong>Nº {conciliacao.parcela.id}</strong>
                  </div>
                  <div>
                    <span>Baixa</span>
                    <strong>{conciliacao.parcela.baixa}</strong>
                  </div>
                  <div>
                    <span>Pago em</span>
                    <strong>{formatDateTime(conciliacao.parcela.pago_em)}</strong>
                  </div>
                </div>
              ) : null}
            </>
          ) : null}

          {!loadingCobrancas && tab === 'cobrancas' ? (
            cobrancasProvedor.length === 0 ? <p className="ck-as__muted">Nenhuma cobrança no provedor.</p> : (
              <ul className="ck-as-list">
                {cobrancasProvedor.map((pagamento) => (
                  <li key={pagamento.id}>
                    <strong>
                      {money(pagamento.transaction_amount)}
                      {pagamento.installment_count > 1 ? <span className="ck-as__muted"> · Parcela {pagamento.installment_number} de {pagamento.installment_count}</span> : null}
                    </strong>
                    <span>
                      {pagamento.status} · {pagamento.payment_method_id || '—'}
                      {pagamento.due_date ? ` · vence em ${formatDate(pagamento.due_date)}` : ''}
                      {pagamento.date_approved ? ` · pago em ${formatDate(pagamento.date_approved)}` : ` · criada em ${formatDate(pagamento.date_created)}`}
                    </span>
                    <span className="ck-as__muted">{pagamento.id}</span>
                  </li>
                ))}
              </ul>
            )
          ) : null}

          {!loadingCobrancas && tab === 'webhooks' ? (
            webhookPayloads.length === 0 ? <p className="ck-as__muted">Nenhum webhook encontrado.</p> : (
              <ul className="ck-as-list">
                {webhookPayloads.map((payload) => (
                  <li key={payload.id}>
                    <strong>{payload.tipo || payload.event || 'Webhook'}</strong>
                    <span>{payload.status || '—'} · {formatDateTime(payload.created_at)}</span>
                    {payload.erro_processamento ? <span className="ck-as__warn">{payload.erro_processamento}</span> : null}
                  </li>
                ))}
              </ul>
            )
          ) : null}

          {!loadingCobrancas && tab === 'tecnico' ? (
            <dl className="ck-as-tech">
              {[
                ['Assinatura', cobrancas?.dados_tecnicos?.assinatura_id],
                ['Matrícula', cobrancas?.dados_tecnicos?.matricula_id],
                ['External reference', cobrancas?.dados_tecnicos?.external_reference],
                ['ID no provedor', cobrancas?.dados_tecnicos?.preference_id || cobrancas?.dados_tecnicos?.gateway_assinatura_id],
                ['Status no gateway', cobrancas?.dados_tecnicos?.status_gateway],
                ['URL de pagamento', cobrancas?.dados_tecnicos?.payment_url],
              ].map(([label, value]) => (
                <div key={label}><dt>{label}</dt><dd>{value || '—'}</dd></div>
              ))}
              {pagamentosPlano.length ? (
                <div>
                  <dt>Parcelas locais</dt>
                  <dd>{pagamentosPlano.map((p) => `#${p.id} ${p.status || ''} ${money(p.valor)}`).join(' · ')}</dd>
                </div>
              ) : null}
            </dl>
          ) : null}
        </div>
      </Modal>
    </LayoutBase>
  );
}
