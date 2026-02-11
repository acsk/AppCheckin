import React, { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import Login from '../src/screens/Login';
import { authService } from '../src/services/authService';

export default function LoginRoute() {
  const router = useRouter();
  const [checkingAuth, setCheckingAuth] = useState(true);

  useEffect(() => {
    let isActive = true;

    const checkAuth = async () => {
      try {
        const token = await authService.getToken();
        if (!isActive) return;
        if (token) {
          router.replace('/dashboard/');
          return;
        }
        setCheckingAuth(false);
      } catch (error) {
        console.error('❌ Erro ao verificar token na rota de login:', error);
        if (isActive) {
          setCheckingAuth(false);
        }
      }
    };

    checkAuth();

    return () => {
      isActive = false;
    };
  }, [router]);

  if (checkingAuth) {
    return null;
  }

  return <Login />;
}
