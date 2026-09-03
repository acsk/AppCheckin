import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  TextInput,
  useWindowDimensions,
  StyleSheet,
  Platform,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { superAdminLogService } from '../../services/superAdminLogService';
import { authService } from '../../services/authService';
import { showError, showSuccess } from '../../utils/toast';

const NIVEIS = [
  { id: '', label: 'Todos' },
  { id: 'error', label: 'Error' },
  { id: 'warning', label: 'Warning' },
  { id: 'info', label: 'Info' },
  { id: 'debug', label: 'Debug' },
];

const LINHAS_OPCOES = [100, 200, 500, 1000];

const corNivel = (nivel) => {
  switch (nivel) {
    case 'error':
    case 'critical':
    case 'alert':
    case 'emergency':
      return '#dc2626';
    case 'warning':
      return '#d97706';
    case 'info':
    case 'notice':
      return '#2563eb';
    case 'debug':
      return '#64748b';
    default:
      return '#334155';
  }
};

export default function LogsLaravelScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;

  const [loading, setLoading] = useState(true);
  const [arquivos, setArquivos] = useState([]);
  const [linhas, setLinhas] = useState([]);
  const [arquivoAtual, setArquivoAtual] = useState('laravel.log');
  const [qtdLinhas, setQtdLinhas] = useState(200);
  const [busca, setBusca] = useState('');
  const [buscaAplicada, setBuscaAplicada] = useState('');
  const [nivel, setNivel] = useState('');
  const [meta, setMeta] = useState(null);
  const [erroLeitura, setErroLeitura] = useState(null);
  const [ready, setReady] = useState(false);

  const carregar = useCallback(async () => {
    try {
      setLoading(true);
      const data = await superAdminLogService.listar({
        arquivo: arquivoAtual,
        linhas: qtdLinhas,
        busca: buscaAplicada || undefined,
        nivel: nivel || undefined,
      });
      setArquivos(data.arquivos || []);
      setLinhas(data.leitura?.linhas || []);
      setMeta(data.leitura || null);
      setErroLeitura(data.erro_leitura || null);
      if (data.filtros?.arquivo) {
        setArquivoAtual(data.filtros.arquivo);
      }
    } catch (error) {
      showError(error.mensagemLimpa || error.erro || error.error || 'Erro ao carregar logs');
    } finally {
      setLoading(false);
    }
  }, [arquivoAtual, qtdLinhas, buscaAplicada, nivel]);

  useEffect(() => {
    const init = async () => {
      const user = await authService.getCurrentUser();
      const papel = authService.getEffectivePapelId(user);
      if (!user || (papel !== 3 && papel !== 4)) {
        showError('Acesso negado. Apenas administradores.');
        router.replace('/');
        return;
      }
      setReady(true);
    };
    init();
  }, [router]);

  useEffect(() => {
    if (ready) {
      carregar();
    }
  }, [ready, carregar]);

  const copiarLogs = async () => {
    const texto = linhas.map((l) => l.texto).join('\n');
    if (!texto) {
      showError('Nenhuma linha para copiar');
      return;
    }
    try {
      if (Platform.OS === 'web' && typeof navigator !== 'undefined' && navigator.clipboard) {
        await navigator.clipboard.writeText(texto);
        showSuccess('Logs copiados');
        return;
      }
      showError('Selecione o texto no bloco de log para copiar manualmente');
    } catch {
      showError('Não foi possível copiar automaticamente');
    }
  };

  const aplicarBusca = () => {
    setBuscaAplicada(busca.trim());
  };

  return (
    <LayoutBase title="Logs Laravel" subtitle="API v2 — diagnóstico em produção">
      <ScrollView style={styles.container} contentContainerStyle={{ paddingBottom: 40 }}>
        <View style={styles.toolbar}>
          <TouchableOpacity style={styles.refreshBtn} onPress={carregar} disabled={loading}>
            <Feather name="refresh-cw" size={16} color="#6366f1" />
            <Text style={styles.refreshText}>Atualizar</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.copyBtn} onPress={copiarLogs}>
            <Feather name="copy" size={16} color="#16a34a" />
            <Text style={styles.copyText}>Copiar</Text>
          </TouchableOpacity>
        </View>

        <View style={styles.infoBox}>
          <Feather name="info" size={16} color="#4338ca" />
          <Text style={styles.infoText}>
            Exibe as últimas linhas de storage/logs da apiV2. Use busca e filtro de nível para
            isolar erros. Limite máximo: 1000 linhas por requisição.
          </Text>
        </View>

        <Text style={styles.sectionLabel}>Arquivo</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.chipsRow}>
          {(arquivos.length ? arquivos : [{ nome: arquivoAtual }]).map((arq) => (
            <TouchableOpacity
              key={arq.nome}
              style={[styles.chip, arquivoAtual === arq.nome && styles.chipAtivo]}
              onPress={() => setArquivoAtual(arq.nome)}
            >
              <Text style={[styles.chipText, arquivoAtual === arq.nome && styles.chipTextAtivo]}>
                {arq.nome}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>

        <Text style={styles.sectionLabel}>Linhas</Text>
        <View style={styles.chipsRowWrap}>
          {LINHAS_OPCOES.map((n) => (
            <TouchableOpacity
              key={n}
              style={[styles.chip, qtdLinhas === n && styles.chipAtivo]}
              onPress={() => setQtdLinhas(n)}
            >
              <Text style={[styles.chipText, qtdLinhas === n && styles.chipTextAtivo]}>{n}</Text>
            </TouchableOpacity>
          ))}
        </View>

        <Text style={styles.sectionLabel}>Nível</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.chipsRow}>
          {NIVEIS.map((item) => (
            <TouchableOpacity
              key={item.id || 'all'}
              style={[styles.chip, nivel === item.id && styles.chipAtivo]}
              onPress={() => setNivel(item.id)}
            >
              <Text style={[styles.chipText, nivel === item.id && styles.chipTextAtivo]}>
                {item.label}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>

        <Text style={styles.sectionLabel}>Buscar no texto</Text>
        <View style={[styles.searchRow, isMobile && { flexDirection: 'column' }]}>
          <TextInput
            style={[styles.searchInput, isMobile && { width: '100%' }]}
            placeholder="Ex.: JWT_SECRET, matricula_id, SQLSTATE"
            placeholderTextColor="#94a3b8"
            value={busca}
            onChangeText={setBusca}
            onSubmitEditing={aplicarBusca}
            returnKeyType="search"
          />
          <TouchableOpacity style={styles.searchBtn} onPress={aplicarBusca}>
            <Feather name="search" size={16} color="#fff" />
            <Text style={styles.searchBtnText}>Filtrar</Text>
          </TouchableOpacity>
        </View>

        {meta && (
          <View style={styles.metaBox}>
            <Text style={styles.metaText}>
              {meta.arquivo} · {meta.total_linhas_retornadas} linha(s)
              {meta.truncado ? ' · truncado (há mais no arquivo)' : ''}
              {meta.tamanho_bytes ? ` · ${Math.round(meta.tamanho_bytes / 1024)} KB` : ''}
            </Text>
          </View>
        )}

        {erroLeitura && (
          <View style={styles.erroBox}>
            <Text style={styles.erroText}>{erroLeitura}</Text>
          </View>
        )}

        {loading ? (
          <View style={styles.loadingBox}>
            <ActivityIndicator size="large" color="#6366f1" />
          </View>
        ) : linhas.length === 0 ? (
          <View style={styles.emptyBox}>
            <Feather name="file-text" size={36} color="#94a3b8" />
            <Text style={styles.emptyTitle}>Nenhuma linha encontrada</Text>
            <Text style={styles.emptyDesc}>Tente outro arquivo, nível ou termo de busca.</Text>
          </View>
        ) : (
          <View style={styles.logBox}>
            {linhas.map((linha, idx) => (
              <Text
                key={`${idx}-${linha.texto.slice(0, 24)}`}
                style={[styles.logLine, { color: corNivel(linha.nivel) }]}
                selectable
              >
                {linha.texto}
              </Text>
            ))}
          </View>
        )}
      </ScrollView>
    </LayoutBase>
  );
}

const mono = Platform.select({ ios: 'Menlo', android: 'monospace', default: 'monospace' });

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8fafc' },
  toolbar: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 8,
    paddingHorizontal: 16,
    paddingTop: 12,
  },
  refreshBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#eef2ff',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
  },
  refreshText: { color: '#6366f1', fontWeight: '600', fontSize: 13 },
  copyBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#ecfdf5',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#bbf7d0',
  },
  copyText: { color: '#16a34a', fontWeight: '600', fontSize: 13 },
  infoBox: {
    flexDirection: 'row',
    gap: 10,
    margin: 16,
    marginBottom: 8,
    padding: 12,
    backgroundColor: '#eef2ff',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#c7d2fe',
  },
  infoText: { flex: 1, fontSize: 12, color: '#4338ca', lineHeight: 18 },
  sectionLabel: {
    marginHorizontal: 16,
    marginTop: 8,
    marginBottom: 6,
    fontSize: 11,
    fontWeight: '700',
    color: '#64748b',
    textTransform: 'uppercase',
  },
  chipsRow: { paddingHorizontal: 16, marginBottom: 4 },
  chipsRowWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    paddingHorizontal: 16,
    marginBottom: 4,
  },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 999,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    marginRight: 8,
    marginBottom: 8,
  },
  chipAtivo: { backgroundColor: '#6366f1', borderColor: '#6366f1' },
  chipText: { fontSize: 12, fontWeight: '600', color: '#475569' },
  chipTextAtivo: { color: '#fff' },
  searchRow: {
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 16,
    marginBottom: 12,
    alignItems: 'center',
  },
  searchInput: {
    flex: 1,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: '#0f172a',
  },
  searchBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#6366f1',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 8,
  },
  searchBtnText: { color: '#fff', fontWeight: '600', fontSize: 13 },
  metaBox: { marginHorizontal: 16, marginBottom: 8 },
  metaText: { fontSize: 12, color: '#64748b' },
  erroBox: {
    marginHorizontal: 16,
    marginBottom: 8,
    padding: 10,
    backgroundColor: '#fef2f2',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#fecaca',
  },
  erroText: { color: '#b91c1c', fontSize: 12 },
  loadingBox: { padding: 40, alignItems: 'center' },
  emptyBox: {
    margin: 16,
    padding: 32,
    backgroundColor: '#fff',
    borderRadius: 12,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  emptyTitle: { fontSize: 15, fontWeight: '700', color: '#334155', marginTop: 10 },
  emptyDesc: { fontSize: 13, color: '#64748b', marginTop: 4, textAlign: 'center' },
  logBox: {
    marginHorizontal: 16,
    backgroundColor: '#0f172a',
    borderRadius: 10,
    padding: 12,
    borderWidth: 1,
    borderColor: '#1e293b',
  },
  logLine: {
    fontFamily: mono,
    fontSize: 11,
    lineHeight: 16,
    marginBottom: 4,
  },
});
