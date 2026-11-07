# Pré-matrícula de migração

O Super Admin habilita o pré-cadastro por academia. O aluno escolhe um plano
gratuito de migração, os dias e horários e o dia de vencimento (obrigatório).
O Admin da academia revisa o pedido e, ao aprovar, a API cria a matrícula no
plano gratuito. Ao fim do período gratuito, o aluno pode contratar o plano pago
vinculado.

## 1. Configuração (Super Admin)

Em **App Mobile → Pré-matrícula de migração**, o Super Admin seleciona a
academia e define:

- se o fluxo está ativo e se o envio é obrigatório no app;
- o público elegível: sem matrícula ativa, com matrícula ativa ou ambos;
- a **contagem do período gratuito** (ver seção 5): a partir do dia de
  vencimento do aluno (padrão) ou a partir da aprovação;
- os **vínculos FREE → pago**: cada plano gratuito de migração aponta para um
  plano pago da mesma academia, modalidade e frequência semanal.

O vínculo é explícito, por academia e por ID de plano, e cada plano gratuito
tem no máximo um plano pago. O gratuito deve estar ativo, ter valor zero e aulas
semanais; o pago deve estar ativo e ter valor maior que zero. A ativação exige
ao menos um vínculo válido com turmas fixas em dias distintos suficientes para
a frequência. Apenas planos com vínculo válido são oferecidos aos alunos; se um
plano do par for desativado ou alterado, o vínculo deixa de ser oferecido até
ser corrigido (o painel sinaliza). Planos com grade insuficiente continuam
visíveis no app, com aviso e envio indisponível para a opção.

Excluir a configuração bloqueia novos pedidos e preserva pedidos, vínculos
gravados nas matrículas e as matrículas já criadas.

> Academias configuradas antes dos vínculos precisam cadastrá-los: sem vínculo
> o app não oferece planos.

## 2. Envio pelo aluno

O aluno precisa de vínculo ativo com a academia e pertencer ao público
elegível. O app mostra descrição e frequência do plano, sem preço nem o termo
`FREE`. O aluno escolhe uma turma por dia distinto até completar a frequência
e informa o dia de vencimento (1 a 31, obrigatório). A tela avisa que o acesso inicial
é gratuito por tempo limitado e que a continuidade depende de contratação.

O envio cria uma solicitação **Aguardando aprovação** e não reserva vaga. Cada
aluno tem no máximo uma solicitação em andamento por academia: pendente, ou
aprovada cuja matrícula não foi cancelada. Reprovadas, excluídas e aprovadas
com matrícula cancelada permitem novo envio.

### Dependentes

O pedido pertence a quem fará as aulas; o responsável apenas envia.

- **Perfil do dependente ativo:** o pedido sai em nome do dependente.
- **Perfil do responsável:** a tela mostra **Para quem?** com o próprio
  responsável e cada dependente. Cada pessoa tem o próprio pedido e status. O
  envio por um dependente cria o vínculo dele com a academia (como ao entrar no
  perfil) e grava `enviada_por_id`.
- **Login do dependente por PIN:** pode preencher e enviar o próprio pedido, mas
  não contrata plano (`COMPRA_SO_RESPONSAVEL`); o app orienta pedir ao
  responsável.
- **Fim do período gratuito do dependente:** quem contrata é o responsável, no
  perfil do dependente (Dependentes → entrar no perfil). Do perfil do
  responsável, o app leva à tela de Dependentes.
- **Segurança:** o `usuario_id` enviado pelo app só é aceito se for o próprio
  perfil ou um dependente do usuário do token; perfil de dependente não envia
  por outros. Caso contrário, 403.
- **Painel:** a linha mostra "Dependente", o responsável (nome, telefone,
  e-mail) e quem enviou. A aprovação cria a matrícula do dependente.
- **Pedido no aluno errado:** reprove com observação, exclua o pedido e reenvie
  para a pessoa certa.

## 3. Revisão e aprovação (Admin da academia)

No painel, **Gestão → Manutenção → Pré-matrículas** abre em **Aguardando
aprovação**; os filtros Aprovados, Reprovados e Todos mostram o histórico. Cada
linha mostra aluno (e-mail, telefone, CPF), data de envio, plano solicitado e
plano pago vinculado, turmas, vencimento, status e, quando houver, a matrícula
criada e seu status.

