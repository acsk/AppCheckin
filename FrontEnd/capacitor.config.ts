import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.appcheckin',
  appName: 'AppCheckin',
  webDir: 'dist/appcheckin-frontend',
  bundledWebRuntime: false,
  server: {
    androidScheme: 'https'
  }
};

export default config;
