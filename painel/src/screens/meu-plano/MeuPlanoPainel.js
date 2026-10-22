import React, { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import meuContratoService from '../../services/meuContratoService';
import { Button, Callout } from '../../components/ck';
import { MeuPlano, PagarModal } from '../../components/ck/meuPlano';
import { showError } from '../../utils/toast';

const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
export const openUrl = (url) => {
  if (url && typeof window !== 'undefined') window.open(url, '_blank', 'noopener');
};

// Plano do Check-in da academia (contrato, cobranças e troca de plano). Usado na tela
// "Plano do Check-in" e na aba "Plano e assinatura" de Minha Conta.
export default function MeuPlanoPainel() {
  const router = useRouter();
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [paying, setPaying] = useState(null);
  const [generating, setGenerating] = useState(false);
  const [result, setResult] = useState(null);
  const [payError, setPayError] = useState('');
  const [payErrorCode, setPayErrorCode] = useState(null);

  const load = async () => {
    setError('');
    try {
      setData(await meuContratoService.buscar());
    } catch (err) {
      setError(err.message);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const pay = async (p) => {
    setPayError('');
    setResult(null);
    // Cobrança já gerada no Asaas: abre o mesmo link em vez de cobrar de novo
    if (p.tem_cobranca && p.gateway_url) {
      openUrl(p.gateway_url);
      return;
    }
    setPaying(p);
  };

  const generate = async (forma, documento) => {
    setGenerating(true);
    setPayError('');
    setPayErrorCode(null);
    try {
      const r = await meuContratoService.pagar(paying.id, forma, documento, !!paying.trocar);
      setResult(r.cobranca || null);
      load();
    } catch (err) {
      setPayError(err.message);
      setPayErrorCode(err.code || null);
      if (err.status === 503) showError(err.message);
    } finally {
      setGenerating(false);
    }
  };

  const closePay = () => {
    setPaying(null);
    setResult(null);
    setPayError('');
    setPayErrorCode(null);
    load();
  };

  return (
    <>
      {error ? (
        <Callout tone="danger" title="Não foi possível carregar o plano" action={<Button size="sm" onClick={load}>Tentar de novo</Button>}>{error}</Callout>
      ) : !data ? (
        <p className="ck-al-hint" role="status">Carregando plano…</p>
      ) : (
        <MeuPlano
          data={data}
          today={today()}
          onPay={pay}
          onChangeMethod={(p) => { setPayError(''); setPayErrorCode(null); setResult(null); setPaying({ ...p, trocar: true }); }}
          onOpenInvoice={openUrl}
          onChangePlan={(data.planos || []).length ? () => router.push('/meu-plano/trocar') : undefined}
        />
      )}
      <PagarModal
        open={!!paying}
        payment={paying}
        formas={data?.formas}
        generating={generating}
        result={result}
        error={payError}
        errorCode={payErrorCode}
        needsDocument={data ? data.documento_valido === false : false}
        onGenerate={generate}
        onOpenUrl={openUrl}
        onClose={closePay}
      />
    </>
  );
}
