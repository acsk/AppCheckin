# Monetização — painel administrativo

Menu **Monetização → Google AdSense | Google AdMob**, disponível ao Super Admin.
Implementado com React/Expo Router e Laravel, seguindo a arquitetura existente.

## Escopo entregue

- Configurações persistidas no MySQL, separadas por aplicação e ambiente.
- Aplicações iniciais: painel web, app do aluno web, Android e iOS.
- Controle global por ambiente e habilitação por provedor/aplicação.
- Publisher ID/domínio/tipo manual ou automático para AdSense.
- App IDs Android/iOS separados e unidades de anúncios nos posicionamentos AdMob.
- Cadastro, edição e remoção de posicionamentos, com habilitação e prioridade.
- Regras para usuários gratuitos/Premium, dispositivos web, consentimento, idade e rotas.
- Diagnóstico dos campos cadastrados e auditoria com valores anteriores/novos.
- Versionamento e recusa de gravações baseadas em configuração desatualizada (HTTP 409).

Todos os provedores e posicionamentos começam **desativados**. Os posicionamentos
iniciais são candidatos, sem IDs reais e sem afirmação de aprovação pelo Google.
O controle global afeta os dois provedores e todas as aplicações do ambiente
selecionado. A configuração de produção é independente da de desenvolvimento.

Este pedido cobre a gestão administrativa. Não carrega scripts nem instala SDKs,
não solicita anúncios e não oferece receitas/impressões estimadas. As regras são
persistidas para a futura integração de exibição; não são uma implementação de
consentimento ou de elegibilidade de usuários nos aplicativos.

## Uso

1. Selecione o provedor, ambiente e aplicação.
2. Cadastre os identificadores públicos da sua conta Google. Não informe senhas,
   tokens de API, chaves de serviço ou JavaScript.
3. Cadastre os posicionamentos e regras. Os identificadores devem ser únicos por
   aplicação/ambiente. A remoção de um posicionamento é gravada ao salvar.
4. Salve as alterações. O diagnóstico mostra pendências de cadastro; aprovação,
   script carregado e SDK inicializado devem ser conferidos na integração real.

AdSense aceita `pub-XXXXXXXXXXXXXXXX` ou `ca-pub-XXXXXXXXXXXXXXXX`. No AdMob,
App ID usa `ca-app-pub-XXXXXXXXXXXXXXXX~XXXXXXXXXX`, enquanto Ad Unit ID usa
`ca-app-pub-XXXXXXXXXXXXXXXX/XXXXXXXXXX`. As validações distinguem esses campos.
Referências oficiais: [Publisher ID do AdSense](https://support.google.com/adsense/answer/105516)
e [App ID/Ad Unit ID do AdMob](https://developers.google.com/admob/api/v1/app-ad-units).

IDs de aplicação usados na inicialização nativa precisam ser aplicados no build
quando o SDK for integrado; salvá-los no painel não altera manifestos instalados.
Mudanças de regras e unidades de anúncio podem ser consumidas remotamente depois
que os aplicativos tiverem a integração apropriada. Não há endpoint público de
consumo nesta etapa: nenhum identificador é exposto aos usuários das academias.

## Banco e API

Migration: `2026_10_25_120000_monetizacao.php`. Execute a migration antes de
publicar a API/painel. Ela cria e inicializa `monetizacao_ambientes`,
`monetizacao_aplicacoes`, `monetizacao_provedores`,
`monetizacao_posicionamentos` e `monetizacao_auditoria`.

Rotas protegidas por JWT e `superadmin.auth`:

- `GET /v2/superadmin/monetizacao/{adsense|admob}?ambiente=development|production`
- `PUT /v2/superadmin/monetizacao/{adsense|admob}/{id}`

A gravação recebe a configuração completa, `versao`, `versao_global`,
`global_habilitado` e `posicionamentos`. O servidor valida os campos, grava
configuração/posicionamentos/controle global/auditoria em uma transação e retorna
o estado atualizado. Contextos de aplicações, plataformas e ambientes não são
intercambiáveis. Administradores de academia não acessam essas rotas.

## Verificação

`php vendor/bin/phpunit tests/Feature/MonetizacaoTest.php`

Testes usam SQLite em memória e IDs de exemplo, sem anúncios reais ou chamadas
ao Google. Cobrem estado inicial, persistência, separação de contextos, validação
de IDs/formatos, bloqueio de scripts, auditoria, concorrência e autorização.
