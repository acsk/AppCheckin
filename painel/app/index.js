import React from 'react';
import { Redirect } from 'expo-router';

export default function IndexRoute() {
  // Redireciona para o dashboard (pasta dashboard/index.js)
  return <Redirect href="/dashboard/" />;
}
