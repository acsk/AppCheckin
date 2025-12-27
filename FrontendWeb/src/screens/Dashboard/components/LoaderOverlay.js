import React from 'react';
import { View, ActivityIndicator } from 'react-native';
import styles from '../styles';

export default function LoaderOverlay() {
  return (
    <View style={styles.loaderOverlay}>
      <ActivityIndicator size="large" color="#f97316" />
    </View>
  );
}
