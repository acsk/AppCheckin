import React, { useEffect } from 'react';
import { useRouter } from 'expo-router';

export default function IndexRoute() {
  const router = useRouter();

  useEffect(() => {
    router.replace('/dashboard');
  }, [router]);

  return null;
}