**Revisar** abre uma modal em que o Admin ajusta plano gratuito, turmas e
vencimento e decide: **Salvar ajuste** (mantém pendente), **Reprovar** ou
**Aprovar e criar matrícula** (grava antes os ajustes feitos na modal).

Na aprovação a API, em uma transação com bloqueio do pedido:

1. confirma que o aluno ainda está ativo na academia;
2. revalida o vínculo FREE → pago e a grade (turmas ativas, dias distintos);
3. cria a matrícula pelo fluxo administrativo existente (regras de coexistência,
   vagas e período de teste do plano gratuito, sem cobrança imediata);
4. grava `matricula_id` e `plano_destino_id` no pedido e `plano_destino_id` na
   matrícula, para que mudanças futuras na configuração não troquem o destino.

Se qualquer etapa falhar (por exemplo, falta de vaga ou conflito com outra
matrícula), nada é gravado e o pedido continua pendente. Aprovar duas vezes
retorna 409. A matrícula existente do aluno nunca é cancelada automaticamente.

## 4. Edição e exclusão após a decisão

Nos pedidos aprovados e reprovados, o Admin da academia e o Super Admin (na
academia selecionada) têm **Editar** e **Excluir**.

- **Editar** corrige plano, grade e vencimento da solicitação, mantendo o
  status. Em pedido aprovado, só o registro da solicitação muda: a matrícula,
  seu plano, grade e cobrança são alterados pela gestão de matrículas.
- **Excluir** pede confirmação e aplica exclusão lógica (`excluida_em` e
  `excluida_por`, mais registro em log). O pedido sai das listas e do status do
  aluno e não conta na duplicidade. A matrícula vinculada, suas vagas e a
  oferta do plano pago são preservadas. Pedidos pendentes não podem ser
  excluídos.

## 5. Fim do período gratuito e compra

A matrícula começa no plano gratuito, sem cobrança imediata, e o acesso vale
desde a aprovação. O fim do período gratuito segue a **contagem** configurada:

- `dia_vencimento` (padrão): o ciclo começa no próximo dia de vencimento do
  aluno (hoje, se for o próprio dia; em mês curto, o dia 31 vira o último dia) e
  dura a duração do plano gratuito (30 dias por padrão). O acesso é gratuito
  até o fim desse ciclo e a cobrança passa a valer nesse dia. Exemplo: aprovado
  em 06/11 com vencimento dia 10, o ciclo vai de 10/11 a 10/12 e o acesso
  gratuito vai até 10/12. Depois, o aluno contrata o plano pago no app, com o
  mesmo dia de vencimento. Assim todos entram no mês e a cobrança começa no
  seguinte.
- `aprovacao`: o período gratuito conta a partir da aprovação, pela duração do
  plano (aprovado em 06/11, gratuito por 30 dias).

A aprovação grava `data_vencimento`, `proxima_data_vencimento` e
`data_inicio_cobranca` da matrícula com essa data e devolve `acesso_gratuito_ate`.
Pedidos antigos sem vencimento não podem ser aprovados até que o dia seja
informado (a modal de revisão o exige).

Quando a matrícula ainda está no plano de valor zero e vence (ou fica
`vencida`), `GET /v2/mobile/pre-matricula` devolve `periodo_gratuito` com
`encerrado: true`, `fim` e `plano_destino` (`id` e `descricao`). O app mostra
**Período gratuito encerrado** e **Contratar plano**, que abre a tela de compra
existente (`/plano-detalhes`) do plano pago, com preço e condições antes do
aceite. Sem destino disponível, o app orienta a falar com a academia.

A compra reaproveita a matrícula vencida da modalidade (mesma turma e grade) e
encerra o período de teste; a ativação revalida grade e vaga pelas regras de
matrícula. Para matrículas de turma fixa, a contratação pelo app é liberada
apenas para o plano pago vinculado (`plano_destino_id`); outras trocas
continuam com a secretaria. A compra antes do vencimento do período gratuito
segue as regras existentes (matrícula ativa na modalidade) e não é ofertada
pelo app.

## Rotas

