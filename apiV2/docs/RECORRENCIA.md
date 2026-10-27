# Planos com fidelização (recorrência)

Assinatura **mensal** com preço promocional condicionado a uma permanência mínima. **Não é venda
parcelada**: cada mês é uma cobrança independente no Asaas, do valor da mensalidade. O total do contrato
nunca é cobrado nem reservado no limite do cartão.

## Cadastro (Planos → ciclo)

Todo ciclo continua vendável **à vista** (o ciclo inteiro numa cobrança, como sempre foi). Num ciclo de 2
meses ou mais, a academia pode **também** oferecer a assinatura mensal com fidelidade
(`plano_ciclos.cobranca_mensal = 1`). No app, o aluno escolhe entre "À vista" e "Assinatura" nos detalhes
do plano. A lista de planos segue com a compra à vista.

Campos da assinatura:
  - mensalidade = `valor ÷ meses` (o mesmo `valor_mensal_equivalente` já calculado);
  - referência = `planos.valor` (o mensal sem desconto);
  - `fidelizacao_meses` (padrão = meses do ciclo) e `politica_renovacao`;
  - `regularizacao_ativa`, desligada por padrão: o sistema só calcula e mostra;
  - `regularizacao_proporcional`, `arrependimento_dias` (mínimo 7, CDC art. 49);
  - `termos`: texto livre somado aos termos gerados. O botão "Usar termo modelo" preenche os 7 itens
    padrão (fidelização, cobrança recorrente, cancelamento, arrependimento, reposição, renovação, ciência),
    editáveis antes de salvar.

A flag antiga "Cobrança recorrente" (`permite_recorrencia`) agora se chama "Renovação automática no cartão
(Mercado Pago)". Ela só tem efeito com Mercado Pago e cartão (preapproval que cobra o ciclo inteiro a cada
período); no Asaas não muda nada.

Mudar o que o aluno aceita (valor, prazo, políticas, texto) sobe `termos_versao`. **Contratos já
assinados não mudam**: `contratos_recorrencia` congela valores, prazo, políticas e o texto aceito.

O plano mensal (1 mês) e todo o fluxo à vista seguem iguais.

## Contratação (app)

1. **Oferta:** `GET /mobile/recorrencia/ofertas?plano_id=` devolve valor, referência, prazo e o texto dos
   termos com o hash (`sha256`).
2. **Aceite:** o aluno marca o aceite e escolhe cartão ou PIX. `POST /mobile/recorrencia/contratar` exige o
   hash do texto que ele viu; se os termos mudaram no meio do caminho, responde `409 TERMOS_DESATUALIZADOS`
   com a oferta nova.
3. **Evidência:** ficam gravados o texto exato, a versão, o hash, a data, o usuário, o IP e o user agent.
4. **Matrícula:** criada com `tipo_cobranca = recorrente` (mesmas regras de reaproveitar ou encadear da
   compra) e contrato `pendente_ativacao`.
5. **Gateway:** assinatura Asaas `MONTHLY`. Na política "encerrar", `maxPayments` = meses de fidelização.
6. **Pagamento:** o app mostra a 1ª cobrança (fatura do cartão ou QR do PIX).

A compra à vista (`/mobile/comprar-plano`) aceita o mesmo ciclo normalmente. A migração de plano recusa
matrícula com contrato vivo (`409 ASSINATURA_FIDELIDADE_ATIVA`).

A tela de assinatura tem dois passos:
1. resumo, termos e aceite;
2. forma de pagamento (cartão ou PIX).

## Cobrança mensal e baixa

- O webhook do Asaas reconhece a cobrança pela `subscription` (ou referência `REC-`) e chama
  `RecorrenciaService::processarPagamento`.
- **Sempre confirma na API do Asaas.** O status do payload não é usado sozinho.
- **Idempotência:**
  - `recorrencia_cobrancas` tem `gateway_payment_id` único e (`contrato_id`, `numero`) único;
  - o número da mensalidade sai do mês do vencimento contado do início;
  - repetir o webhook responde `ja_paga` sem baixar de novo;
  - o contrato é travado (`lockForUpdate`) enquanto a cobrança é processada.
- Cada mensalidade vira uma parcela em `pagamentos_plano` (integra com relatórios e contas a receber).
  Baixa manual no painel também conta.
- **Acesso:** cada mensalidade paga libera um mês a partir do seu vencimento (`RecorrenciaAcesso`). Essa
  regra substitui a do ciclo à vista só para essas matrículas.

| Evento/status Asaas | Efeito |
|---|---|
| `PAYMENT_CREATED` | Registra a mensalidade (parcela em aberto) |
| `RECEIVED` / `CONFIRMED` | Baixa, contrato `ativo`, próxima cobrança +1 mês |
| `PAYMENT_CREDIT_CARD_CAPTURE_REFUSED` | Soma tentativa, contrato `pagamento_pendente` |
| `OVERDUE` | Mensalidade vencida, contrato `pagamento_pendente` |
| `REFUNDED`, `CHARGEBACK_*` | Mensalidade estornada (parcela cancelada) e o acesso é recalculado |
| `DELETED` | Mensalidade cancelada |

O comando diário `recorrencia:processar` (05:45) faz quatro coisas:

- confere as cobranças de cada assinatura no Asaas (cobre webhook perdido);
- aplica o fim da fidelização;
- marca `inadimplente` (5 dias de atraso, a mesma régua da matrícula);
- efetiva os cancelamentos agendados.

## Máquina de estados do contrato

```
pendente_ativacao → ativo ⇄ pagamento_pendente → inadimplente
(ativo | pagamento_pendente | inadimplente) → cancelamento_solicitado → cancelamento_agendado → cancelado
ativo → (fim da fidelização, política "encerrar") → encerrado
```

