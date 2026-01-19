import { useLocalSearchParams } from 'expo-router';
import FormProfessorScreen from '../../src/screens/professores/FormProfessorScreen';

export default function EditarProfessor() {
  const { id } = useLocalSearchParams();
  return <FormProfessorScreen professorId={id} />;
}
