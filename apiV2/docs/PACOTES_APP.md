# Pacotes pelo app

Um pacote junta várias pessoas num pagamento só (ex.: plano família). Além da contratação pelo
painel, o pacote pode ser vendido e renovado pelo app.

## Venda pelo app (por pacote)

`pacotes.venda_app` (painel → Pacotes → Editar pacote → "Venda pelo app"):

| Valor | Quem vê no app |
|---|---|
| `nao` (padrão) | Ninguém: só o admin contrata pelo painel |
| `liberados` | Só os alunos em `pacote_liberacoes` (painel → "Alunos liberados no app"), com validade opcional. Ex.: depois de uma negociação |
| `todos` | Todos os alunos da academia |

## Fluxo

1. **Vitrine** — aba Planos, seção "Pacotes" (`GET /v2/mobile/pacotes/disponiveis`).
2. **Contratar** — `POST /v2/mobile/pacotes/{pacoteId}/contratar`: cria o contrato com o aluno como
   pagante e sem os demais (`pacote_contratos.origem = app`). Contrato pendente do mesmo pacote é reaproveitado.
3. **Pagar** — `POST /v2/mobile/pacotes/contratos/{id}/cobranca` `{metodo_pagamento: pix|cartao, gateway?}`:
   soma as parcelas em aberto do período mais antigo do contrato e cobra o total **na matrícula do pagante**
   (`MAT-…`), no gateway da academia (Asaas ou Mercado Pago). A parcela do pagante fica registrada em
   `pacote_cobrancas`. Com duas contas ativas responde `409 ESCOLHA_GATEWAY` (o app pergunta).
4. **Baixa** — o webhook do Asaas (e a conferência agendada `asaas:sincronizar-matriculas`) dá baixa na
   matrícula do pagante e chama `PacoteConciliacaoService::conciliar`: as parcelas dos demais no mesmo
   vencimento são quitadas, as matrículas recalculadas e o contrato ativado (ou estendido).
   Mercado Pago e baixa manual: a conciliação roda nas listagens do painel e do app (`conciliarAbertos`).
5. **Participantes** — o contrato pago aparece em "Contratos ativos" com o selo "Pelo app" e o botão
   **Completar**: o admin associa os demais, que ganham matrícula até o fim da vigência.
6. **Renovação** — a partir de 7 dias antes do fim (`MobilePacoteVendaService::JANELA_RENOVACAO_DIAS`),
   o pagante vê "Renovar pacote" em Minhas assinaturas. `POST /v2/mobile/pacotes/contratos/{id}/renovar`
   gera uma parcela por participante (o **valor atual** do pacote rateado), vencendo no fim da vigência
   atual; depois o app chama a cobrança. Pago, o acesso de cada um vai até `max(pagamento, fim atual) + ciclo`.

As rotas exigem as funcionalidades `matriculas`/`pagamentos` ligadas (configuração remota do app).

## Regras importantes

- **Só a cobrança do pacote quita o grupo.** Uma baixa comum na parcela do pagante (cada um pagando a
  sua) não está em `pacote_cobrancas` e não quita ninguém.
- **Uma cobrança = um período.** Com mais de um período em aberto, cobra o mais antigo; a renovação só é
  gerada sem nada em aberto.
- **Cobrança anterior é cancelada** no provedor ao gerar outra (`CobrancaAnteriorService`).
- A baixa manual do admin (`darBaixaPacote`) **não gera mais** a "próxima parcela" por pessoa no valor
  cheio do plano: a renovação é do pacote inteiro, pelo pagante.
- O endpoint antigo `POST /mobile/pacotes/contratos/{id}/pagar` (builds antigos do app) usa a cobrança nova
  quando a academia está no Asaas (checkout; sem cartão, a fatura do PIX), devolvendo `payment_url` como antes.
  No Mercado Pago segue o fluxo antigo (preferência `PAC-…`, baixa pela API Slim).

## Arquivos

- `app/Services/Mobile/MobilePacoteVendaService.php` — vitrine, contratar, cobrar, renovar
- `app/Services/Pacote/PacoteConciliacaoService.php` — baixa do pagante → grupo
- `app/Services/Admin/AdminPacoteLiberacaoService.php` — alunos liberados
- `database/migrations/2026_10_26_120000_pacote_venda_app.php`
- App: `src/components/PacotesVitrine.tsx`, `src/hooks/usePacotePagamento.tsx`
- Painel: `src/components/ck/pacotes.js` (`PacoteModal`, `PacoteLiberacoesModal`), `src/screens/pacotes/PacotesScreen.js`
- Testes: `tests/Feature/PacoteVendaAppTest.php`
