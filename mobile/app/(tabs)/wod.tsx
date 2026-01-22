import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useFocusEffect } from '@react-navigation/native';
import { useRouter } from 'expo-router';
import React, { useCallback, useState } from 'react';
import {
    ActivityIndicator,
    RefreshControl,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { mobileService } from '../../src/services/mobileService';
import { colors } from '../../src/theme/colors';

interface Bloco {
  id: number;
  wod_id: number;
  ordem: number;
  tipo: string;
  titulo: string;
  descricao?: string;
  conteudo?: string;
  tempo_cap?: string;
}

interface Variacao {
  id: number;
  wod_id: number;
  nome: string;
  descricao: string;
}

interface WodDetalhes {
  id: number;
  tenant_id: number;
  modalidade_id: number;
  modalidade_nome: string;
  modalidade_cor: string;
  modalidade_icone?: string;
  modalidade?: {
    id: number;
    nome: string;
    cor?: string;
    icone?: string;
  };
  data: string;
  titulo: string;
  descricao: string;
  status: string;
  blocos: Bloco[];
  variacoes: Variacao[];
  resultados: any[];
  created_at: string;
  criado_por_nome: string;
}

interface Modalidade {
  id: number;
  modalidade_id: number;
  modalidade_nome: string;
  modalidade_cor: string;
  modalidade_icone?: string;
  wods?: WodDetalhes[];
}

export default function WodScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [modalidades, setModalidades] = useState<Modalidade[]>([]);
  const [selectedModality, setSelectedModality] = useState<Modalidade | null>(null);
  const [wodDetalhes, setWodDetalhes] = useState<WodDetalhes | null>(null);
  const [error, setError] = useState<string | null>(null);

  const loadModalidades = useCallback(async () => {
    try {
      setError(null);
      setLoading(true);
      
      const token = await AsyncStorage.getItem('@appcheckin:token');
      if (!token) {
        setError('Token não encontrado');
        return;
      }

      const response = await mobileService.getModalidadesComWodHoje();
      
      // Extrair o array correto da resposta
      const wodsArray = response?.data || response;
      
      if (!wodsArray || !Array.isArray(wodsArray) || wodsArray.length === 0) {
        setError('Nenhum WOD disponível para hoje');
        return;
      }
      
      // Agrupar WODs por modalidade
      const modalidadesMap = new Map();
      
      wodsArray.forEach((wod: any) => {
        const modId = wod.modalidade?.id ?? wod.modalidade_id;
        const modNome = wod.modalidade?.nome ?? wod.modalidade_nome;
        const modCor = wod.modalidade?.cor ?? wod.modalidade_cor ?? '#999';
        const modIcone = wod.modalidade?.icone ?? wod.modalidade_icone;

        if (!modId || !modNome) {
          return;
        }

        if (!modalidadesMap.has(modId)) {
          modalidadesMap.set(modId, {
            modalidade_id: modId,
            modalidade_nome: modNome,
            modalidade_cor: modCor,
            modalidade_icone: modIcone,
            wods: []
          });
        }

        modalidadesMap.get(modId).wods.push(wod);
      });

      const modalidadesArray = Array.from(modalidadesMap.values());
      
      setModalidades(modalidadesArray);
      
      // Se houver apenas uma modalidade, carregar direto
      if (modalidadesArray.length === 1) {
        console.log('⚡ [WOD] Apenas 1 modalidade detectada, carregando primeiro WOD automaticamente...');
        const modalidade = modalidadesArray[0];
        const primeiWod = modalidade.wods[0];
        // Adicionar dados da modalidade ao WOD
        const wodComModalidade = {
          ...primeiWod,
          modalidade_id: modalidade.modalidade_id,
          modalidade_nome: modalidade.modalidade_nome,
          modalidade_cor: modalidade.modalidade_cor,
          modalidade_icone: modalidade.modalidade_icone
        };
        setSelectedModality(modalidade);
        setWodDetalhes(wodComModalidade);
      }
    } catch (error: any) {
      console.error('❌ [WOD] Erro ao carregar modalidades:', error);
      console.error('📍 [WOD] Detalhes do erro:', {
        message: error?.message,
        status: error?.status,
        response: error?.response,
        stack: error?.stack
      });
      setError(error?.message || 'Erro ao carregar WODs do dia');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  // Recarregar quando a tela ganhar foco (volta da navegação)
  useFocusEffect(
    useCallback(() => {
      loadModalidades();
    }, [loadModalidades])
  );

  const loadWodDetalhes = (modalidade: Modalidade) => {
    try {
      setError(null);
      setSelectedModality(modalidade);

      // Se houver apenas 1 WOD para a modalidade, mostrar direto
      if (modalidade.wods && modalidade.wods.length > 0) {
        const primeiro = modalidade.wods[0];
        // Garantir que a cor e nome da modalidade estejam no WOD
        const wodComModalidade = {
          ...primeiro,
          modalidade_id: modalidade.modalidade_id,
          modalidade_nome: modalidade.modalidade_nome,
          modalidade_cor: modalidade.modalidade_cor,
          modalidade_icone: modalidade.modalidade_icone
        };
        setWodDetalhes(wodComModalidade);
      } else {
        setError('Nenhum WOD encontrado para essa modalidade');
      }
    } catch (error: any) {
      setError(error?.message || 'Erro ao abrir detalhes do WOD');
    }
  };

  const onRefresh = () => {
    setRefreshing(true);
    loadModalidades();
  };

  const getModalidadeIcon = (nomeMod: string): string => {
    const modal = nomeMod?.toLowerCase() || '';
    if (modal.includes('crossfit') || modal.includes('cross')) return 'activity';
    if (modal.includes('funcional')) return 'trending-up';
    if (modal.includes('musculação')) return 'target';
    if (modal.includes('yoga')) return 'sun';
    if (modal.includes('pilates')) return 'circle';
    return 'zap';
  };

  const getModalidadeMdiIcon = (icone?: string): string | null => {
    if (!icone) return null;
    return icone;
  };

  const getTipoIcon = (tipo: string): string => {
    const t = tipo?.toLowerCase() || '';
    if (t.includes('warmup')) return 'zap';
    if (t.includes('metcon')) return 'activity';
    if (t.includes('accessory')) return 'tool';
    if (t.includes('main')) return 'target';
    if (t.includes('finisher') || t.includes('cool')) return 'wind';
    return 'activity';
  };

  const formatDate = (dateString: string): string => {
    try {
      const date = new Date(dateString + 'T00:00:00');
      return date.toLocaleDateString('pt-BR', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
      });
    } catch {
      return dateString;
    }
  };

  // Estado: Carregando modalidades
  if (loading) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.headerTop}>
          <Text style={styles.headerTitle}>WOD do Dia</Text>
        </View>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={styles.loadingText}>Carregando treino...</Text>
        </View>
      </SafeAreaView>
    );
  }

  // Estado: Erro
  if (error && !selectedModality) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.headerTop}>
          <Text style={styles.headerTitle}>WOD do Dia</Text>
        </View>
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[colors.primary]} />
          }
        >
          <View style={styles.errorContainer}>
            <Feather name="alert-circle" size={64} color="#FF6B6B" />
            <Text style={styles.errorTitle}>Ops!</Text>
            <Text style={styles.errorMessage}>{error}</Text>
            <TouchableOpacity style={styles.retryButton} onPress={loadModalidades}>
              <Text style={styles.retryButtonText}>Tentar Novamente</Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </SafeAreaView>
    );
  }

  // Estado: Exibir detalhes do WOD (apenas 1 modalidade)
  if (selectedModality && wodDetalhes) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.headerTop}>
          <View style={styles.headerTitleRow}>
            {modalidades.length > 1 && (
              <TouchableOpacity 
                style={styles.backButton}
                onPress={() => {
                  setSelectedModality(null);
                  setWodDetalhes(null);
                }}
              >
                <Feather name="chevron-left" size={24} color="#fff" />
              </TouchableOpacity>
            )}
            <View>
              <Text style={styles.headerTitle}>WOD do Dia</Text>
              <Text style={styles.headerSubtitle}>
                {formatDate(wodDetalhes.data)}
              </Text>
            </View>
          </View>
        </View>

        <ScrollView
          style={styles.scrollView}
          contentContainerStyle={styles.scrollContent}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[colors.primary]} />
          }
        >
          {/* Card de informações do WOD */}
          <View style={styles.wodCard}>
            <View
              style={[
                styles.wodHeaderBar,
                { backgroundColor: wodDetalhes.modalidade_cor }
              ]}
            >
              <View style={styles.wodHeaderBadge}>
                <View style={styles.wodHeaderIcon}>
                  {getModalidadeMdiIcon(
                    wodDetalhes.modalidade_icone || wodDetalhes.modalidade?.icone
                  ) ? (
                    <MaterialCommunityIcons
                      name={getModalidadeMdiIcon(
                        wodDetalhes.modalidade_icone || wodDetalhes.modalidade?.icone
                      ) as any}
                      size={26}
                      color={wodDetalhes.modalidade_cor}
                    />
                  ) : (
                    <Feather
                      name={getModalidadeIcon(wodDetalhes.modalidade_nome) as any}
                      size={26}
                      color={wodDetalhes.modalidade_cor}
                    />
                  )}
                </View>
                <Text style={styles.wodHeaderBadgeText}>
                  {wodDetalhes.modalidade_nome}
                </Text>
              </View>
              <View style={styles.wodHeaderAccent} />
            </View>

            <Text style={styles.wodTitulo}>{wodDetalhes.titulo}</Text>

            {wodDetalhes.descricao && (
              <View style={styles.descricaoContainer}>
                <Text style={styles.descricaoLabel}>Tipo de Treino</Text>
                <Text style={styles.wodDescricao}>{wodDetalhes.descricao}</Text>
              </View>
            )}

            {wodDetalhes.criado_por_nome && (
              <View style={styles.criadoPorContainer}>
                <Feather name="user-check" size={14} color="#999" />
                <Text style={styles.criadoPorText}>Criado por {wodDetalhes.criado_por_nome}</Text>
              </View>
            )}
          </View>

          {/* Blocos do WOD */}
          {wodDetalhes.blocos && wodDetalhes.blocos.length > 0 && (
            <View style={styles.secaoContainer}>
              {wodDetalhes.blocos.map((bloco, index) => (
                <View key={bloco.id} style={styles.blocoCard}>
                  <View style={styles.blocoHeader}>
                    <View style={[
                      styles.blocoOrdem,
                      { backgroundColor: `${wodDetalhes.modalidade_cor}20` }
                    ]}>
                      <Text style={[
                        styles.blocoOrdemText,
                        { color: wodDetalhes.modalidade_cor }
                      ]}>
                        {bloco.ordem ? bloco.ordem : index + 1}
                      </Text>
                    </View>
                    <View style={styles.blocoInfo}>
                      <View style={[
                        styles.blocoBadge,
                        { backgroundColor: `${wodDetalhes.modalidade_cor}20` }
                      ]}>
                        <Feather 
                          name={getTipoIcon(bloco.tipo) as any} 
                          size={14} 
                          color={wodDetalhes.modalidade_cor}
                        />
                        <Text style={[
                          styles.blocoTipoText,
                          { color: wodDetalhes.modalidade_cor }
                        ]}>{bloco.tipo.toUpperCase()}</Text>
                      </View>
                    </View>
                  </View>
                  <Text style={styles.blocoTitulo}>{bloco.titulo}</Text>
                  <Text style={styles.blocoDescricao}>
                    {bloco.descricao || bloco.conteudo}
                  </Text>
                </View>
              ))}
            </View>
          )}

          {/* Variações */}
          {wodDetalhes.variacoes && wodDetalhes.variacoes.length > 0 && (
            <View style={styles.secaoContainer}>
              {wodDetalhes.variacoes.map((variacao) => (
                <View key={variacao.id} style={styles.variacaoCard}>
                  <View style={[
                    styles.variacaoBadge,
                    { backgroundColor: `${wodDetalhes.modalidade_cor}20` }
                  ]}>
                    <Text style={[
                      styles.variacaoNome,
                      { color: wodDetalhes.modalidade_cor }
                    ]}>{variacao.nome}</Text>
                  </View>
                  <Text style={styles.variacaoDescricao}>{variacao.descricao}</Text>
                </View>
              ))}
            </View>
          )}

          <TouchableOpacity
            style={styles.checkinButton}
            onPress={() => router.push('/(tabs)/checkin')}
          >
            <Feather name="check-circle" size={18} color="#fff" />
            <Text style={styles.checkinButtonText}>Realizar check-in</Text>
          </TouchableOpacity>

          {error && (
            <View style={styles.errorMessageContainer}>
              <Feather name="alert-circle" size={16} color="#FF6B6B" />
              <Text style={styles.errorMessageText}>{error}</Text>
            </View>
          )}
        </ScrollView>
      </SafeAreaView>
    );
  }

  // Estado: Seleção de modalidades (múltiplas)
  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.headerTop}>
        <View>
          <Text style={styles.headerTitle}>WOD do Dia</Text>
          <Text style={styles.headerSubtitle}>
            {formatDate(new Date().toISOString().split('T')[0])}
          </Text>
        </View>
      </View>

      <ScrollView
        style={styles.scrollView}
        contentContainerStyle={styles.scrollContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[colors.primary]} />
        }
      >
        <View style={styles.secaoContainer}>
          <Text style={styles.sectionTitle}>
            Selecione a modalidade ({modalidades.length} disponíveis)
          </Text>
          
          {modalidades.map((modalidade) => {
            const primeiroWod = modalidade.wods?.[0];
            return (
              <TouchableOpacity
                key={modalidade.modalidade_id}
                style={[
                  styles.modalidadeListItem,
                  { borderLeftColor: modalidade.modalidade_cor }
                ]}
                onPress={() => loadWodDetalhes(modalidade)}
                disabled={loading}
              >
                <View 
                  style={[
                    styles.modalidadeListIcon,
                    { backgroundColor: `${modalidade.modalidade_cor}20` }
                  ]}
                >
                  {getModalidadeMdiIcon(modalidade.modalidade_icone) ? (
                    <MaterialCommunityIcons
                      name={getModalidadeMdiIcon(modalidade.modalidade_icone) as any}
                      size={22}
                      color={modalidade.modalidade_cor}
                    />
                  ) : (
                    <Feather
                      name={getModalidadeIcon(modalidade.modalidade_nome) as any}
                      size={22}
                      color={modalidade.modalidade_cor}
                    />
                  )}
                </View>
                <View style={styles.modalidadeListContent}>
                  <Text style={styles.modalidadeListNome}>
                    {modalidade.modalidade_nome}
                  </Text>
                  {primeiroWod ? (
                    <>
                      <Text style={styles.modalidadeListWodTitle}>
                        {primeiroWod.titulo}
                      </Text>
                      <Text style={styles.modalidadeListWodMeta}>
                        {primeiroWod.blocos?.length || 0} blocos • {primeiroWod.variacoes?.length || 0} variações
                      </Text>
                      {primeiroWod.descricao ? (
                        <Text style={styles.modalidadeListWodDesc} numberOfLines={2}>
                          {primeiroWod.descricao}
                        </Text>
                      ) : null}
                    </>
                  ) : (
                    <Text style={styles.modalidadeListHint}>Toque para ver o WOD</Text>
                  )}
                </View>
                <View style={styles.modalidadeListArrow}>
                  {loading ? (
                    <ActivityIndicator size="small" color={colors.primary} />
                  ) : (
                    <Feather name="chevron-right" size={22} color={modalidade.modalidade_cor} />
                  )}
                </View>
              </TouchableOpacity>
            );
          })}
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.backgroundSecondary,
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 18,
    paddingVertical: 16,
    backgroundColor: colors.primary,
    borderBottomWidth: 0,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.2,
    shadowRadius: 6,
    elevation: 8,
  },
  headerTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  backButton: {
    padding: 6,
    marginLeft: -6,
  },
  headerTitle: {
    fontSize: 24,
    fontWeight: '800',
    color: '#fff',
  },
  headerSubtitle: {
    fontSize: 14,
    color: 'rgba(255,255,255,0.8)',
    textTransform: 'capitalize',
    marginTop: 4,
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    padding: 16,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 8,
    fontSize: 16,
    color: colors.textSecondary,
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 40,
  },
  errorTitle: {
    fontSize: 22,
    fontWeight: 'bold',
    color: '#FF6B6B',
    marginTop: 12,
    marginBottom: 6,
  },
  errorMessage: {
    fontSize: 16,
    color: colors.textSecondary,
    textAlign: 'center',
    marginBottom: 16,
    paddingHorizontal: 24,
  },
  retryButton: {
    backgroundColor: colors.primary,
    paddingHorizontal: 24,
    paddingVertical: 10,
    borderRadius: 10,
  },
  retryButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  errorMessageContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff1f2',
    padding: 12,
    borderRadius: 12,
    marginTop: 16,
    gap: 8,
  },
  errorMessageText: {
    flex: 1,
    fontSize: 15,
    color: '#ef4444',
  },
  secaoContainer: {
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 19,
    fontWeight: '700',
    color: colors.text,
    marginBottom: 12,
  },
  wodCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 14,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#f0f1f4',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.06,
    shadowRadius: 10,
    elevation: 2,
  },
  wodHeaderBar: {
    borderRadius: 14,
    paddingHorizontal: 12,
    paddingVertical: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  wodHeaderBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  wodHeaderIcon: {
    width: 38,
    height: 38,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
  },
  wodHeaderBadgeText: {
    fontSize: 16,
    fontWeight: '700',
    color: '#fff',
  },
  wodHeaderAccent: {
    width: 36,
    height: 6,
    borderRadius: 999,
    backgroundColor: 'rgba(255,255,255,0.6)',
  },
  wodTitulo: {
    fontSize: 19,
    fontWeight: '700',
    color: colors.text,
    marginBottom: 4,
  },
  descricaoContainer: {
    marginTop: 6,
    marginBottom: 8,
  },
  descricaoLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textMuted,
    textTransform: 'uppercase',
    marginBottom: 2,
    letterSpacing: 0.5,
  },
  wodDescricao: {
    fontSize: 16,
    lineHeight: 20,
    color: colors.textSecondary,
  },
  criadoPorContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 8,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: '#eef2f7',
    gap: 4,
  },
  criadoPorText: {
    fontSize: 13,
    color: colors.textMuted,
    fontStyle: 'italic',
  },
  blocoCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 12,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: '#eef2f7',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  blocoHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginBottom: 6,
    gap: 8,
  },
  blocoOrdem: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: `${colors.primary}20`,
    justifyContent: 'center',
    alignItems: 'center',
  },
  blocoOrdemText: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.primary,
  },
  blocoInfo: {
    flex: 1,
  },
  blocoBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: `${colors.primary}15`,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
    gap: 3,
    alignSelf: 'flex-start',
  },
  blocoTipoText: {
    fontSize: 12,
    fontWeight: '700',
    color: colors.primary,
  },
  blocoTitulo: {
    fontSize: 15,
    fontWeight: '700',
    color: colors.text,
    marginBottom: 3,
  },
  blocoDescricao: {
    fontSize: 14,
    lineHeight: 16,
    color: colors.textSecondary,
  },
  variacaoCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 12,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: '#eef2f7',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  variacaoBadge: {
    backgroundColor: `${colors.primary}15`,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    alignSelf: 'flex-start',
    marginBottom: 6,
  },
  variacaoNome: {
    fontSize: 13,
    fontWeight: '700',
    color: colors.primary,
  },
  variacaoDescricao: {
    fontSize: 14,
    lineHeight: 16,
    color: colors.textSecondary,
  },
  modalidadeListItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    padding: 12,
    borderRadius: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: '#eef2f7',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  modalidadeListIcon: {
    width: 36,
    height: 36,
    borderRadius: 18,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 8,
  },
  modalidadeListContent: {
    flex: 1,
  },
  modalidadeListNome: {
    fontSize: 15,
    fontWeight: '700',
    color: colors.text,
    marginBottom: 1,
  },
  modalidadeListHint: {
    fontSize: 12,
    color: colors.textMuted,
  },
  modalidadeListWodTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.text,
    marginBottom: 2,
  },
  modalidadeListWodMeta: {
    fontSize: 12,
    color: colors.textSecondary,
    marginBottom: 2,
  },
  modalidadeListWodDesc: {
    fontSize: 12,
    lineHeight: 14,
    color: colors.textSecondary,
  },
  modalidadeListArrow: {
    padding: 4,
    marginLeft: 4,
  },
  checkinButton: {
    backgroundColor: colors.primary,
    borderRadius: 14,
    paddingVertical: 13,
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    marginTop: 6,
    shadowColor: colors.primary,
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.25,
    shadowRadius: 10,
    elevation: 3,
  },
  checkinButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '700',
  },
});
