import React from 'react';
import BaixaPagamentoForm from './BaixaPagamentoForm';

export default function BaixaPagamentoModal({ visible, pagamento, ...props }) {
  return visible && pagamento ? <BaixaPagamentoForm key={pagamento.pagamento_id || pagamento.id} pagamento={pagamento} {...props} /> : null;
}
