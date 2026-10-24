import { Redirect } from 'expo-router';

// A atualização cadastral agora fica na tela App mobile
export default function AtualizacaoCadastralRedirect() {
  return <Redirect href="/app-mobile" />;
}
