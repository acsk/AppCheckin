# Limpeza de pendências abandonadas

O comando `cobrancas:limpar-pendentes` roda todo dia às 04:30. Ele **encerra**, sem apagar, o que o aluno
começou no app e não pagou. O registro fica no histórico como cancelado ou expirado.

| O quê | Quando | O que acontece |
|---|---|---|
| Simulação de troca de plano (`[MIGRACAO_SIMULADA]`) | parcela não paga há mais de 3 dias | Cobrança cancelada no Asaas e parcela cancelada (se fosse paga depois, aplicaria a troca fora de hora) |
| Checkout avulso (`assinaturas` pendente) | sem atualização há mais de 3 dias | Cobrança cancelada no Asaas e assinatura "expirada" (some de Minhas assinaturas). Se a matrícula ficou pendente sem nenhum pagamento, ela e as parcelas em aberto são canceladas |
| Assinatura com fidelidade não ativada | contrato `pendente_ativacao` há mais de 7 dias | Assinatura removida no Asaas, mensalidades em aberto canceladas, contrato cancelado |

**Segurança:**

- Antes de cancelar, a cobrança é conferida no Asaas. Se estiver **paga** (webhook perdido), nada é
  cancelado: a sincronização dá a baixa.
- Com o Asaas fora do ar, o item fica para o dia seguinte.
- Cobranças do Mercado Pago (sem consulta aqui) só são encerradas depois de 30 dias.
- Matrícula com qualquer pagamento confirmado nunca é cancelada.

**Opções:**

```bash
php artisan cobrancas:limpar-pendentes --simular          # só lista
php artisan cobrancas:limpar-pendentes --tenant=3         # uma academia
php artisan cobrancas:limpar-pendentes --dias=5 --dias-fidelidade=10
```

Código: `app/Services/LimpezaPendenciasService.php`, `RecorrenciaService::encerrarNaoAtivado`. Testes:
`tests/Feature/LimpezaPendenciasTest.php`.
