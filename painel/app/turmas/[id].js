import { useLocalSearchParams } from 'expo-router';
import FormTurmaScreen from '../../src/screens/turmas/FormTurmaScreen';

export default function EditarTurma() {
  const { id } = useLocalSearchParams();
  return <FormTurmaScreen turmaId={id} />;
}
