import React from 'react';
import BaixaPagamentoForm from './BaixaPagamentoForm';

export default function BaixaPagamentoPlanoModal({ visible, pagamento, ...props }) {
  return visible && pagamento ? <BaixaPagamentoForm key={pagamento.pagamento_id || pagamento.id || pagamento.conta_id} pagamento={pagamento} plano {...props} /> : null;
}
