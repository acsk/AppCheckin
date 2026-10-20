import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { LogsViewer } from '../../components/ck/logs';
import { superAdminLogService } from '../../services/superAdminLogService';
import { authService } from '../../services/authService';
import { showError, showSuccess } from '../../utils/toast';
import { parseLaravelLines, rawLogText } from '../../utils/laravelLogs';

export default function LogsLaravelScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [arquivos, setArquivos] = useState([]);
  const [linhas, setLinhas] = useState([]);
  const [arquivoAtual, setArquivoAtual] = useState('laravel.log');
  const [qtdLinhas, setQtdLinhas] = useState(200);
  const [busca, setBusca] = useState('');
  const [nivel, setNivel] = useState('todos');
  const [modo, setModo] = useState('agrupado');
  const [meta, setMeta] = useState(null);
  const [erroLeitura, setErroLeitura] = useState('');
  const [ready, setReady] = useState(false);
  const requestId = useRef(0);
  const entries = useMemo(() => parseLaravelLines(linhas), [linhas]);

  const carregar = useCallback(async () => {
    if (!ready) return;
    const request = ++requestId.current;
    setLoading(true);
    setErroLeitura('');
    try {
      // Carrega o trecho completo. Filtrar linhas na API retiraria o contexto
      // e o stack trace; o viewer filtra os registros já reunidos.
      const data = await superAdminLogService.listar({ arquivo: arquivoAtual, linhas: qtdLinhas });
      if (request !== requestId.current) return;
      setArquivos(data.arquivos || []);
      setLinhas(data.leitura?.linhas || []);
      setMeta(data.leitura || null);
      setErroLeitura(data.erro_leitura || '');
      if (data.filtros?.arquivo) setArquivoAtual(data.filtros.arquivo);
    } catch (error) {
      if (request !== requestId.current) return;
      setLinhas([]);
      setMeta(null);
      setErroLeitura(error.mensagemLimpa || error.erro || error.error || 'Erro ao carregar logs');
    } finally {
      if (request === requestId.current) setLoading(false);
    }
  }, [ready, arquivoAtual, qtdLinhas]);

  useEffect(() => {
    let active = true;
    const init = async () => {
      try {
        const user = await authService.getCurrentUser();
        if (!active) return;
        const papel = authService.getEffectivePapelId(user);
        if (!user || (papel !== 3 && papel !== 4)) {
          showError('Acesso negado. Apenas administradores.');
          router.replace('/');
          return;
        }
        setReady(true);
      } catch {
        if (active) router.replace('/');
      }
    };
    init();
    return () => { active = false; };
  }, [router]);

  useEffect(() => {
    if (ready) carregar();
    return () => { requestId.current += 1; };
  }, [ready, carregar]);

  const copiar = async (registros) => {
    const texto = rawLogText(registros);
    if (!texto) return false;
    try {
      if (!navigator.clipboard?.writeText) throw new Error('Clipboard indisponível');
      await navigator.clipboard.writeText(texto);
      showSuccess('Logs copiados');
      return true;
    } catch {
      showError('Não foi possível copiar automaticamente. Expanda o registro e copie o texto original.');
      return false;
    }
  };

  const baixar = (registros) => {
    if (!registros.length) return;
    const url = URL.createObjectURL(new Blob([rawLogText(registros)], { type: 'text/plain;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `${arquivoAtual.replace(/\.log$/, '')}-trecho.log`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  };

  return (
    <LayoutBase title="Logs da API" pageHeader={false}>
      <LogsViewer
        entries={entries} files={arquivos} file={arquivoAtual} meta={meta}
        lineLimit={qtdLinhas} loading={loading} error={erroLeitura}
        query={busca} level={nivel} mode={modo}
        onQueryChange={setBusca} onLevelChange={setNivel} onModeChange={setModo}
        onFileChange={(file) => { requestId.current += 1; setArquivoAtual(file); setLinhas([]); setMeta(null); }}
        onLineLimitChange={setQtdLinhas} onRefresh={carregar} onCopy={copiar} onDownload={baixar}
      />
    </LayoutBase>
  );
}
