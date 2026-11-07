import React from 'react';
import LayoutBase from '../../components/LayoutBase';
import { PageHeader } from '../../components/ck';
import PreMatriculaPanel from '../superadmin/PreMatriculaPanel';

export default function PreMatriculaScreen() {
  return (
    <LayoutBase title="Pré-matrículas" pageHeader={false}>
      <PageHeader
        title="Pré-matrículas"
        description="Revise os pedidos dos alunos, ajuste plano e horários quando necessário e aprove ou reprove."
      />
      <PreMatriculaPanel modoAcademia />
    </LayoutBase>
  );
}
