import React from 'react';
import AppMobileLayout from './AppMobileLayout';
import AtualizacaoCadastralPanel from './AtualizacaoCadastralPanel';

export default function AtualizacaoCadastralScreen() {
  return (
    <AppMobileLayout title="Atualização Cadastral" description="Gerencie os pedidos de revisão de cadastro, confirmação de e-mail e troca de senha dos usuários do app.">
      <div className="ck-mc">
        <AtualizacaoCadastralPanel />
      </div>
    </AppMobileLayout>
  );
}
