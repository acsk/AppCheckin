import { StyleSheet } from 'react-native';

export default StyleSheet.create({
  container: { 
    flex: 1, 
    backgroundColor: '#0b0f19' 
  },
  bg: { 
    flex: 1, 
    alignItems: 'center', 
    justifyContent: 'center' 
  },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(5, 7, 12, 0.5)',
  },
  logo: { 
    width: 220, 
    height: 120 
  },
});
