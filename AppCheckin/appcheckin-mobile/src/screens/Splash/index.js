import React from 'react';
import { ImageBackground, Image, StatusBar, View } from 'react-native';
import styles from './styles';

export default function Splash() {
  return (
    <View style={styles.container}>
      <StatusBar barStyle="light-content" />
      <ImageBackground 
        source={require('../../../assets/img/bg.png')} 
        style={styles.bg} 
        resizeMode="cover"
      >
        <View style={styles.overlay} />
        <Image 
          source={require('../../../assets/img/app.png')} 
          style={styles.logo} 
          resizeMode="contain" 
        />
      </ImageBackground>
    </View>
  );
}
