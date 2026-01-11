import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Switch,
  Alert,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { colors } from '../theme/colors';

export default function MinhaContaScreen({ navigation, user, onLogout }) {
  const [notificacoes, setNotificacoes] = useState(true);
  const [lembreteTreino, setLembreteTreino] = useState(true);

  const handleLogout = () => {
    Alert.alert(
      'Sair da Conta',
      'Tem certeza que deseja sair?',
      [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Sair', style: 'destructive', onPress: onLogout },
      ]
    );
  };

  const handleDeleteAccount = () => {
    Alert.alert(
      'Excluir Conta',
      'Esta ação é irreversível. Todos os seus dados serão perdidos.',
      [
        { text: 'Cancelar', style: 'cancel' },
        { 
          text: 'Excluir', 
          style: 'destructive', 
          onPress: () => Alert.alert('Atenção', 'Entre em contato com sua academia para excluir sua conta.')
        },
      ]
    );
  };

  const menuSections = [
    {
      title: 'Preferências',
      items: [
        {
          icon: 'bell',
          label: 'Notificações',
          type: 'switch',
          value: notificacoes,
          onToggle: setNotificacoes,
          color: colors.primary,
        },
        {
          icon: 'clock',
          label: 'Lembrete de Treino',
          type: 'switch',
          value: lembreteTreino,
          onToggle: setLembreteTreino,
          color: colors.info,
        },
      ],
    },
    {
      title: 'Conta',
      items: [
        {
          icon: 'lock',
          label: 'Alterar Senha',
          type: 'link',
          color: colors.success,
          onPress: () => Alert.alert('Em breve', 'Esta funcionalidade estará disponível em breve.'),
        },
        {
          icon: 'shield',
          label: 'Privacidade',
          type: 'link',
          color: '#8B5CF6',
          onPress: () => Alert.alert('Em breve', 'Esta funcionalidade estará disponível em breve.'),
        },
      ],
    },
    {
      title: 'Suporte',
      items: [
        {
          icon: 'help-circle',
          label: 'Central de Ajuda',
          type: 'link',
          color: colors.warning,
          onPress: () => Alert.alert('Ajuda', 'Entre em contato com sua academia para obter suporte.'),
        },
        {
          icon: 'message-circle',
          label: 'Fale Conosco',
          type: 'link',
          color: colors.info,
          onPress: () => Alert.alert('Contato', 'Envie um email para suporte@appcheckin.com'),
        },
        {
          icon: 'info',
          label: 'Sobre o App',
          type: 'link',
          subtitle: 'Versão 1.0.0',
          color: colors.textSecondary,
          onPress: () => Alert.alert('AppCheckin', 'Versão 1.0.0\n\nDesenvolvido para facilitar seu check-in na academia.'),
        },
      ],
    },
  ];

  const renderItem = (item, index, isLast) => {
    return (
      <TouchableOpacity
        key={index}
        style={[styles.menuItem, !isLast && styles.menuItemBorder]}
        onPress={item.onPress}
        disabled={item.type === 'switch'}
        activeOpacity={0.7}
      >
        <View style={styles.menuItemLeft}>
          <View style={[styles.menuIcon, { backgroundColor: item.color + '15' }]}>
            <Feather name={item.icon} size={18} color={item.color} />
          </View>
          <View>
            <Text style={styles.menuItemLabel}>{item.label}</Text>
            {item.subtitle && (
              <Text style={styles.menuItemSubtitle}>{item.subtitle}</Text>
            )}
          </View>
        </View>

        {item.type === 'switch' ? (
          <Switch
            value={item.value}
            onValueChange={item.onToggle}
            trackColor={{ false: colors.gray200, true: colors.primary + '50' }}
            thumbColor={item.value ? colors.primary : colors.gray400}
          />
        ) : (
          <Feather name="chevron-right" size={20} color={colors.gray400} />
        )}
      </TouchableOpacity>
    );
  };

  return (
    <ScrollView style={styles.container} showsVerticalScrollIndicator={false}>
      {/* User Info Card */}
      <View style={styles.userCard}>
        <View style={styles.userAvatar}>
          <Text style={styles.userInitial}>{user?.nome?.charAt(0) || 'U'}</Text>
        </View>
        <View style={styles.userInfo}>
          <Text style={styles.userName}>{user?.nome || 'Usuário'}</Text>
          <Text style={styles.userEmail}>{user?.email || ''}</Text>
        </View>
      </View>

      {/* Menu Sections */}
      {menuSections.map((section, sectionIndex) => (
        <View key={sectionIndex} style={styles.section}>
          <Text style={styles.sectionTitle}>{section.title}</Text>
          <View style={styles.sectionCard}>
            {section.items.map((item, itemIndex) => 
              renderItem(item, itemIndex, itemIndex === section.items.length - 1)
            )}
          </View>
        </View>
      ))}

      {/* Logout Button */}
      <TouchableOpacity 
        style={styles.logoutButton} 
        onPress={handleLogout}
        activeOpacity={0.8}
      >
        <Feather name="log-out" size={20} color={colors.error} />
        <Text style={styles.logoutText}>Sair da Conta</Text>
      </TouchableOpacity>

      {/* Delete Account */}
      <TouchableOpacity 
        style={styles.deleteButton} 
        onPress={handleDeleteAccount}
        activeOpacity={0.8}
      >
        <Text style={styles.deleteText}>Excluir Conta</Text>
      </TouchableOpacity>

      <View style={{ height: 40 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.backgroundSecondary,
  },
  // User Card
  userCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.background,
    margin: 16,
    marginBottom: 8,
    padding: 16,
    borderRadius: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  userAvatar: {
    width: 56,
    height: 56,
    borderRadius: 16,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 14,
  },
  userInitial: {
    fontSize: 24,
    fontWeight: '700',
    color: colors.textLight,
  },
  userInfo: {
    flex: 1,
  },
  userName: {
    fontSize: 17,
    fontWeight: '600',
    color: colors.text,
  },
  userEmail: {
    fontSize: 13,
    color: colors.textSecondary,
    marginTop: 2,
  },
  // Section
  section: {
    marginHorizontal: 16,
    marginTop: 16,
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textSecondary,
    marginBottom: 8,
    marginLeft: 4,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  sectionCard: {
    backgroundColor: colors.background,
    borderRadius: 16,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  menuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 14,
  },
  menuItemBorder: {
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  menuItemLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  menuIcon: {
    width: 38,
    height: 38,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  menuItemLabel: {
    fontSize: 15,
    color: colors.text,
    fontWeight: '500',
  },
  menuItemSubtitle: {
    fontSize: 12,
    color: colors.textMuted,
    marginTop: 2,
  },
  // Buttons
  logoutButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.error + '10',
    marginHorizontal: 16,
    marginTop: 24,
    padding: 16,
    borderRadius: 14,
    gap: 8,
  },
  logoutText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.error,
  },
  deleteButton: {
    alignItems: 'center',
    marginTop: 16,
    padding: 12,
  },
  deleteText: {
    fontSize: 14,
    color: colors.textMuted,
    textDecorationLine: 'underline',
  },
});
