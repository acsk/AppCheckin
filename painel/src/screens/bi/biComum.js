import React, { useEffect, useState } from 'react';
import { Button, Icon } from '../../components/ck';
import { exportarBi } from '../../services/biService';
import { showError } from '../../utils/toast';
import { fdate } from '../../components/ck/financeiro';

/* Peças comuns das telas do BI (Inteligência de negócios). */

export const num = (v) => new Intl.NumberFormat('pt-BR').format(v || 0);
export const pct = (v) => (v == null ? '—' : `${String(v).replace('.', ',')}%`);

const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
const MESES_LONGOS = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
/** 'YYYY-MM' → 'out' */
export const mesCurto = (ym) => MESES[Number(ym.slice(5, 7)) - 1];
/** 'YYYY-MM' → 'outubro de 2026' */
export const mesLongo = (ym) => `${MESES_LONGOS[Number(ym.slice(5, 7)) - 1]} de ${ym.slice(0, 4)}`;

/** Períodos aceitos pela API (App\Support\Bi\Periodo) */
export const PERIODOS = [
  { value: 'mes', label: 'Este mês' },
  { value: 'mes_anterior', label: 'Mês anterior' },
  { value: '3m', label: '3 meses' },
  { value: '12m', label: '12 meses' },
];

/**
 * Carrega os dados de uma tela do BI para o período; mantém os anteriores enquanto recarrega.
 * @param {(periodo: string) => Promise<any>} buscar
 */
export function useBiCarga(buscar, periodo) {
  const [dados, setDados] = useState(null);
  const [erro, setErro] = useState('');
  const [loading, setLoading] = useState(true);
  const [reload, setReload] = useState(0);

  useEffect(() => {
    let ativo = true;
    setLoading(true);
    setErro('');
    buscar(periodo)
      .then((d) => { if (ativo) setDados(d || null); })
      .catch((e) => { if (ativo) setErro(e?.mensagemLimpa || e?.response?.data?.error || 'Não foi possível carregar os indicadores.'); })
      .finally(() => { if (ativo) setLoading(false); });
    return () => { ativo = false; };
  }, [periodo, reload]);

  return { dados, erro, loading, recarregar: () => setReload((x) => x + 1) };
}

/** "vs 01/09 a 20/09" para o rodapé dos cards */
export const textoVs = (periodo) => (periodo ? `vs ${fdate(periodo.anterior.inicio).slice(0, 5)} a ${fdate(periodo.anterior.fim).slice(0, 5)}` : '');

/** Variação em pontos percentuais (para taxas), no formato do StatTile */
export function deltaPp(cur, prev, vs, goodWhenUp) {
  if (cur == null || prev == null) return null;
  const d = Math.round((cur - prev) * 10) / 10;
  return { value: d, label: `${d > 0 ? '+' : d < 0 ? '−' : ''}${Math.abs(d).toFixed(1).replace('.', ',')} p.p.`, vs, goodWhenUp };
}

export function Empty({ icon, title, children }) {
  return (
    <div className="ck-mt-empty">
      <span className="ck-mt-empty__icon" aria-hidden><Icon name={icon} size={20} /></span>
      <strong>{title}</strong>
      {children ? <p>{children}</p> : null}
    </div>
  );
}

/** Bloco recolhível com as fórmulas: itens = [[termo, explicação], …] */
export function ComoCalculamos({ itens }) {
  const [aberto, setAberto] = useState(false);
  return (
    <section className="ck-rp__how">
      <button type="button" className="ck-rp__howbtn" aria-expanded={aberto} onClick={() => setAberto(!aberto)}>
        <Icon name="chevron-down" size={16} className={aberto ? 'ck-ep__chev is-open' : 'ck-ep__chev'} />
        Como os números são calculados
      </button>
      {aberto ? <dl>{itens.map(([t, d]) => <div key={t}><dt>{t}</dt><dd>{d}</dd></div>)}</dl> : null}
    </section>
  );
}

/** Baixa o CSV de uma lista (alvo.indicador) ou do histórico da tela (alvo.tela) no período */
export function BotaoExportar({ alvo, periodo, children = 'Exportar', variant = 'secondary' }) {
  const [baixando, setBaixando] = useState(false);
  const baixar = async () => {
    setBaixando(true);
    try {
      await exportarBi(alvo, periodo);
    } catch (e) {
      showError(e?.mensagemLimpa || 'Não foi possível gerar o arquivo.');
    } finally {
      setBaixando(false);
    }
  };
  return <Button size="sm" variant={variant} icon="download" loading={baixando} onClick={baixar}>{children}</Button>;
}
