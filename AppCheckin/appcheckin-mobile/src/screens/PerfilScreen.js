import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  Image,
  TouchableOpacity,
  ActivityIndicator,
  RefreshControl,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import mobileService from '../services/mobileService';
import { colors } from '../theme/colors';

export default function PerfilScreen({ navigation, user }) {
  const [perfil, setPerfil] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState(null);

  const carregarPerfil = useCallback(async () => {
    try {
      setError(null);
      const response = await mobileService.getPerfil();
      if (response.success && response.data) {
        setPerfil(response.data);
      } else {
        setError('Erro ao carregar perfil');
      }
    } catch (err) {
      console.error('Erro ao carregar perfil:', err);
      setError(err.message || 'Erro ao carregar perfil');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    carregarPerfil();
  }, [carregarPerfil]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    carregarPerfil();
  }, [carregarPerfil]);

  const formatarData = (dataString) => {
    if (!dataString) return 'Não informado';
    try {
      const data = new Date(dataString);
      const meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
      return `${meses[data.getMonth()]} ${data.getFullYear()}`;
    } catch {
      return dataString;
    }
  };

  const dadosExibicao = perfil || user || {};
  const estatisticas = perfil?.estatisticas || {};

  const infoItems = [
    { icon: 'mail', label: 'Email', value: dadosExibicao?.email || 'Não informado' },
    { icon: 'phone', label: 'Telefone', value: dadosExibicao?.telefone || 'Não informado' },
    { icon: 'calendar', label: 'Membro desde', value: formatarData(dadosExibicao?.membro_desde) },
    { icon: 'award', label: 'Plano', value: dadosExibicao?.plano?.nome || 'Sem plano' },
  ];

  const statsData = [
    { label: 'Check-ins', value: String(estatisticas.total_checkins || 0), icon: 'check-circle', color: colors.success },
    { label: 'Este mês', value: String(estatisticas.checkins_mes || 0), icon: 'calendar', color: colors.info },
    { label: 'Sequência', value: String(estatisticas.sequencia_dias || 0), icon: 'zap', color: colors.primary },
  ];

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.loadingText}>Carregando perfil...</Text>
      </View>
    );
  }

  if (error && !perfil) {
    return (
      <View style={styles.errorContainer}>
        <Feather name="alert-circle" size={64} color={colors.error} />
        <Text style={styles.errorText}>{error}</Text>
        <TouchableOpacity style={styles.retryButton} onPress={carregarPerfil}>
          <Text style={styles.retryButtonText}>Tentar novamente</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <ScrollView 
      style={styles.container} 
      showsVerticalScrollIndicator={false}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={onRefresh}
          tintColor={colors.primary}
          colors={[colors.primary]}
        />
      }
    >
      {/* Header com foto */}
      <View style={styles.header}>
        <View style={styles.profileImageContainer}>
          {dadosExibicao?.foto_base64 ? (
            <Image
              source={{ uri: `data:image/jpeg;base64,${dadosExibicao.foto_base64}` }}
              style={styles.profileImage}
            />
          ) : (
            <Image
              source={{ uri: `https://ui-avatars.com/api/?name=${encodeURIComponent(dadosExibicao?.nome || 'User')}&background=FF6B35&color=fff&size=200` }}
              style={styles.profileImage}
            />
          )}
          <TouchableOpacity style={styles.editPhotoButton}>
            <Feather name="camera" size={16} color={colors.textLight} />
          </TouchableOpacity>
        </View>

        <Text style={styles.userName}>{dadosExibicao?.nome || 'Usuário'}</Text>
        <Text style={styles.userEmail}>{dadosExibicao?.email || ''}</Text>
        
        {dadosExibicao?.plano && (
          <View style={styles.badge}>
            <Feather name="star" size={14} color={colors.primary} />
            <Text style={styles.badgeText}>{dadosExibicao.plano.nome}</Text>
          </View>
        )}

        {dadosExibicao?.tenant && (
          <Text style={styles.tenantName}>{dadosExibicao.tenant.nome}</Text>
        )}
      </View>

      <View style={styles.content}>
        {/* Stats */}
        <View style={styles.statsContainer}>
          {statsData.map((stat, index) => (
            <View key={index} style={styles.statItem}>
              <View style={[styles.statIcon, { backgroundColor: stat.color + '15' }]}>
                <Feather name={stat.icon} size={20} color={stat.color} />
              </View>
              <Text style={styles.statValue}>{stat.value}</Text>
              <Text style={styles.statLabel}>{stat.label}</Text>
            </View>
          ))}
        </View>

        {/* Informações */}
        <Text style={styles.sectionTitle}>Informações Pessoais</Text>
        
        <View style={styles.infoCard}>
          {infoItems.map((item, index) => (
            <View 
              key={index} 
              style={[
                styles.infoItem,
                index < infoItems.length - 1 && styles.infoItemBorder
              ]}
            >
              <View style={styles.infoLeft}>
                <View style={styles.infoIcon}>
                  <Feather name={item.icon} size={18} color={colors.primary} />
                </View>
                <View>
                  <Text style={styles.infoLabel}>{item.label}</Text>
                  <Text style={styles.infoValue}>{item.value}</Text>
                </View>
              </View>
              <Feather name="chevron-right" size={20} color={colors.gray400} />
            </View>
          ))}
        </View>

        {/* Ações */}
        <TouchableOpacity style={styles.editButton} activeOpacity={0.8}>
          <Feather name="edit-2" size={18} color={colors.textLight} />
          <Text style={styles.editButtonText}>Editar Perfil</Text>
        </TouchableOpacity>

        <View style={{ height: 40 }} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.backgroundSecondary,
  },
  loadingContainer: {
    flex: 1,
    backgroundColor: colors.backgroundSecondary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loadingText: {
    fontSize: 16,
    color: colors.textSecondary,
    marginTop: 16,
  },
  errorContainer: {
    flex: 1,
    backgroundColor: colors.backgroundSecondary,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 40,
  },
  errorText: {
    fontSize: 16,
    color: colors.error,
    textAlign: 'center',
    marginTop: 16,
  },
  retryButton: {
    marginTop: 20,
    paddingHorizontal: 24,
    paddingVertical: 12,
    backgroundColor: colors.primary,
    borderRadius: 12,
  },
  retryButtonText: {
    color: colors.textLight,
    fontWeight: '600',
  },
  // Header
  header: {
    backgroundColor: colors.background,
    alignItems: 'center',
    paddingVertical: 30,
    paddingHorizontal: 20,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  profileImageContainer: {
    position: 'relative',
    marginBottom: 16,
  },
  profileImage: {
    width: 100,
    height: 100,
    borderRadius: 50,
    borderWidth: 4,
    borderColor: colors.primary,
  },
  editPhotoButton: {
    position: 'absolute',
    bottom: 0,
    right: 0,
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 3,
    borderColor: colors.background,
  },
  userName: {
    fontSize: 22,
    fontWeight: '700',
    color: colors.text,
    marginBottom: 4,
  },
  userEmail: {
    fontSize: 14,
    color: colors.textSecondary,
    marginBottom: 12,
  },
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.primary + '15',
    paddingHorizontal: 14,
    paddingVertical: 6,
    borderRadius: 20,
    gap: 6,
  },
  badgeText: {
    color: colors.primary,
    fontSize: 13,
    fontWeight: '600',
  },
  tenantName: {
    fontSize: 13,
    color: colors.textMuted,
    marginTop: 8,
  },
  // Content
  content: {
    padding: 16,
  },
  // Stats
  statsContainer: {
    flexDirection: 'row',
    backgroundColor: colors.background,
    borderRadius: 16,
    padding: 16,
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  statItem: {
    flex: 1,
    alignItems: 'center',
  },
  statIcon: {
    width: 44,
    height: 44,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
  },
  statValue: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.text,
  },
  statLabel: {
    fontSize: 12,
    color: colors.textSecondary,
    marginTop: 2,
  },
  // Section Title
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 12,
  },
  // Info Card
  infoCard: {
    backgroundColor: colors.background,
    borderRadius: 16,
    overflow: 'hidden',
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  infoItem: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 16,
  },
  infoItemBorder: {
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  infoLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  infoIcon: {
    width: 40,
    height: 40,
    borderRadius: 12,
    backgroundColor: colors.primary + '15',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 14,
  },
  infoLabel: {
    fontSize: 12,
    color: colors.textSecondary,
  },
  infoValue: {
    fontSize: 15,
    color: colors.text,
    fontWeight: '500',
    marginTop: 2,
  },
  // Edit Button
  editButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.primary,
    borderRadius: 14,
    paddingVertical: 16,
    gap: 8,
    shadowColor: colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 4,
  },
  editButtonText: {
    color: colors.textLight,
    fontSize: 16,
    fontWeight: '600',
  },
});
