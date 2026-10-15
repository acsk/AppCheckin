import React from "react";
import Modal from "./Modal";

type Props = { visible: boolean; message: string; onClose: () => void };

export default function PendingPaymentModal({ visible, message, onClose }: Props) {
  return (
    <Modal
      visible={visible}
      variant="dialog"
      title="Pagamento pendente"
      eyebrow="Sua matrícula"
      description={message}
      icon="alert-triangle"
      primary={{ label: "OK, entendi", onPress: onClose }}
      onClose={onClose}
    />
  );
}