App (JWT do aluno; tenant do token):

- `GET /v2/mobile/pre-matricula[?usuario_id={dependente}]`: configuração
  aplicável, planos e turmas elegíveis, ou o estado do pedido (`status`,
  `matricula_id`, `periodo_gratuito`, `contratar`). No perfil do responsável
  traz também `dependentes` com a situação de cada um.
- `POST /v2/mobile/pre-matricula`: `plano_id`, `grade` (`turma_fixa_id`,
  `dia_semana`), `dia_vencimento` (1–31, obrigatório) e `usuario_id`
  opcional (dependente). O servidor resolve o plano pago pelo vínculo.

Super Admin:

- `GET /v2/superadmin/pre-matriculas?tenant_id={id}`: configuração (com
  `pares`), fila, planos, turmas e `catalogo` de planos gratuitos e pagos.
- `PUT /v2/superadmin/pre-matriculas/configuracao`: `tenant_id`, `ativa`,
  `obrigatoria`, `publico`, `contagem_gratuito` (`dia_vencimento` ou
  `aprovacao`) e `pares` (`plano_free_id`, `plano_pago_id`). Sem a
  chave `pares`, os vínculos atuais são mantidos; com ela, substituídos.
- `DELETE /v2/superadmin/pre-matriculas/configuracao?tenant_id={id}`.
- `PUT /v2/superadmin/pre-matriculas/{id}` (`tenant_id`, `plano_id`, `grade`,
  `dia_vencimento`) e `DELETE /v2/superadmin/pre-matriculas/{id}?tenant_id={id}`.
- `POST /v2/superadmin/pre-matriculas/{id}/aprovar|reprovar` com `tenant_id` e
  `observacao` opcional (mantidas por compatibilidade).

Admin da academia (tenant do token; permissão `matriculas.gerenciar`):

- `GET /v2/admin/pre-matriculas`: fila, planos de migração e turmas.
- `PUT /v2/admin/pre-matriculas/{id}`: ajusta plano, grade e vencimento (em
  qualquer status).
- `DELETE /v2/admin/pre-matriculas/{id}`: exclusão lógica de aprovado/reprovado.
- `POST /v2/admin/pre-matriculas/{id}/aprovar|reprovar` com `observacao`
  opcional; a aprovação devolve `matricula_id`.

A API valida em todas as operações que o pedido pertence à academia autorizada;
pedidos de outra academia ou excluídos respondem 404.

## Banco, publicação e erros

Migrations (execute `php artisan migrate --force` **antes** de publicar):

- `2026_11_06_190000_pre_matriculas_migracao.php`: configuração e pedidos;
- `2026_11_06_213500_add_matricula_id_to_pre_matriculas.php`: `matricula_id`;
- `2026_11_07_000000_pre_matriculas_enviada_por.php`: `enviada_por_id`;
- `2026_11_07_010000_pre_matricula_contagem_gratuito.php`: `contagem_gratuito`;
- `2026_11_06_230000_pre_matricula_planos_e_exclusao.php`: tabela
  `pre_matricula_planos`, `plano_destino_id`/`excluida_em`/`excluida_por` nos
  pedidos e `plano_destino_id` em `matriculas`. Matrículas aprovadas antes
  desta migration ficam sem destino (a oferta pós-teste só aparece para
  matrículas com destino confirmado).

As rotas de pré-matrícula respondem sempre em JSON, mesmo sem `Accept`, no
formato `{"message": "..."}` (validações incluem `errors`). Falhas inesperadas
retornam 500 com mensagem genérica e são registradas no log. Com o esquema
incompleto, o app recebe `pre_matricula: null` e o painel recebe 503.

Testes (PHP 8.4):
`php vendor/bin/phpunit tests/Feature/PreMatriculaMigracaoTest.php tests/Feature/PreMatriculaRespostasJsonTest.php`.
Cobrem vínculos, ativação, aprovação (vaga/conflito simulados, duplicada,
vínculo inválido), edição e exclusão nos dois status, isolamento entre
academias, duplicidade, fim do período gratuito e contrato JSON dos erros. A
compra pelo app e a disputa real pela última vaga dependem de MySQL/gateways e
não são exercitadas nesses testes.
