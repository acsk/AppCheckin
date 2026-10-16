import React, { useEffect, useMemo, useState } from 'react';
import LayoutBase from '../../components/LayoutBase';
import {
  Badge,
  Button,
  Callout,
  ChoiceCard,
  ChoiceGroup,
  Icon,
  ProviderTile,
  SaveBar,
  SecretField,
  Switch,
  Tabs,
} from '../../components/ck';
import { LOGO_ASAAS, LOGO_MERCADO_PAGO } from '../../components/ck/providerLogos';
import paymentCredentialsService from '../../services/paymentCredentialsService';
import { showSuccess, showError } from '../../utils/toast';

// Configurações › Pagamentos no modelo "PagamentosScreen" do design system do painel:
// tiles de provedor → painel do selecionado (teste, "No app") → ambiente em uso →
// formas de pagamento → credenciais por ambiente → uma única SaveBar.

const PROVIDERS = [
  { id: 'mercadopago', nome: 'Mercado Pago', logo: LOGO_MERCADO_PAGO },
  { id: 'asaas', nome: 'Asaas', logo: LOGO_ASAAS },
];
const ENV_LABEL = { sandbox: 'Sandbox', production: 'Produção' };
const METODOS = [
  { campo: 'enable_pix', label: 'PIX', description: 'Pagamento instantâneo com QR Code ou copia e cola.' },
  { campo: 'enable_boleto', label: 'Boleto', description: 'Compensa em até 3 dias úteis.' },
  { campo: 'enable_credit_card', label: 'Cartão', description: 'Crédito, à vista ou recorrente.' },
];

function formDe(provider, row) {
  return {
    provider,
    environment: row?.environment || 'sandbox',
    is_active: !!row?.is_active,
    enable_pix: row ? row.enable_pix !== false && row.enable_pix !== 0 : true,
    enable_boleto: row ? row.enable_boleto !== false && row.enable_boleto !== 0 : true,
    enable_credit_card: row ? row.enable_credit_card !== false && row.enable_credit_card !== 0 : true,
    access_token_test: '',
    public_key_test: '',
    access_token_prod: '',
    public_key_prod: '',
    webhook_secret: '',
  };
}

// Campos de credencial de um provedor num ambiente.
function camposCredenciais(providerId, env, row) {
  const sufixo = env === 'sandbox' ? 'test' : 'prod';
  if (providerId === 'asaas') {
    return [
      {
        campo: `access_token_${sufixo}`,
        label: 'API key',
        configured: !!row?.[`has_token_${sufixo}`],
        placeholder: env === 'sandbox' ? '$aact_hmlg_…' : '$aact_prod_…',
        hint: env === 'sandbox' ? 'Gerada na conta de testes do Asaas.' : 'Em Integrações › Chave de API no Asaas.',
      },
    ];
  }
  return [
    {
      campo: `access_token_${sufixo}`,
      label: 'Access token',
      configured: !!row?.[`has_token_${sufixo}`],
      placeholder: env === 'sandbox' ? 'TEST-…' : 'APP_USR-…',
      hint: 'Usado pelo servidor para criar cobranças.',
    },
    {
      campo: `public_key_${sufixo}`,
      label: 'Public key',
      configured: !!row?.[`public_key_${sufixo}_masked`],
      maskedValue: row?.[`public_key_${sufixo}_masked`] || undefined,
      placeholder: env === 'sandbox' ? 'TEST-…' : 'APP_USR-…',
      hint: 'Usada pelo app para o checkout.',
    },
  ];
}

function temCredencial(row, form, env) {
  const sufixo = env === 'sandbox' ? 'test' : 'prod';
  return !!(row?.[`has_token_${sufixo}`] || form?.[`access_token_${sufixo}`]?.trim());
}

const horaAgora = () =>
  new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

