# Configuração remota do app

Liga, desliga e ajusta o app do aluno sem publicar versão nova.
Painel: **Super Admin → Configurações do app** (`/app-mobile`). Só o Super Admin edita.

## Como funciona

```
Catálogo (código)          Valores (banco)                       App
AppConfigCatalogo  ──►  app_config_valores (rascunho)
                          │  Revisar e publicar
                          ▼
                       app_config_publicacoes (retrato) ──cache──► AppConfigResolver ──► GET /v2/mobile/app-config
                          │                                                         └──► middleware funcionalidade:*
                       app_config_auditoria
```

- **O catálogo fica no código** (`app/Support/AppConfigCatalogo.php`): chave, tipo, padrão, limites, escopo,
  versão mínima e papéis. O banco guarda só valores. Chave fora do catálogo é recusada. O padrão de cada item
  é o comportamento de antes da configuração remota.
- **Rascunho → publicação.** As edições vão para `app_config_valores`. Publicar grava o retrato completo
  (geral + exceções por academia) em `app_config_publicacoes` e limpa o cache. O app lê sempre a última publicação.
- **Herança.** Um valor geral igual ao padrão não é guardado; uma exceção por academia só existe enquanto difere.
- **Rollback.** "Restaurar" republica o retrato de uma versão antiga como uma versão nova (`origem = rollback`).
- **Kill switch.** "Desligar agora" grava `bloqueio.<funcionalidade>` e publica na hora, levando **só** essa mudança
  (o resto do rascunho continua pendente). As rotas antigas de obrigatoriedade e de limite do ranking também publicam na hora.

## Precedência

1. Padrão do catálogo
2. Valor geral publicado
3. Exceção da academia (só itens com `escopo = tenant`)
4. Papel (`papeis` no catálogo)
5. Versão mínima da plataforma (`versao_minima`; web está sempre na última)
6. Rollout (`rollout.<k>`, 0–100; balde estável `crc32("k:usuario_id") % 100`)
7. Bloqueio emergencial

Os passos 4 a 7 só desligam. Nenhuma exceção por academia religa uma funcionalidade bloqueada.

## Contrato (versão 1)

`GET /v2/mobile/app-config` (JWT; academia vem do token) e `GET /v2/app-config` (público, só o geral).
Headers: `X-App-Platform: web|android|ios`, `X-App-Version: 1.0.2`, `If-None-Match`.
Resposta com `ETag`; conteúdo igual → `304`.

```json
{
  "versao_contrato": 1,
  "versao_config": 7,
  "cache_ttl_segundos": 300,
  "funcionalidades": { "checkin": true, "matriculas": true, "pagamentos": true, "cancelamento": true,
                       "ranking": false, "recordes": true, "grupos_whatsapp": true },
  "parametros": { "ranking_limite_home": 15, "aviso_home": "" },
  "atualizacao": { "versao_minima": "1.0.2", "versao_recomendada": "1.1.0", "url": "https://…/app.apk",
                   "obrigatoria": false, "disponivel": true, "mensagem": "" }
}
```

Na web, `atualizacao` é `{ obrigatoria, mensagem }`: `obrigatoria` é a política para versões novas
(quem compara o build é o `ForceUpdateGate`). Campos novos podem ser adicionados sem mudar `versao_contrato`;
mudança incompatível sobe a versão, e builds antigos ignoram contratos maiores que o deles.

## Backend: bloqueio nas rotas

```php
Route::post('/checkin', …)->middleware('funcionalidade:checkin');
```

Com a funcionalidade desligada a rota responde `403` com `code: FUNCIONALIDADE_DESATIVADA`. Isso vale também
para app desatualizado ou offline com configuração antiga. Hoje as rotas com bloqueio são:

| Funcionalidade | Rotas |
|---|---|
| checkin | `POST /mobile/checkin`, `POST /mobile/checkin/manual` |
| matriculas + pagamentos | `POST /mobile/comprar-plano`, `POST /mobile/migrar-plano` |
| pagamentos | `POST /mobile/pagamento/pix`, `GET /mobile/pagamento/reabrir/{id}`, `POST /mobile/pacotes/contratos/{id}/pagar` |
| cancelamento | `POST /mobile/assinatura/{id}/cancelar`, `POST /mobile/diaria/{id}/cancelar` |
| recordes | `POST /mobile/recordes`, `PUT /mobile/recordes/{id}` |

Ranking, grupos do WhatsApp e consultas não têm bloqueio no servidor (são leitura). Pagamentos já iniciados
(`verificar-pagamento`) continuam funcionando.

No código: `app(AppConfigService::class)->parametro('ranking_limite_home', $tenantId)` e
`->funcionalidadeAtiva('checkin', AppConfigContexto::daRequisicao($request))`.

## App

`mobile/src/services/remoteConfig.ts` é o único ponto de acesso:

```ts
estaFuncionalidadeAtiva("pagamentos");
obterParametro("ranking_limite_home");
obterAtualizacao();
verificarAtualizacaoObrigatoria();
```

Em componentes: `useFuncionalidade("ranking")`, `useParametro("aviso_home")`, `useConfigRemota()` ou
`<SeFuncionalidade f="recordes">…</SeFuncionalidade>`.

- Busca ao abrir, no login e no logout (`app/_layout.tsx`), na troca de academia (`authService.selectTenant*`)
  e ao voltar para o app depois do intervalo (`cache_ttl_segundos`).
- Guarda a última configuração válida **por contexto** (`@appcheckin:remote_config:t<tenant>` / `:publico`),
  então nunca reaproveita a configuração de outra academia.
- Chamadas simultâneas compartilham a requisição. Sem rede ou com contrato inválido, mantém a última
  configuração válida; sem nenhuma, vale `PADROES` (o comportamento de antes).

Onde o app obedece hoje: abas Check-in e Planos (`AppTabBar`), ranking e aviso na Home, item Recordes e
grupos do WhatsApp no menu (`account.tsx`), atualização nativa (`ForceUpdateGate`).

## Adicionar uma funcionalidade ou parâmetro

1. Declare no `AppConfigCatalogo` (`FUNCIONALIDADES` ou `PARAMETROS`). Se o app precisar de um build novo para
   obedecer, defina `versao_minima` para esse build.
2. No app, adicione a chave em `Funcionalidade`/`Parametros` e em `PADROES` (`remoteConfig.ts`) e use os hooks.
3. Ação sensível: coloque `->middleware('funcionalidade:<k>')` na rota.
4. Teste em `tests/Feature/AppConfigTest.php`.

## O que exige build novo

- **Web (PWA):** nunca; basta o deploy.
- **APK/iOS:** só quando muda código: tela ou componente novo, flag para algo que ainda não lê a configuração,
  ícone, splash, nome e cores do `app.json`. Ligar/desligar, parâmetros, exceções por academia, rollout,
  bloqueio e versões não exigem build.
- APKs anteriores a este recurso não leem `/app-config`. Para eles, `/v2/version/mobile` continua respondendo,
  com `android.obrigatoria = true` só quando a versão mínima alcança a publicada.

## Migração

A migration `2026_10_25_100000_app_config_remota` cria as tabelas e a publicação 1 a partir do estado atual:
`app_versions.ranking_checkins_limite`, `app_versions.obrigatoria` e o APK do `.env`
(com obrigatória ligada, `android_versao_minima` = versão do `.env`, para manter o bloqueio de antes).
As colunas antigas de `app_versions` ficam no banco, mas deixam de ser lidas.
`APP_ANDROID_VERSION`/`APP_ANDROID_APK_URL` passam a ser só o padrão; o valor vale pelo painel (aba Versões).
