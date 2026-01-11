import { Redirect } from 'expo-router';

export default function IndexScreen() {
  // Redirecionar para account já que é a tela inicial
  return <Redirect href="/account" />;
}
