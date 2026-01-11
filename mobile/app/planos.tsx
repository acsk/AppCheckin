import { colors } from '@/src/theme/colors';
import { Feather } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRoute } from '@react-navigation/native';
import { useRouter } from 'expo-router';
import React, { useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

interface Modalidade {
  id: number;
  nome: string;
  cor: string;
}

interface Plano {
  id: number;
  tenant_id: number;
  nome: string;
  descricao: string;
  valor: number;
  duracao_dias: number;
  checkins_semanais: number;
  ativo: boolean;
  modalidade: Modalidade;
}

interface Matricula {
  matricula_id: number;
  plano: Plano;
  datas: {
    matricula: string;
    inicio: string;
    vencimento: string;
  };
  valor: number;
  status: string;
  motivo: string;
  created_at: string;
  updated_at: string;
}

interface RouteParams {
  tenantId?: string;
}

export default function PlanosScreen() {
  const router = useRouter();
  const route = useRoute();
  const tenantId = (route.params as RouteParams)?.tenantId;
  const [matriculas, setMatriculas] = useState<Matricula[]>([]);
  const [selectedMatricula, setSelectedMatricula] = useState<Matricula | null>(null);
  const [loading, setLoading] = useState(true);
  const [apenasAtivos, setApenasAtivos] = useState(false);
  const [pagamentos, setPagamentos] = useState([]);
  const [resumoFinanceiro, setResumoFinanceiro] = useState(null);

  useEffect(() => {
    loadPlanos();
  }, []);

  const loadPlanos = async (todas = false) => {
    try {
      console.log('\n🔄 INICIANDO CARREGAMENTO DE PLANOS');
      
      const token = await AsyncStorage.getItem('@appcheckin:token');
      if (!token) {
        console.error('❌ Token não encontrado');
        Alert.alert('Erro', 'Token não encontrado');
        return;
      }
      console.log('✅ Token encontrado');

      const url = `http://localhost:8080/mobile/planos${todas ? '?todas=true' : ''}`;
      console.log('📍 URL:', url);

      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      console.log('📡 RESPOSTA DO SERVIDOR');
      console.log('   Status:', response.status);
      console.log('   Status Text:', response.statusText);

      const responseText = await response.text();
      console.log('   Body (primeiros 500 chars):', responseText.substring(0, 500));

      if (!response.ok) {
        console.error('❌ ERRO NA REQUISIÇÃO');
        console.error('   Status:', response.status);
        console.error('   Body completo:', responseText);
        throw new Error(`HTTP ${response.status}: ${responseText}`);
      }

      let data;
      try {
        data = JSON.parse(responseText);
        console.log('✅ JSON parseado com sucesso');
      } catch (parseError) {
        console.error('❌ ERRO AO FAZER PARSE DO JSON');
        console.error('   Erro:', (parseError as Error).message);
        console.error('   Body:', responseText);
        throw parseError;
      }

      console.log('   Dados completos:', JSON.stringify(data, null, 2));

      if (data.success && data.data?.matriculas) {
        console.log('✅ Matrículas carregadas com sucesso');
        console.log('   Quantidade:', data.data.matriculas.length);
        setMatriculas(data.data.matriculas);
      } else if (!data.success) {
        console.error('⚠️ success = false');
        console.error('   Error:', data.error);
        throw new Error(data.error || 'Erro ao carregar matrículas');
      }
    } catch (error) {
      console.error('❌ EXCEÇÃO CAPTURADA EM loadPlanos');
      console.error('   Nome:', (error as Error).name);
      console.error('   Mensagem:', (error as Error).message);
      console.error('   Stack:', (error as Error).stack);
      console.log('📦 Usando dados mockados como fallback...');
      
      // Dados mockados para exemplo (matrículas, não planos)
      const mockMatriculas = [
        {
          matricula_id: 23,
          plano: {
            id: 5,
            tenant_id: 4,
            nome: "Plano 3x por semana",
            descricao: "Acesso 3 vezes na semana à modalidade",
            valor: 150.00,
            duracao_dias: 30,
            checkins_semanais: 3,
            ativo: true,
            modalidade: {
              id: 4,
              nome: "Natação",
              cor: "#3b82f6"
            }
          },
          datas: {
            matricula: "2026-01-09",
            inicio: "2026-01-09",
            vencimento: "2026-02-08"
          },
          valor: 150.00,
          status: "ativa",
          motivo: "nova",
          created_at: "2026-01-09T10:30:00",
          updated_at: "2026-01-09T10:30:00"
        },
        {
          matricula_id: 24,
          plano: {
            id: 6,
            tenant_id: 5,
            nome: "Plano 1x por semana",
            descricao: "Acesso 1 vez na semana à modalidade",
            valor: 110.00,
            duracao_dias: 30,
            checkins_semanais: 1,
            ativo: true,
            modalidade: {
              id: 5,
              nome: "CrossFit",
              cor: "#10b981"
            }
          },
          datas: {
            matricula: "2026-01-07",
            inicio: "2026-01-07",
            vencimento: "2026-02-06"
          },
          valor: 110.00,
          status: "ativa",
          motivo: "nova",
          created_at: "2026-01-07T08:00:00",
          updated_at: "2026-01-07T08:00:00"
        }
      ];
      setMatriculas(mockMatriculas);
    } finally {
      setLoading(false);
    }
  };

  const loadPagamentos = async (matriculaId) => {
    try {
      console.log('\n🔄 INICIANDO CARREGAMENTO DE PAGAMENTOS');
      console.log('   matriculaId:', matriculaId);
      
      const token = await AsyncStorage.getItem('@appcheckin:token');
      if (!token) {
        console.warn('❌ Token não encontrado');
        return;
      }
      console.log('✅ Token encontrado:', token.substring(0, 20) + '...');

      const url = `http://localhost:8080/mobile/matriculas/${matriculaId}`;
      console.log('📍 URL:', url);

      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      console.log('📡 RESPOSTA DO SERVIDOR');
      console.log('   Status:', response.status);
      console.log('   Status Text:', response.statusText);
      
      // Tentar ler a resposta como texto primeiro
      const responseText = await response.text();
      console.log('   Body (primeiros 500 chars):', responseText.substring(0, 500));

      if (!response.ok) {
        console.error('❌ ERRO NA REQUISIÇÃO');
        console.error('   Status:', response.status);
        console.error('   Body completo:', responseText);
        
        try {
          const errorData = JSON.parse(responseText);
          console.error('   Erro parseado:', errorData);
        } catch (e) {
          console.error('   Não foi possível fazer parse do erro como JSON');
        }
        return;
      }

      // Se chegou aqui, status é OK, fazer parse como JSON
      let data;
      try {
        data = JSON.parse(responseText);
        console.log('✅ JSON parseado com sucesso');
        console.log('   Dados completos:', JSON.stringify(data, null, 2));
      } catch (parseError) {
        console.error('❌ ERRO AO FAZER PARSE DO JSON');
        console.error('   Erro:', (parseError as Error).message);
        console.error('   Body:', responseText);
        return;
      }

      if (data.success && data.data) {
        console.log('✅ SUCCESS = true');
        console.log('   Pagamentos:', data.data.pagamentos?.length || 0, 'itens');
        console.log('   Resumo Financeiro:', data.data.resumo_financeiro);
        
        setPagamentos(data.data.pagamentos || []);
        setResumoFinanceiro(data.data.resumo_financeiro);
      } else {
        console.warn('⚠️ success é false ou data.data está vazio');
        console.log('   Success:', data.success);
        console.log('   Data:', data.data);
      }
    } catch (error) {
      console.error('❌ EXCEÇÃO CAPTURADA');
      console.error('   Nome do erro:', (error as Error).name);
      console.error('   Mensagem:', (error as Error).message);
      console.error('   Stack:', (error as Error).stack);
    }
  };

  const handleSelectPlano = async (matricula) => {
    console.log('🎯 Matrícula selecionada:', matricula.matricula_id, 'Plano:', matricula.plano.nome);
    setSelectedMatricula(matricula);
    
    if (matricula.matricula_id) {
      await loadPagamentos(matricula.matricula_id);
    }
  };

  const formatarValor = (valor) => {
    return `R$ ${parseFloat(valor).toFixed(2).replace('.', ',')}`;
  };

  // Sempre mostrar apenas as ativas
  const matriculasFiltradas = matriculas
    .filter(m => m.status === 'ativa')
    .filter(m => tenantId ? m.plano.tenant_id === parseInt(tenantId) : true);

  if (loading) {
    return (
      <SafeAreaView style={styles.container} edges={['top']}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      </SafeAreaView>
    );
  }

  // Tela de Detalhes da Matrícula
  if (selectedMatricula) {
    return (
      <SafeAreaView style={styles.container} edges={['top']}>
        {/* Header */}
        <View style={styles.headerTop}>
          <TouchableOpacity 
            style={styles.headerBackButton}
            onPress={() => setSelectedMatricula(null)}
          >
            <Feather name="arrow-left" size={24} color="#333" />
          </TouchableOpacity>
          <Text style={styles.headerTitleCentered}>Detalhes do Plano</Text>
          <View style={{ width: 40 }} />
        </View>

        <ScrollView 
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
        >
          {/* Header do Plano */}
          <View style={styles.planoHeader}>
            <View style={{ flex: 1 }}>
              <Text style={styles.planoNome}>{selectedMatricula.plano.nome}</Text>
              <View style={styles.planoHeaderSubrow}>
                {selectedMatricula.plano.modalidade && (
                  <View style={[
                    styles.modalidadeBadge,
                    { backgroundColor: selectedMatricula.plano.modalidade.cor + '20' }
                  ]}>
                    <View 
                      style={[
                        styles.modalidadeDot,
                        { backgroundColor: selectedMatricula.plano.modalidade.cor }
                      ]}
                    />
                    <Text style={[
                      styles.modalidadeText,
                      { color: selectedMatricula.plano.modalidade.cor }
                    ]}>
                      {selectedMatricula.plano.modalidade.nome}
                    </Text>
                  </View>
                )}
                {selectedMatricula.plano.duracao_dias && (
                  <View style={styles.durationContainer}>
                    <Feather name="clock" size={14} color={colors.primary} />
                    <Text style={styles.durationText}>{selectedMatricula.plano.duracao_dias} dias</Text>
                  </View>
                )}
              </View>
            </View>
          </View>

          {/* Descrição */}
          {selectedMatricula.plano.descricao && (
            <View style={styles.descricaoCard}>
              <Text style={styles.descricaoText}>{selectedMatricula.plano.descricao}</Text>
            </View>
          )}

          {/* Pagamentos */}
          {pagamentos.length > 0 && (
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Histórico de Pagamentos</Text>
              <View style={styles.card}>
                {pagamentos.map((pagamento, idx) => (
                  <View key={idx}>
                    <View style={styles.pagamentoRow}>
                      <View style={styles.pagamentoContent}>
                        <Text style={styles.label}>
                          {new Date(pagamento.data_vencimento).toLocaleDateString('pt-BR')}
                        </Text>
                        <Text style={styles.value}>
                          R$ {typeof pagamento.valor === 'number'
                            ? pagamento.valor.toFixed(2).replace('.', ',')
                            : pagamento.valor}
                        </Text>
                      </View>
                      <View style={[
                        styles.statusBadge,
                        { 
                          backgroundColor: pagamento.status === 'Pago' ? '#4CAF5020' : '#FF980020',
                          paddingHorizontal: 12,
                          paddingVertical: 6
                        }
                      ]}>
                        <Text style={[
                          styles.statusText,
                          { color: pagamento.status === 'Pago' ? '#4CAF50' : '#FF9800' }
                        ]}>
                          {pagamento.status}
                        </Text>
                      </View>
                    </View>
                    {idx < pagamentos.length - 1 && <View style={styles.divider} />}
                  </View>
                ))}
              </View>
            </View>
          )}
        </ScrollView>
      </SafeAreaView>
    );
  }

  // Tela de Lista de Planos
  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      {/* Header com Botão Voltar e Recarregar */}
      <View style={styles.headerTop}>
        <TouchableOpacity 
          style={styles.headerBackButton}
          onPress={() => router.back()}
        >
          <Feather name="arrow-left" size={24} color="#333" />
        </TouchableOpacity>
        <Text style={styles.headerTitleCentered}>Planos</Text>
        <TouchableOpacity 
          style={styles.refreshButton}
          onPress={() => loadPlanos()}
        >
          <Feather name="refresh-cw" size={20} color={colors.primary} />
        </TouchableOpacity>
      </View>

      <ScrollView 
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Lista de Matrículas */}
        {matriculas.length > 0 ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>
              Minhas Matrículas ({matriculasFiltradas.length})
            </Text>
            {matriculasFiltradas.map((matricula) => (
              <TouchableOpacity
                key={matricula.matricula_id}
                style={[
                  styles.planoCard,
                  matricula.plano.modalidade && { borderLeftColor: matricula.plano.modalidade.cor }
                ]}
                onPress={() => handleSelectPlano(matricula)}
                activeOpacity={0.7}
              >
                <View style={styles.planoCardContent}>
                  {/* Header: Modalidade Badge */}
                  <View style={styles.planoCardHeader}>
                    {matricula.plano.modalidade && (
                      <View style={[
                        styles.modalidadeBadgeCard,
                        { backgroundColor: matricula.plano.modalidade.cor + '20' }
                      ]}>
                        <View 
                          style={[
                            styles.modalidadeBadgeDot,
                            { backgroundColor: matricula.plano.modalidade.cor }
                          ]}
                        />
                        <Text style={[
                          styles.modalidadeBadgeText,
                          { color: matricula.plano.modalidade.cor }
                        ]}>
                          {matricula.plano.modalidade.nome}
                        </Text>
                      </View>
                    )}
                  </View>

                  {/* Nome do Plano */}
                  <Text style={styles.planoCardNome}>{matricula.plano.nome}</Text>

                  {/* Valor destacado */}
                  <View style={styles.planoCardValorContainer}>
                    <Text style={styles.planoCardValor}>
                      {formatarValor(matricula.plano.valor)}
                    </Text>
                    <Text style={styles.planoCardValorSubtext}>/mês</Text>
                  </View>

                  {/* Info Row: Duração + Check-ins */}
                  <View style={styles.planoCardInfoRow}>
                    <View style={styles.infoItemCard}>
                      <Feather name="calendar" size={16} color={colors.primary} />
                      <View style={styles.infoItemContent}>
                        <Text style={styles.infoItemLabel}>Duração</Text>
                        <Text style={styles.infoItemValue}>{matricula.plano.duracao_dias} dias</Text>
                      </View>
                    </View>

                    <View style={styles.infoItemCard}>
                      <Feather name="repeat" size={16} color={colors.primary} />
                      <View style={styles.infoItemContent}>
                        <Text style={styles.infoItemLabel}>Por semana</Text>
                        <Text style={styles.infoItemValue}>
                          {matricula.plano.checkins_semanais === 999 ? 'Ilimitado' : `${matricula.plano.checkins_semanais}x`}
                        </Text>
                      </View>
                    </View>
                  </View>

                  {/* Status - Apenas mostra se a matrícula não está ativa */}
                  {matricula.status !== 'ativa' && (
                    <View style={styles.planoCardStatusBadge}>
                      <Feather name="alert-circle" size={14} color="#F44336" />
                      <Text style={styles.planoCardStatusText}>
                        {matricula.status.charAt(0).toUpperCase() + matricula.status.slice(1)}
                      </Text>
                    </View>
                  )}
                </View>
              </TouchableOpacity>
            ))}
          </View>
        ) : (
          <View style={styles.emptyContainer}>
            <Feather name="inbox" size={48} color="#ddd" />
            <Text style={styles.emptyText}>Nenhuma matrícula ativa encontrada</Text>
            <Text style={styles.emptySubtext}>
              Você não possui matrículas ativas no momento
            </Text>
          </View>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#000',
  },
  headerTitleCentered: {
    fontSize: 18,
    fontWeight: '700',
    color: '#000',
    flex: 1,
    textAlign: 'center',
  },
  headerBackButton: {
    padding: 8,
    marginRight: 8,
  },
  refreshButton: {
    padding: 8,
  },
  backButton: {
    padding: 8,
    marginBottom: 12,
    alignSelf: 'flex-start',
  },
  minimalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
    paddingHorizontal: 0,
  },
  headerActionsMini: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  filterButtonMini: {
    padding: 6,
  },
  filterButtonActiveMini: {
    backgroundColor: colors.primary + '15',
    borderRadius: 6,
  },
  refreshButtonMini: {
    padding: 6,
  },
  scrollContent: {
    padding: 16,
    paddingBottom: 16,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 50,
  },
  section: {
    marginBottom: 24,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#000',
    marginBottom: 12,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  divider: {
    height: 1,
    backgroundColor: '#f0f0f0',
    marginVertical: 12,
  },

  /* Tenant Info */
  tenantInfoCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  tenantInfoContent: {
    flex: 1,
  },
  tenantInfoName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#000',
    marginBottom: 2,
  },
  tenantInfoSlug: {
    fontSize: 12,
    color: '#999',
  },

  /* Plano Cards */
  planoCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    marginBottom: 16,
    overflow: 'hidden',
    borderLeftWidth: 6,
    borderLeftColor: colors.primary,
    borderWidth: 1,
    borderColor: '#e8e8e8',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.08,
    shadowRadius: 8,
    elevation: 4,
  },
  planoCardAtual: {
    borderColor: colors.primary,
    borderWidth: 2,
    backgroundColor: colors.primary + '05',
  },
  planoCardModalidadeBar: {
    height: 4,
  },
  planoCardContent: {
    padding: 16,
  },
  planoCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
    gap: 12,
  },
  modalidadeBadgeCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
  },
  modalidadeBadgeDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  modalidadeBadgeText: {
    fontSize: 12,
    fontWeight: '600',
    textTransform: 'capitalize',
  },
  platoAtualBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 6,
    backgroundColor: colors.primary + '15',
    borderRadius: 6,
  },
  platoAtualText: {
    fontSize: 11,
    color: colors.primary,
    fontWeight: '600',
  },
  planoCardNome: {
    fontSize: 16,
    fontWeight: '700',
    color: '#000',
    marginBottom: 12,
  },
  planoCardValorContainer: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: 4,
    marginBottom: 14,
  },
  planoCardValor: {
    fontSize: 24,
    fontWeight: '800',
    color: colors.primary,
  },
  planoCardValorSubtext: {
    fontSize: 13,
    color: '#999',
    fontWeight: '500',
  },
  planoCardInfoRow: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 12,
  },
  infoItemCard: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#f8f8f8',
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 10,
  },
  infoItemContent: {
    flex: 1,
  },
  infoItemLabel: {
    fontSize: 11,
    color: '#999',
    fontWeight: '500',
    marginBottom: 2,
  },
  infoItemValue: {
    fontSize: 13,
    color: '#000',
    fontWeight: '600',
  },
  planoCardStatusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#F4433615',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
  },
  planoCardStatusText: {
    fontSize: 12,
    color: '#F44336',
    fontWeight: '600',
  },

  /* Plano Details */
  planoHeader: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 20,
    marginBottom: 20,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  planoHeaderSubrow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginTop: 12,
  },
  planoNome: {
    fontSize: 22,
    fontWeight: '700',
    color: '#000',
  },
  modalidadeBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 8,
    alignSelf: 'flex-start',
  },
  modalidadeDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  modalidadeText: {
    fontSize: 13,
    fontWeight: '600',
  },
  planoValorContainer: {
    alignItems: 'flex-end',
  },
  planoValor: {
    fontSize: 24,
    fontWeight: '700',
    color: colors.primary,
  },
  planoValorMes: {
    fontSize: 13,
    fontWeight: '500',
    color: '#999',
    marginTop: 2,
  },
  tenantBadgeDetail: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 14,
    paddingVertical: 8,
    backgroundColor: colors.primary + '10',
    borderRadius: 8,
    marginBottom: 16,
    alignSelf: 'flex-start',
  },
  tenantBadgeText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.primary,
  },
  descricaoCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 14,
    marginBottom: 20,
    borderLeftWidth: 4,
    borderLeftColor: colors.primary,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  descricaoText: {
    fontSize: 13,
    color: '#666',
    lineHeight: 20,
  },

  /* Info */
  infoRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
  },
  infoContent: {
    flex: 1,
  },
  label: {
    fontSize: 12,
    color: '#999',
    marginBottom: 4,
  },
  value: {
    fontSize: 13,
    color: '#000',
    fontWeight: '600',
  },

  /* Status */
  statusInfo: {
    flexDirection: 'row',
    gap: 12,
  },
  statusItem: {
    flex: 1,
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 6,
    alignItems: 'center',
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
  },
  durationContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: colors.primary + '10',
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 8,
  },
  durationText: {
    fontSize: 13,
    color: colors.primary,
    fontWeight: '600',
  },

  /* Empty State */
  emptyContainer: {
    alignItems: 'center',
    paddingVertical: 60,
  },
  emptyText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#999',
    marginTop: 12,
  },
  emptySubtext: {
    fontSize: 13,
    color: '#ccc',
    marginTop: 6,
    textAlign: 'center',
  },
  pagamentoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
  },
  pagamentoContent: {
    flex: 1,
  },
});
