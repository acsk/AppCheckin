import { Redirect } from 'expo-router';

// A atualização cadastral agora fica em Configurações do app
export default function AtualizacaoCadastralRedirect() {
  return <Redirect href="/app-mobile" />;
}
