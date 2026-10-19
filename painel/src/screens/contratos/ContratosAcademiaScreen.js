import { useEffect } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';

// Rota antiga /contratos/academia?id=: a lista de contratos filtrada pela academia substitui esta tela
export default function ContratosAcademiaScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  useEffect(() => {
    router.replace(id ? `/contratos?academia=${id}` : '/contratos');
  }, [id]);
  return null;
}
