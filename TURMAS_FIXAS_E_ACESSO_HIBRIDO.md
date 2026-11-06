# Turmas fixas e acesso híbrido — AppCheckin

Documento consolidado em **06/11/2026**, com o escopo implementado, a correção da grade individual, as regras operacionais, a preservação dos dados e as validações realizadas até este momento.

## 1. Objetivo e evolução

O mesmo tenant pode trabalhar simultaneamente com matrículas de **horário livre** e **turma fixa**. A Aqua4Styles foi indicada como primeira escola a utilizar o modelo; não existe regra exclusiva para ela no código.

A primeira implementação vinculava a matrícula a uma turma inteira e associava planos disponíveis à turma. Após a revisão solicitada, o modelo foi corrigido:

- O plano, o ciclo e o valor contratado pertencem à **matrícula**, não à turma.
- A turma oferece sua grade recorrente; a secretaria escolhe os dias de cada aluno.
- Uma matrícula fixa pode combinar horários de turmas diferentes.
- A reposição é uma reserva pontual, separada da grade e da presença.

O cadastro público de alunos não foi alterado. A secretaria organiza plano e grade pelo painel.

## 2. Configurações e menu do tenant

Em **Configurações Gerais → Matrículas**, foi adicionado **Tipo padrão para novas matrículas**:

- Horário livre;
- Turma fixa.

O parâmetro `tipo_acesso_padrao` pré-seleciona o formulário de nova matrícula. A secretaria pode escolher outro tipo, e a decisão fica gravada em `matriculas.tipo_acesso`. Alterar o padrão não converte matrículas anteriores.

O menu **Turmas** do painel aparece quando o tenant:

1. possui alguma turma cadastrada; **ou**
2. utiliza turma fixa como padrão.

O menu **Turmas** é uma entrada própria na seção **Gestão**, separada de **Academia → Aulas**. Sua página é dedicada a listar, criar e editar turmas; cada cartão tem a ação **Editar** e o botão **Criar turma** abre um formulário novo, sem misturar a seleção de outra turma dentro dele. Agenda e presenças permanecem em **Aulas**.

As permissões da equipe continuam sendo exigidas. Um tenant de horário livre pode acessar a gestão dedicada pelo atalho **Gerenciar turmas** na área de **Aulas** para cadastrar a primeira turma. Não foi criada uma configuração extra só para exibir o menu.

## 3. Cadastro e geração das turmas

O cadastro mantém:

- nome;
- modalidade;
- professor;
- dias da semana;
- horário de início e fim;
- capacidade;
- atividade e configuração de check-in livre.

O campo **Planos disponíveis** foi removido da interface e das validações de contratação/vínculo. Uma mesma turma pode receber alunos de planos 1x, 2x e 3x por semana, com valores distintos.

Uma turma recorrente possui um horário e vários dias. Para horários diferentes, cadastre turmas distintas e combine os dias na grade do aluno.

As ocorrências são materializadas na agenda existente para um horizonte de 90 dias. O comando `turmas:gerar-aulas-fixas`, agendado diariamente, mantém esse horizonte. O cron Laravel deve continuar executando `schedule:run` a cada minuto.

Conflitos de agenda e falhas de geração são explícitos. Dias inativos e ocorrências já existentes são respeitados.

A edição da turma preserva a grade recorrente já vinculada e as configurações específicas das ocorrências. Para mudar dias/horários recorrentes, crie outra turma e transfira as grades explicitamente. A secretaria pode habilitar check-in livre em uma ocorrência específica.

## 4. Matrícula e grade individual

### Turma fixa

A secretaria seleciona o plano da matrícula e **um ou mais pares turma/dia da semana**, inclusive em turmas diferentes da mesma modalidade.

Exemplo de plano 2x:

| Dia | Horário | Turma |
|---|---|---|
| Terça | 07h | Turma da manhã |
| Quinta | 18h | Turma da tarde |

O terceiro horário semanal é recusado. Se a turma da manhã também oferece quinta, selecionar somente terça **não reserva quinta**.

São validados:

- tenant e modalidade do plano e das turmas;
- atividade das turmas;
- dias realmente oferecidos;
- ausência de pares duplicados;
- frequência semanal do plano;
- capacidade de cada dia/horário e ocorrências afetadas.

