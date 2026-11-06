# Pré-matrícula de migração

O Super Admin configura o fluxo por academia em **App Mobile →
Pré-matrícula de migração**. A tela permite criar, editar e excluir a
configuração da academia. A configuração define se o fluxo está ativo e se o
envio é obrigatório no app. Quando ativo, o pré-cadastro aparece para os alunos
com vínculo ativo na academia selecionada, tenham ou não matrícula ativa.
O app lista os planos ativos de valor zero, sem ocultar uma opção cuja
frequência ainda não tenha dias distintos suficientes na grade. Nesse caso,
informa ao aluno que a academia precisa disponibilizar mais horários. Para
ativar o fluxo, ao menos um plano precisa ter uma grade compatível.

O app mostra a descrição e a frequência dos planos elegíveis, sem exibir preço
ou o termo `FREE`. O aluno escolhe uma turma em cada dia da semana, sem repetir
dias, e pode informar o dia de vencimento (1 a 31), que é opcional. O vínculo
ativo de aluno à academia continua obrigatório, mesmo para quem ainda não tem
matrícula.

## Revisão e efeitos

Cada aluno mantém no máximo uma solicitação pendente ou aprovada por academia.
Uma solicitação reprovada pode ser enviada novamente. A fila do painel abre no
filtro **Aguardando aprovação**; filtros adicionais permitem consultar pedidos
aprovados, reprovados ou todos. O Super Admin pode aprovar ou reprovar, com
observação opcional. A decisão não cria matrícula,
cobrança nem reserva vaga; após revisar, a matrícula deve ser criada
manualmente. O plano e os dados da turma são registrados como estavam no envio,
para que uma edição posterior do plano ou da turma não altere o histórico.

## Rotas

As rotas abaixo são autenticadas e usam o tenant selecionado no JWT para o app:

- `GET /v2/mobile/pre-matricula`: retorna a configuração aplicável, os planos e
  turmas elegíveis, ou o estado da solicitação atual.
- `POST /v2/mobile/pre-matricula`: recebe `plano_id`, `grade` (lista de
  `turma_fixa_id` e `dia_semana`) e `dia_vencimento` opcional (`null` ou 1–31).

As rotas do painel exigem autenticação de Super Admin:

- `GET /v2/superadmin/pre-matriculas?tenant_id={id}`: configuração e fila.
- `PUT /v2/superadmin/pre-matriculas/configuracao`: grava `tenant_id`, `ativa`,
  `obrigatoria`. A pré-matrícula é sempre oferecida aos alunos vinculados à
  academia selecionada, independentemente de possuírem matrícula ativa.
- `DELETE /v2/superadmin/pre-matriculas/configuracao?tenant_id={id}`: remove a
  configuração e desativa novos pedidos, preservando as solicitações recebidas.
- `POST /v2/superadmin/pre-matriculas/{id}/aprovar` e
  `POST /v2/superadmin/pre-matriculas/{id}/reprovar`: recebem `tenant_id` e
  `observacao` opcional.

A migration `2026_11_06_190000_pre_matriculas_migracao.php` cria as tabelas da
configuração e das solicitações. Execute `php artisan migrate --force` antes de
publicar a API, o painel e o app móvel.

Testes: `php vendor/bin/phpunit tests/Feature/PreMatriculaMigracaoTest.php`.