export default function PaymentCredentialsScreen() {
  const [loading, setLoading] = useState(true);
  const [existentes, setExistentes] = useState({ mercadopago: null, asaas: null });
  const [base, setBase] = useState({ mercadopago: formDe('mercadopago'), asaas: formDe('asaas') });
  const [forms, setForms] = useState(base);
  const [selecionado, setSelecionado] = useState('mercadopago');
  const [abaCredenciais, setAbaCredenciais] = useState({ mercadopago: 'sandbox', asaas: 'sandbox' });
  const [salvando, setSalvando] = useState(false);
  const [testando, setTestando] = useState(null);
  const [ultimoTeste, setUltimoTeste] = useState({});
  const [versao, setVersao] = useState(0); // remonta os campos ao descartar/salvar

  useEffect(() => {
    carregarCredenciais();
  }, []);

  const carregarCredenciais = async () => {
    try {
      setLoading(true);
      const response = await paymentCredentialsService.obterCredenciais();
      const lista = response.success ? response.data?.providers || [] : [];
      const nextExistentes = { mercadopago: null, asaas: null };
      lista.forEach((row) => {
        if (row.provider === 'mercadopago' || row.provider === 'asaas') nextExistentes[row.provider] = row;
      });
      const nextBase = {
        mercadopago: formDe('mercadopago', nextExistentes.mercadopago),
        asaas: formDe('asaas', nextExistentes.asaas),
      };
      setExistentes(nextExistentes);
      setBase(nextBase);
      setForms(nextBase);
      setAbaCredenciais({ mercadopago: nextBase.mercadopago.environment, asaas: nextBase.asaas.environment });
      setVersao((v) => v + 1);
    } catch (error) {
      console.error('Erro ao carregar credenciais:', error);
      showError('Não foi possível carregar as credenciais. Tente de novo.');
    } finally {
      setLoading(false);
    }
  };

  const atualizar = (provider, patch) =>
    setForms((prev) => ({ ...prev, [provider]: { ...prev[provider], ...patch } }));

  const sujo = (provider) => JSON.stringify(forms[provider]) !== JSON.stringify(base[provider]);
  const algumSujo = PROVIDERS.some((p) => sujo(p.id));

  const montarPayload = (provider) => {
    const f = forms[provider];
    const dados = {
      provider,
      environment: f.environment,
      is_active: f.is_active,
      enable_pix: !!f.enable_pix,
      enable_boleto: !!f.enable_boleto,
      enable_credit_card: !!f.enable_credit_card,
    };
    ['access_token_test', 'public_key_test', 'access_token_prod', 'public_key_prod', 'webhook_secret'].forEach((k) => {
      if (f[k].trim()) dados[k] = f[k].trim();
    });
    return dados;
  };

  const salvar = async () => {
    const alterados = PROVIDERS.filter((p) => sujo(p.id));
    // Mesma validação de antes: ativo exige a chave do ambiente escolhido.
    for (const p of alterados) {
      const f = forms[p.id];
      if (f.is_active && !temCredencial(existentes[p.id], f, f.environment)) {
        setSelecionado(p.id);
        setAbaCredenciais((prev) => ({ ...prev, [p.id]: f.environment }));
        showError(`Faltam as credenciais de ${ENV_LABEL[f.environment]} do ${p.nome}. Preencha a chave para ativar.`);
        return;
      }
    }
    try {
      setSalvando(true);
      for (const p of alterados) {
        await paymentCredentialsService.salvarCredenciais(montarPayload(p.id));
      }
      showSuccess(alterados.length > 1 ? 'Alterações salvas nos dois provedores' : 'Alterações salvas');
      await carregarCredenciais();
    } catch (error) {
      showError(error.response?.data?.message || 'Não foi possível salvar. Tente de novo.');
    } finally {
      setSalvando(false);
    }
  };

  const descartar = () => {
    setForms(base);
    setAbaCredenciais({ mercadopago: base.mercadopago.environment, asaas: base.asaas.environment });
    setVersao((v) => v + 1);
  };

  const testar = async (provider) => {
    try {
      setTestando(provider);
      const response = await paymentCredentialsService.testarConexao(provider);
      if (response.success) {
        setUltimoTeste((prev) => ({ ...prev, [provider]: { ok: true, env: forms[provider].environment, hora: horaAgora() } }));
      }
    } catch (error) {
      setUltimoTeste((prev) => ({ ...prev, [provider]: { ok: false, hora: horaAgora(), msg: error.response?.data?.message } }));
      showError(error.response?.data?.message || 'A conexão falhou. Confira as chaves e tente de novo.');
    } finally {
      setTestando(null);
    }
  };

  const atual = PROVIDERS.find((p) => p.id === selecionado);
  const form = forms[selecionado];
  const row = existentes[selecionado];
  const env = form.environment;
  const aba = abaCredenciais[selecionado];
  const okSandbox = temCredencial(row, form, 'sandbox');
  const okProducao = temCredencial(row, form, 'production');
  const okAtual = env === 'sandbox' ? okSandbox : okProducao;
  const teste = ultimoTeste[selecionado];
  const campos = useMemo(() => camposCredenciais(selecionado, aba, row), [selecionado, aba, row]);

  if (loading) {
    return (
      <LayoutBase title="Pagamentos" subtitle="Escolha como os alunos pagam no app.">
        <div className="ck-config" style={{ display: 'flex', alignItems: 'center', gap: 10, color: 'var(--ink-muted)' }}>
          <Icon name="loader" size={18} className="ck-spin" />
          Carregando credenciais…
        </div>
      </LayoutBase>
    );
  }

  const envBadge = (e) =>
    e === 'production' ? (
      <Badge tone="warning" dot>Produção</Badge>
    ) : (
      <Badge tone="info" dot>Sandbox</Badge>
    );

  return (
    <LayoutBase
      title="Pagamentos"
      subtitle="Escolha como os alunos pagam no app. Mercado Pago e Asaas funcionam de forma independente."
    >
      <div className="ck-config" style={{ display: 'grid', gap: 'var(--space-6)' }}>
        {/* Resumo: um tile por provedor */}
        <div className="ck-tiles" role="radiogroup" aria-label="Provedor">
          {PROVIDERS.map((p) => {
            const f = forms[p.id];
            const ok = temCredencial(existentes[p.id], f, f.environment);
            return (
              <ProviderTile
                key={p.id}
                name={p.nome}
                logo={p.logo}
                enabled={f.is_active}
                selected={selecionado === p.id}
                onSelect={() => setSelecionado(p.id)}
                badges={envBadge(f.environment)}
                footIcon={ok ? 'check-circle' : 'alert-triangle'}
                footnote={
                  ok
                    ? `Credenciais de ${ENV_LABEL[f.environment]} configuradas`
                    : `Faltam credenciais de ${ENV_LABEL[f.environment]}`
                }
              />
            );
          })}
        </div>

        {/* Painel do provedor selecionado */}
        <section className="ck-card" aria-label={atual.nome} key={selecionado}>
          <div className="ck-card__head">
            <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 'var(--space-4)', flexWrap: 'wrap' }}>
              <div style={{ flex: 1, minWidth: 200 }}>
                <h2 className="ck-card__title" style={{ fontSize: 24, lineHeight: '28px' }}>
                  {atual.nome}
                </h2>
                <div style={{ marginTop: 4, display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 13, lineHeight: '18px', color: teste?.ok ? 'var(--success)' : teste ? 'var(--danger)' : 'var(--ink-muted)' }}>
                  <Icon name={teste?.ok ? 'check-circle' : teste ? 'alert-triangle' : 'activity'} size={15} />
                  {teste?.ok
                    ? `Conexão OK em ${ENV_LABEL[teste.env]}, às ${teste.hora}`
                    : teste
                      ? `A conexão falhou às ${teste.hora}`
                      : 'Teste a conexão depois de salvar as chaves'}
                </div>
              </div>
              <Button
                icon="zap"
                loading={testando === selecionado}
                disabled={!form.is_active || !okAtual || sujo(selecionado)}
                title={sujo(selecionado) ? 'Salve as alterações antes de testar' : undefined}
                onClick={() => testar(selecionado)}
              >
                {testando === selecionado ? 'Testando…' : 'Testar conexão'}
              </Button>
              <span style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-3)', paddingLeft: 'var(--space-4)', marginLeft: 'var(--space-1)', borderLeft: '1px solid var(--line)', fontSize: 14, fontWeight: 600, color: 'var(--ink-muted)' }}>
                <span aria-hidden="true">No app</span>
                <Switch
                  checked={form.is_active}
                  onChange={(v) => atualizar(selecionado, { is_active: v })}
                  ariaLabel={`${atual.nome} disponível no app`}
                />
              </span>
            </div>
          </div>

          <div className="ck-card__body" style={{ gap: 'var(--space-8)' }}>
            {!form.is_active ? (
              <Callout tone="neutral" title={`${atual.nome} está desativado`}>
                Os alunos não verão esta forma de pagamento no app. As credenciais continuam salvas.
              </Callout>
            ) : null}

            {/* Ambiente em uso */}
            <div className="ck-section">
              <h3 className="ck-section__title">Ambiente em uso</h3>
              <p className="ck-section__desc">Define onde as cobranças dos alunos são processadas.</p>
              <ChoiceGroup label="Ambiente em uso">
                <ChoiceCard
                  checked={env === 'sandbox'}
                  onSelect={() => {
                    atualizar(selecionado, { environment: 'sandbox' });
                    setAbaCredenciais((prev) => ({ ...prev, [selecionado]: 'sandbox' }));
                  }}
                  title="Sandbox"
                  description="Para testes. Nenhum valor real é cobrado."
                  status={okSandbox ? 'success' : 'warning'}
                  statusLabel={okSandbox ? 'Credenciais configuradas' : 'Faltam credenciais'}
                />
                <ChoiceCard
                  checked={env === 'production'}
                  onSelect={() => {
                    atualizar(selecionado, { environment: 'production' });
                    setAbaCredenciais((prev) => ({ ...prev, [selecionado]: 'production' }));
                  }}
                  title="Produção"
                  description="Cobranças reais nos cartões e contas dos alunos."
                  status={okProducao ? 'success' : 'warning'}
                  statusLabel={okProducao ? 'Credenciais configuradas' : 'Faltam credenciais'}
                />
              </ChoiceGroup>
              {form.is_active && !okAtual ? (
                <Callout tone="danger" title={`Faltam as credenciais de ${ENV_LABEL[env]}`}>
                  Preencha as chaves abaixo para que o app consiga cobrar.
                </Callout>
              ) : null}
            </div>

            {/* Formas de pagamento oferecidas no checkout deste provedor */}
            <div className="ck-section">
              <h3 className="ck-section__title">Formas de pagamento</h3>
              <p className="ck-section__desc">O que os alunos podem usar no checkout do {atual.nome}.</p>
              <div style={{ display: 'grid', gap: 'var(--space-3)' }}>
                {METODOS.map((m) => (
                  <Switch
                    key={m.campo}
                    label={m.label}
                    description={m.description}
                    checked={!!form[m.campo]}
                    onChange={(v) => atualizar(selecionado, { [m.campo]: v })}
                  />
                ))}
              </div>
            </div>

            {/* Credenciais por ambiente */}
            <div className="ck-section">
              <h3 className="ck-section__title">Credenciais</h3>
              <Tabs
                label="Credenciais por ambiente"
                value={aba}
                onChange={(v) => setAbaCredenciais((prev) => ({ ...prev, [selecionado]: v }))}
                tabs={[
                  { value: 'production', label: 'Produção', status: okProducao ? 'success' : 'warning', statusLabel: okProducao ? 'Configurado' : 'Pendente' },
                  { value: 'sandbox', label: 'Sandbox', status: okSandbox ? 'success' : 'warning', statusLabel: okSandbox ? 'Configurado' : 'Pendente' },
                ]}
              />
              <div
                key={`${selecionado}-${aba}-${versao}`}
                style={{
                  paddingTop: 'var(--space-2)',
                  display: 'grid',
                  gap: 'var(--space-4)',
                  gridTemplateColumns: campos.length > 1 ? 'repeat(auto-fit, minmax(260px, 1fr))' : '1fr',
                }}
              >
                {campos.map((c) => (
                  <SecretField
                    key={c.campo}
                    label={c.label}
                    configured={c.configured}
                    maskedValue={c.maskedValue}
                    placeholder={c.placeholder}
                    hint={c.hint}
                    value={form[c.campo]}
                    onChange={(v) => atualizar(selecionado, { [c.campo]: v })}
                  />
                ))}
              </div>
            </div>
          </div>
        </section>

        <SaveBar visible={algumSujo} saving={salvando} onDiscard={descartar} onSave={salvar} />
      </div>
    </LayoutBase>
  );
}