Uma frequência positiva em `planos.checkins_semanais` limita a quantidade de horários recorrentes. Zero preserva a regra de frequência ilimitada.

O painel exibe e permite editar a grade. O app exibe a grade individual e a próxima aula elegível.

### Horário livre

O fluxo anterior de escolha por disponibilidade foi preservado, com limites do plano, elegibilidade, janela e prevenção de duplicidade. A coexistência com matrículas fixas não transforma horários livres em grades obrigatórias.

### Matrículas pendentes

Matrículas pendentes não ocupam reserva fixa. A capacidade é verificada na contratação/edição e novamente na ativação ou confirmação do pagamento.

Se um pagamento for confirmado e não houver capacidade para ativar, a baixa é preservada e a matrícula fica em **Pago - aguardando vaga**. A secretaria pode ajustar a grade sem alterar o preço contratado; a sincronização financeira pode tentar a ativação novamente quando houver vaga.

## 5. Agenda, vagas e check-in

### Presença regular de aluno fixo

O backend autoriza somente aulas pertencentes aos dias e turmas da grade da matrícula ativa/vigente. A janela configurada de check-in continua obrigatória.

A presença utiliza a vaga previamente reservada. A ausência não libera essa vaga para outros alunos.

### Aula mista

Quando a ocorrência permite check-in livre:

```text
saldo livre =
  capacidade
  - alunos fixos vigentes para aquele dia
  - reposições confirmadas
  - alunos livres com presença
```

Cada aluno é contado uma única vez. Uma presença de aluno já reservado não ocupa uma segunda vaga.

Exemplo: capacidade 12, dez reservas fixas, nenhuma reposição → até dois alunos livres. Isso continua válido quando os alunos fixos estão ausentes.

Reduzir a capacidade abaixo das reservas/ocupação é recusado.

### Concorrência e duplicidade

Check-in, edição de grade, ativação e reposição utilizam transações e locks ordenados: grupos do tenant, ocorrência e aluno/matrícula conforme o fluxo.

O bloqueio conservador dos grupos do tenant evita inversões em operações com várias turmas, embora possa serializar mais operações do tenant. As consultas de presença e reservas usam leituras correntes sob lock, inclusive no MySQL, para não decidir a última vaga com um snapshot desatualizado.

As validações ficam no backend; não dependem apenas dos botões e contadores da interface.

## 6. Reposição manual pelo professor

Na lista de alunos da aula foi adicionada **Adicionar aluno para reposição**, separada da inclusão manual regular.

O professor responsável pela aula, ou administrador autorizado, seleciona um aluno elegível e o inclui naquela ocorrência:

1. O backend valida tenant, autorização, modalidade, matrícula ativa/vigente, aula ativa e não encerrada.
2. Verifica capacidade considerando reservas fixas, reposições e presenças livres.
3. Registra a reserva em `aula_reposicoes`.
4. O aluno passa a poder fazer check-in naquela ocorrência, dentro da janela.
5. A presença aparece no histórico como **Reposição**.

A inclusão não registra presença antecipadamente, não muda grade, plano, valor ou cobrança, e não libera vagas fixas de ausentes. Repetir a mesma reserva é idempotente; alunos já reservados regularmente ou presentes não recebem uma segunda reserva.

A reposição não consome os limites semanais/mensais regulares, mas mantém janela, prevenção de duplicidade diária e demais bloqueios aplicáveis.

O check-in manual regular e o resumo financeiro do aluno foram preservados. Não foi implementado cancelamento de reserva de reposição nesta etapa.

## 7. Cobrança e alterações de preço

O valor contratado da matrícula/ciclo continua sendo a base das cobranças existentes. Alterar o preço de tabela do plano vale para novas contratações, sem reescrever contratos ou parcelas emitidas.

Reajustes de contratos ativos exigem ação explícita. O agendamento automático de `reajuste:aplicar-renovacoes` foi removido. Marcações legadas de renovação são preservadas, mas não aplicadas sem autorização de reajuste.

Para os contratos avulsos abrangidos, o operador pode simular e confirmar:

```bash
/opt/alt/php84/usr/bin/php artisan planos:reajustar-contratos --tenant=ID --ciclo=ID --valor-anterior=100
/opt/alt/php84/usr/bin/php artisan planos:reajustar-contratos --tenant=ID --ciclo=ID --valor-anterior=100 --confirmar
```

Valores negociados, pacotes e contratos com fidelização não são abrangidos por essa ação.

A troca/contratação de plano para matrícula fixa é feita pela secretaria. O pagamento da mensalidade no app continua no fluxo existente.

Uma simulação de troca iniciada no app antes de a matrícula ser convertida para fixa não pode sobrescrever a grade no retorno do pagamento. A confirmação é recusada para conferência da secretaria, preservando o pagamento recebido.

## 8. Estrutura de dados e migrações

| Estrutura | Finalidade |
|---|---|
| `turmas_fixas` | Cadastro recorrente da turma |
| `turmas.turma_fixa_id` | Liga uma ocorrência à turma recorrente |
| `turmas.checkin_livre` | Permissão de acesso livre naquela ocorrência |
| `matriculas.tipo_acesso` | Tipo escolhido por matrícula |
| `matricula_grade` | Pares matrícula/turma/dia, com tenant |
| `matriculas.grade_revisao` | Sinaliza vínculos legados para revisão |
| `aula_reposicoes` | Reserva por ocorrência/aluno/matrícula |

### Primeira migração

[2026_11_06_100000_acesso_hibrido_aulas.php](apiV2/database/migrations/2026_11_06_100000_acesso_hibrido_aulas.php):

- cria a estrutura inicial de turmas recorrentes;
- adiciona o tipo de acesso à matrícula;
- define **horário livre** para os registros anteriores;
- mantém aulas anteriores abertas ao check-in livre;
- inclui o parâmetro padrão e o status de pagamento aguardando vaga;
- preserva valores, cobranças, status anteriores e histórico.

### Correção da grade individual

[2026_11_06_140000_grade_individual_reposicoes.php](apiV2/database/migrations/2026_11_06_140000_grade_individual_reposicoes.php):

- cria grade individual e reposições;
- copia **todos os dias** da turma do vínculo singular anterior;
- marca revisão quando a grade supera a frequência ou ela não pode ser identificada;
- não corta dias nem altera plano, valor, status ou check-ins.

As grades legadas excedentes permanecem válidas até revisão explícita. Novas alterações de grade/plano devem respeitar a frequência.

A coluna singular `matriculas.turma_fixa_id` e a antiga pivot `turma_fixa_planos` foram mantidas para compatibilidade/auditoria, mas não são a fonte da nova autorização por dia nem exigem planos associados à turma.

As migrações são conservadoras e irreversíveis por rollback destrutivo: restauração exige backup validado.

## 9. Superfícies implementadas

### Painel

- configuração de tipo padrão;
- menu e página de Turmas;
- turma sem seleção de planos;
- criação de matrícula com grade por dia e turma;
- editor compartilhado de grade;
- visualização da grade e alerta de revisão;
- manutenção do plano e valor contratado.

### App

- grade individual e próxima aula;
- autorização de aula fixa, livre ou reposição;
- indicação de reserva e saldo;
- seleção de aluno para reposição;
- histórico com identificação de reposição;
- preservação da inclusão manual regular e resumo financeiro.

### Backend

- validação de vínculos, frequência, vigência, janela e capacidade;
- geração de ocorrências recorrentes;
- reserva por dia e reposição por ocorrência;
- integração com ativação e sincronização financeira;
- prevenção transacional de superlotação e duplicidade;
- proteção dos valores contratados.

### Rotas principais

| Método | Rota | Uso |
|---|---|---|
| GET/POST | `/v2/admin/turmas-fixas` | Listar/criar turmas |
| PUT | `/v2/admin/turmas-fixas/{id}` | Editar turma |
| GET | `/v2/admin/matriculas/{id}/grade` | Consultar grade/revisão |
| PUT | `/v2/admin/matriculas/{id}/tipo-acesso` | Alterar tipo e grade |
| POST | `/v2/mobile/professor/reposicoes` | Reservar reposição |

Rotas administrativas exigem autenticação e tenant selecionado, com as permissões aplicáveis. A inclusão de reposição valida também a autorização do professor.

Payload de grade:

```json
{
  "tipo_acesso": "turma_fixa",
  "grade": [
    {"turma_fixa_id": 10, "dia_semana": 2},
    {"turma_fixa_id": 11, "dia_semana": 4}
  ]
}
```

Os IDs são ilustrativos. Dias usam ISO: segunda=1 até domingo=7. Para reposição, o payload usa `turma_id` da **ocorrência**, não o ID da turma recorrente, e `aluno_id`.

## 10. Critérios e evidências de validação

| Critério | Evidência obtida |
|---|---|
| Tenants anteriores continuam no horário livre | Testes de migração e fluxo legado |
| Tipos fixo/livre coexistem | Testes de elegibilidade e saldo misto |
| Trocar o padrão não altera matrículas anteriores | Teste de migração/persistência |
| Aluno fixo só acessa sua grade e janela | Testes de dias, turmas e janela |
| 12 vagas com dez fixos permitem dois livres, mesmo com ausência | Testes de capacidade/reservas |
| Última vaga não é ocupada por dois alunos | Testes concorrentes em MySQL isolado |
| Preço novo não altera contratos anteriores | Testes de preço, contratação e reajuste |
| Plano 2x admite terça 07h e quinta 18h em turmas distintas, mas não terceiro dia | Teste de grade individual |
| Uma turma admite planos 1x/2x/3x | Teste sem dependência da pivot de planos |
| Reposição de sábado não muda grade de terça/quinta | Teste de reserva pontual, presença e histórico |
| Migração preserva dias, valores e check-ins; excesso recebe revisão | Testes de migração legada |

Execuções registradas nesta implementação:

- suíte backend focada ampliada: **260 testes, 1.006 asserções, dois skips opt-in**, código de saída 0;
- concorrência MySQL isolada: **dois testes, 30 asserções**, código de saída 0;
- grade individual após refinamento do cenário de sábado: **sete testes, 47 asserções**, código de saída 0;
- verificação final de matrícula/capacidade/detalhe: **58 testes, 243 asserções**, código de saída 0;
- exportação web Expo do painel e mobile: concluída;
- lint mobile focado: zero erros, 26 avisos.
- seed do tenant 5 em SQLite em memória: **quatro testes, 19 asserções**, código de saída 0; verificação de formatação dos dois arquivos PHP aprovada.
- menu dedicado de Turmas: exportação web do painel aprovada e seis verificações de navegador com API simulada (menu de primeiro nível, listagem, criação, edição, formulário novo vazio e erro de carregamento impedindo gravação). Criação/edição verificaram os métodos POST/PUT e a releitura dos dados no servidor simulado; não acessaram produção.

Essas execuções têm escopos sobrepostos; suas contagens **não devem ser somadas**.

Comando focado:

```bash
php vendor/bin/phpunit --filter 'AcessoHibrido|GradeIndividual'
```

Os testes de concorrência são opt-in e exigem MySQL descartável/isolado. As instruções estão no [README da API](apiV2/README.md). Nunca execute essa suíte destrutiva no banco da aplicação.

Limitações conhecidas:

- a tentativa inicial de validação visual dos fluxos híbridos foi inconclusiva; posteriormente, a gestão dedicada de turmas foi validada no navegador com API simulada, sem homologação integrada com banco real;
- Android/iOS ainda precisam de homologação;
- gateways reais e o ambiente da Aqua4Styles não foram homologados nesta rodada;
- erros de mocks de `CentralComunicacaoTest` foram reproduzidos na base anterior, fora da correção;
- passar testes com fixtures não substitui a validação da experiência real após publicação.

## 11. Seed de três alunos — tenant 5

Foi solicitado um seed **sem execução** para o tenant 5:

[Tenant5TurmasFixasAlunosSeeder.php](apiV2/database/seeders/Tenant5TurmasFixasAlunosSeeder.php).

| Nome fictício | E-mail de teste |
|---|---|
| ALUNO TESTE TURMAS 01 | `appcheckin-tenant5-turmas-01@example.invalid` |
| ALUNO TESTE TURMAS 02 | `appcheckin-tenant5-turmas-02@example.invalid` |
| ALUNO TESTE TURMAS 03 | `appcheckin-tenant5-turmas-03@example.invalid` |

