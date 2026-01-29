import React from 'react';
import { useLocalSearchParams } from 'expo-router';
import FormAlunoScreen from '../../src/screens/alunos/FormAlunoScreen';
import DetalheAlunoScreen from '../../src/screens/alunos/DetalheAlunoScreen';

export default function AlunoRoute() {
  const { edit } = useLocalSearchParams();
  
  // Se o parâmetro edit=true for passado, mostra o formulário de edição
  // Caso contrário, mostra os detalhes do aluno
  if (edit === 'true') {
    return <FormAlunoScreen />;
  }
  
  return <DetalheAlunoScreen />;
}