Toda transição vai para `recorrencia_eventos`. O histórico nunca é apagado.

## Fim da fidelização (renovação)

| Política | O que acontece |
|---|---|
| `encerrar` | O Asaas para na última mensalidade; o contrato encerra quando o acesso pago acaba |
| `mensal` | A assinatura passa ao valor de referência (`PUT /subscriptions`), sem prazo mínimo |
| `promocional` | Continua pelo mesmo valor, sem prazo mínimo |
| `nova_fidelizacao` | O app oferece um novo período; só começa com novo aceite (novo contrato, mesma assinatura) |

Nenhuma política renova a **obrigação** de permanência sem aceite.

## Cancelamento e regularização

Regularização = (referência − contratado) × mensalidades promocionais **pagas e já iniciadas** dentro da
fidelização (`App\Support\RecorrenciaCalculo`, em centavos inteiros). Exemplos: bimestral 1 mês = R$ 20;
trimestral 2 meses = R$ 50; semestral 4 meses = R$ 120.

**Zera quando:**

- está no prazo de arrependimento (a partir do aceite);
- a fidelização foi cumprida;
- o tipo é impossibilidade de prestar o serviço;
- a política não cobra (o valor fica só informativo);
- não há desconto.

**Nunca entram** meses futuros. Mensalidades vencidas em aberto aparecem à parte. O proporcional (opcional)
conta só os dias usados do último mês, quando a data efetiva corta o acesso antes do fim.

**Fluxo:**

1. O aluno (app) ou o admin pede o cancelamento.
2. A assinatura é removida no Asaas na hora, e as mensalidades futuras não pagas são canceladas.
3. O acesso vai até o fim do período pago (`cancelamento_agendado`).
4. Sem valor a cobrar, o pedido já fica confirmado.
5. Com regularização, um admin confirma: pode isentar total ou parcialmente, com motivo obrigatório.
6. Confirmado o valor, a regularização vira parcela em `pagamentos_plano`.
7. O pedido nunca depende de pagar antes.

A memória de cálculo completa fica em `cancelamentos_recorrencia.memoria_calculo`.

## Painel

**Financeiro → Recorrências**

- **Indicadores:** ativos, MRR, receita prevista (30 dias), em fidelização, fidelização terminando,
  cancelamentos solicitados, inadimplência, descontos concedidos, regularizações pendentes.
- **Lista:** filtros e paginação.
- **Detalhe:** resumo, cronograma, pagamentos, histórico, situação no Asaas, termos aceitos e o simulador
  (tipo, data efetiva e isenção) que confirma o cancelamento.

**Permissões:** leitura com `financeiro.ver`; cancelar e conferir no Asaas com `financeiro.baixar`.

## App

- **Detalhes do plano:** o ciclo com fidelidade mostra "R$ x/mês · fidelidade N meses" e o botão
  "Assinar" (termos, aceite e forma de pagamento).
- **Minhas assinaturas → Minha assinatura:**
  - valor, próximo vencimento, forma de pagamento, início e acesso;
  - fidelidade restante, pagamentos, condições de renovação e termos aceitos;
  - pagar a mensalidade em aberto, ou trocar o cartão pela fatura;
  - "Solicitar cancelamento" com a simulação antes;
  - aceitar uma nova fidelidade.

## Limitações conhecidas

- **Só Asaas.** O Mercado Pago (preapproval) ainda cobra o período inteiro a cada N meses e não é usado
  para fidelização.
- **Cartão no Asaas:** a 1ª mensalidade é paga pela fatura, e o Asaas debita as seguintes no mesmo cartão.
  Precisa ser **validado no sandbox** antes de divulgar. Trocar de cartão = pagar a próxima fatura com o
  novo cartão (não há tela de cartão no app; dado de cartão nunca passa pelo AppCheckin).
- **Ainda não implementado (regras da especificação):**
  - suspensão temporária da matrícula (7);
  - reativação de uma assinatura cancelada: o caminho é contratar de novo (8).
- **Troca de plano durante a fidelização (6):** é cancelar (com a regularização do contrato) e contratar
  de novo.
- **Cobrança da regularização:** a parcela é lançada, mas não é gerada uma cobrança no Asaas
  automaticamente. O admin cobra pelo fluxo de parcelas existente.
- **Builds antigos do app:** compram o ciclo à vista normalmente; a opção de assinatura só aparece no app novo.

## Arquivos

- Migration `2026_10_28_120000_recorrencia_fidelizacao.php`
- `app/Support/RecorrenciaCalculo.php` (cálculo e datas, sem banco)
- `app/Services/Recorrencia/`:
  - `RecorrenciaService` (contratar, gateway, renovação, cancelamento, rotina);
  - `RecorrenciaAcesso`;
  - `AdminRecorrenciaService`;
  - `MobileRecorrenciaService`.
- `AsaasService`: criarAssinatura, pagamentosDaAssinatura, atualizarValorAssinatura, cancelarAssinatura,
  pixDaCobranca.
- `AsaasWebhookService`: despacho das cobranças de assinatura.
- Rotas: `routes/v2/admin/recorrencias.php`; `/mobile/recorrencia/*` em `routes/api.php`.
- Comando `recorrencia:processar`.
- Painel: `src/screens/recorrencias/*`, `CicloModal` (Planos).
- App: `src/components/FidelidadeSheet.tsx`, `MinhaAssinaturaFidelidade.tsx`.
- Testes: `tests/Feature/RecorrenciaFidelizacaoTest.php` (19 cenários).
