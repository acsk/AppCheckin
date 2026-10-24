import { Redirect } from 'expo-router';

// Mantém os links antigos apontando para a página dedicada.
export default function AtualizacaoCadastralRedirect() {
  return <Redirect href="/app-mobile/atualizacao-cadastral" />;
}
