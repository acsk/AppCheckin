import { useColorScheme } from '@/hooks/use-color-scheme';
import { colors } from '@/src/theme/colors';
import { Feather } from '@expo/vector-icons';
import { useState } from 'react';
import {
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';

// Importar as telas
import AccountScreen from './(tabs)/account';
import CheckinScreen from './(tabs)/checkin';

export default function MainApp() {
  const [currentTab, setCurrentTab] = useState('account');
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';

  const renderScreen = () => {
    switch (currentTab) {
      case 'account':
        return <AccountScreen />;
      case 'checkin':
        return <CheckinScreen />;
      default:
        return <AccountScreen />;
    }
  };

  return (
    <View style={[styles.container, { backgroundColor: isDark ? '#1a1a1a' : '#f5f5f5' }]}>
      {/* Conteúdo */}
      <View style={styles.content}>
        {renderScreen()}
      </View>

      {/* Navegação Customizada */}
      <View style={[
        styles.customNav,
        {
          backgroundColor: isDark ? '#1a1a1a' : '#fff',
          borderTopColor: isDark ? '#333' : '#e5e5e5',
        }
      ]}>
        <TouchableOpacity
          style={[
            styles.navItem,
            currentTab === 'account' && styles.navItemActive,
          ]}
          onPress={() => setCurrentTab('account')}
        >
          <Feather
            name="user"
            size={24}
            color={currentTab === 'account' ? colors.primary : '#999'}
          />
          <Text
            style={[
              styles.navLabel,
              {
                color: currentTab === 'account' ? colors.primary : '#999',
              },
            ]}
          >
            Minha Conta
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[
            styles.navItem,
            currentTab === 'checkin' && styles.navItemActive,
          ]}
          onPress={() => setCurrentTab('checkin')}
        >
          <Feather
            name="check-square"
            size={24}
            color={currentTab === 'checkin' ? colors.primary : '#999'}
          />
          <Text
            style={[
              styles.navLabel,
              {
                color: currentTab === 'checkin' ? colors.primary : '#999',
              },
            ]}
          >
            Checkin
          </Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  content: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  customNav: {
    flexDirection: 'row',
    borderTopWidth: 1,
    paddingBottom: 0,
    paddingTop: 4,
    paddingHorizontal: 0,
    height: 65,
  },
  navItem: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    gap: 4,
  },
  navItemActive: {
    opacity: 1,
  },
  navLabel: {
    fontSize: 12,
    fontWeight: '500',
    marginTop: 2,
  },
});
