import React, { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import { authService } from '../../services/authService';
import LayoutBase from '../../components/LayoutBase';
import MeuPlanoPainel from './MeuPlanoPainel';

export { openUrl } from './MeuPlanoPainel';

export default function MeuPlanoScreen() {
  const router = useRouter();
  const [ok, setOk] = useState(false);

  useEffect(() => {
    (async () => {
      const user = await authService.getCurrentUser();
      if (!user || user.papel_id !== 3) {
        router.replace('/');
        return;
      }
      setOk(true);
    })();
  }, []);

  return (
    <LayoutBase title="Plano do Check-in" pageHeader={false}>
      {ok ? <MeuPlanoPainel /> : null}
    </LayoutBase>
  );
}
