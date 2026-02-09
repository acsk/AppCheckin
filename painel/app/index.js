import React from 'react';
import { Redirect } from 'expo-router';

export default function IndexRoute() {
  // Sempre redireciona para a tela de login
  return <Redirect href="/login" />;
}