Os e-mails usam domínio reservado e não recebem mensagens. Não são criados CPF ou telefone fictícios que possam coincidir com dados de pessoas reais.

O seed:

- exige tenant 5 existente e ativo;
- cria usuários, fichas de aluno e papel ativo de aluno naquele tenant;
- reutiliza o repositório de cadastro;
- é idempotente e preserva senhas/dados em reexecuções;
- recusa colisões, desfazendo novos registros na transação;
- não cria nem altera turmas, planos, matrículas, grades ou cobranças;
- não foi incluído no `DatabaseSeeder`, evitando execução automática.

A senha deve ser fornecida no ambiente no momento da execução, com pelo menos 12 caracteres. Não há senha fixa no código.

```bash
# Na pasta apiV2, apenas quando desejar inserir os alunos:
read -r -s -p "Senha dos alunos de teste: " TURMAS_FIXAS_TEST_PASSWORD
echo
export TURMAS_FIXAS_TEST_PASSWORD
/opt/alt/php84/usr/bin/php artisan db:seed --class=Tenant5TurmasFixasAlunosSeeder --force
unset TURMAS_FIXAS_TEST_PASSWORD
```

Confirme o banco e o tenant antes de executar. Depois, pelo painel, contrate os planos apropriados e distribua os alunos entre grade fixa e horário livre. Isso permite exercitar contratação, reserva e cobrança pelas regras reais, sem escolher planos arbitrariamente no seed.

## 12. Publicação e roteiro de homologação

Até esta consolidação, o assistente **não executou migrações nem seed em produção** e não fez commit/push da correção da grade individual. A implementação inicial já estava no commit `3da5539d`; a correção subsequente ainda estava no worktree.

Publicação:

1. Fazer e validar backup do banco.
2. Publicar a API antes dos clientes.
3. Aplicar as migrações pendentes na ordem:

```bash
/opt/alt/php84/usr/bin/php artisan migrate --path=database/migrations/2026_11_06_100000_acesso_hibrido_aulas.php --force
/opt/alt/php84/usr/bin/php artisan migrate --path=database/migrations/2026_11_06_140000_grade_individual_reposicoes.php --force
/opt/alt/php84/usr/bin/php artisan optimize:clear
```

4. Confirmar cron e geração de ocorrências.
5. Publicar painel e app compatíveis com a nova grade.
6. Revisar matrículas sinalizadas.
7. Homologar:
   - menu/configuração no tenant;
   - criação de turmas e plano por matrícula;
   - seleção isolada de dias e limite semanal;
   - check-in na grade e recusa fora dela/janela;
   - acesso livre com reservas fixas ausentes;
   - reposição, presença e histórico;
   - pagamento/ativação quando a turma está cheia;
   - valores contratados e parcelas existentes;
   - experiência Android/iOS.

O PHP padrão do servidor relatado era 7.4; os comandos da API devem utilizar o executável PHP 8.4 indicado acima.

## 13. Referências de implementação

- [Regras de acesso, grade e vagas](apiV2/app/Services/AcessoHibridoService.php)
- [Cadastro de turma e vínculo de grade](apiV2/app/Services/Admin/AdminTurmaFixaService.php)
- [Criação e alteração de matrícula](apiV2/app/Services/Admin/AdminMatriculaService.php)
- [Reposição pelo professor](apiV2/app/Services/Mobile/MobileProfessorService.php)
- [Autorização do check-in](apiV2/app/Services/Mobile/MobileCheckinService.php)
- [Agenda e grade do aluno](apiV2/app/Services/Mobile/MobileHorariosService.php)
- [Histórico de presença](apiV2/app/Repositories/CheckinRepository.php)
- [Editor de grade do painel](painel/src/components/ck/GradeTurmaEditor.js)
- [Menu do painel](painel/src/components/LayoutBase.js)
- [Aula no app do professor](mobile/app/checkin-turma.tsx)
- [Testes de grade individual](apiV2/tests/Feature/GradeIndividualTest.php)
- [Testes de concorrência](apiV2/tests/Feature/AcessoHibridoConcurrencyTest.php)
- [Testes do seed](apiV2/tests/Feature/Tenant5TurmasFixasAlunosSeederTest.php)
