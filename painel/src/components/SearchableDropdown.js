import React, { useState, useRef, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ScrollView,
  Modal,
  Pressable,
} from 'react-native';
import { Feather } from '@expo/vector-icons';

export default function SearchableDropdown({
  data = [],
  value,
  onChange,
  placeholder = 'Selecione...',
  searchPlaceholder = 'Buscar...',
  labelKey = 'nome',
  valueKey = 'id',
  subtextKey = null,
  disabled = false,
  error = false,
  renderItem = null,
  filterFunction = null,
  style,
}) {
  const [search, setSearch] = useState('');
  const [showDropdown, setShowDropdown] = useState(false);
  const [selectedItem, setSelectedItem] = useState(null);

  useEffect(() => {
    if (value && data.length > 0) {
      const item = data.find(item => {
        const itemValue = item[valueKey];
        if (itemValue === null || itemValue === undefined) return false;
        return itemValue === value || String(itemValue) === String(value);
      });
      setSelectedItem(item);
    } else {
      setSelectedItem(null);
    }
  }, [value, data, valueKey]);

  const filteredData = data.filter(item => {
    if (filterFunction) {
      return filterFunction(item, search);
    }
    
    const searchLower = search.toLowerCase();
    const labelMatch = item[labelKey]?.toLowerCase().includes(searchLower);
    const subtextMatch = subtextKey ? item[subtextKey]?.toLowerCase().includes(searchLower) : false;
    
    return labelMatch || subtextMatch;
  });

  const handleSelect = (item) => {
    onChange(item[valueKey]);
    setShowDropdown(false);
    setSearch('');
  };

  const handleClear = () => {
    onChange('');
    setSelectedItem(null);
    setSearch('');
  };

  const handleOpenDropdown = () => {
    if (!disabled) {
      setShowDropdown(true);
    }
  };

  return (
    <>
      <TouchableOpacity
        style={[
          styles.container,
          error && styles.containerError,
          disabled && styles.containerDisabled,
          style,
        ]}
        onPress={handleOpenDropdown}
        disabled={disabled}
        activeOpacity={0.7}
      >
        <View style={styles.content}>
          <Feather name="search" size={18} color="#6b7280" style={styles.icon} />
          <Text
            style={[
              styles.text,
              !selectedItem && styles.placeholder,
              disabled && styles.textDisabled,
            ]}
            numberOfLines={1}
          >
            {selectedItem ? selectedItem[labelKey] : placeholder}
          </Text>
          {selectedItem && !disabled && (
            <TouchableOpacity
              onPress={(e) => {
                e.stopPropagation();
                handleClear();
              }}
              style={styles.clearButton}
              hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
            >
              <Feather name="x" size={18} color="#6b7280" />
            </TouchableOpacity>
          )}
          {!selectedItem && (
            <Feather name="chevron-down" size={18} color="#6b7280" />
          )}
        </View>
      </TouchableOpacity>

      <Modal
        visible={showDropdown}
        transparent
        animationType="fade"
        onRequestClose={() => setShowDropdown(false)}
      >
        <Pressable
          style={styles.modalOverlay}
          onPress={() => setShowDropdown(false)}
        >
          <Pressable
            style={styles.modalContent}
            onPress={(e) => e.stopPropagation()}
          >
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>{placeholder}</Text>
              <TouchableOpacity
                onPress={() => setShowDropdown(false)}
                style={styles.closeButton}
              >
                <Feather name="x" size={24} color="#2b1a04" />
              </TouchableOpacity>
            </View>

            <View style={styles.searchContainer}>
              <Feather name="search" size={18} color="#6b7280" style={styles.searchIcon} />
              <TextInput
                style={styles.searchInput}
                placeholder={searchPlaceholder}
                placeholderTextColor="#9ca3af"
                value={search}
                onChangeText={setSearch}
                autoFocus
              />
              {search.length > 0 && (
                <TouchableOpacity
                  onPress={() => setSearch('')}
                  style={styles.searchClearButton}
                >
                  <Feather name="x-circle" size={18} color="#6b7280" />
                </TouchableOpacity>
              )}
            </View>

            <ScrollView style={styles.listContainer} showsVerticalScrollIndicator={false}>
              {filteredData.length === 0 ? (
                <View style={styles.emptyContainer}>
                  <Feather name="inbox" size={48} color="#d1d5db" />
                  <Text style={styles.emptyText}>Nenhum item encontrado</Text>
                </View>
              ) : (
                filteredData.map((item) => {
                  const isSelected = selectedItem && selectedItem[valueKey] === item[valueKey];
                  
                  return (
                    <TouchableOpacity
                      key={item[valueKey]}
                      style={[
                        styles.listItem,
                        isSelected && styles.listItemSelected,
                      ]}
                      onPress={() => handleSelect(item)}
                      activeOpacity={0.7}
                    >
                      {renderItem ? (
                        renderItem(item, isSelected)
                      ) : (
                        <View style={styles.listItemContent}>
                          <Text style={[styles.listItemText, isSelected && styles.listItemTextSelected]}>
                            {item[labelKey]}
                          </Text>
                          {subtextKey && item[subtextKey] && (
                            <Text style={styles.listItemSubtext}>{item[subtextKey]}</Text>
                          )}
                        </View>
                      )}
                      {isSelected && (
                        <Feather name="check" size={20} color="#f97316" />
                      )}
                    </TouchableOpacity>
                  );
                })
              )}
            </ScrollView>
          </Pressable>
        </Pressable>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: 'rgba(255,255,255,0.9)',
    borderWidth: 1,
    borderColor: 'rgba(43,26,4,0.2)',
    borderRadius: 10,
    minHeight: 48,
  },
  containerError: {
    borderColor: '#ef4444',
    borderWidth: 2,
  },
  containerDisabled: {
    backgroundColor: '#f3f4f6',
    opacity: 0.6,
  },
  content: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 12,
    gap: 8,
  },
  icon: {
    marginRight: 4,
  },
  text: {
    flex: 1,
    fontSize: 16,
    color: '#2b1a04',
  },
  placeholder: {
    color: '#9ca3af',
  },
  textDisabled: {
    color: '#6b7280',
  },
  clearButton: {
    padding: 4,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modalContent: {
    width: '100%',
    maxWidth: 500,
    maxHeight: '80%',
    backgroundColor: '#fff',
    borderRadius: 12,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.25,
    shadowRadius: 12,
    elevation: 8,
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2b1a04',
  },
  closeButton: {
    padding: 4,
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f9fafb',
    margin: 16,
    marginBottom: 8,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  searchIcon: {
    marginRight: 8,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 12,
    fontSize: 16,
    color: '#2b1a04',
  },
  searchClearButton: {
    padding: 4,
  },
  listContainer: {
    maxHeight: 400,
  },
  listItem: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  listItemSelected: {
    backgroundColor: 'rgba(249, 115, 22, 0.1)',
  },
  listItemContent: {
    flex: 1,
  },
  listItemText: {
    fontSize: 16,
    color: '#2b1a04',
    fontWeight: '500',
  },
  listItemTextSelected: {
    color: '#f97316',
    fontWeight: '600',
  },
  listItemSubtext: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 4,
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyText: {
    fontSize: 16,
    color: '#9ca3af',
    marginTop: 12,
  },
});
