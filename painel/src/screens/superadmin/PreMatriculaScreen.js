import React from 'react';
import AppMobileLayout from './AppMobileLayout';
import PreMatriculaPanel from './PreMatriculaPanel';

export default function PreMatriculaScreen() {
  return (
    <AppMobileLayout
      title="Pré-matrícula de migração"
      description="Configure quem pode enviar uma pré-matrícula pelo app e revise as solicitações recebidas."
    >
      <PreMatriculaPanel />
    </AppMobileLayout>
  );
}
