# Welcome to your Expo app 👋

This is an [Expo](https://expo.dev) project created with [`create-expo-app`](https://www.npmjs.com/package/create-expo-app).

## Get started

1. Install dependencies

   ```bash
   npm install
   ```

2. Start the app

   ```bash
   npx expo start
   ```

In the output, you'll find options to open the app in a

- [development build](https://docs.expo.dev/develop/development-builds/introduction/)
- [Android emulator](https://docs.expo.dev/workflow/android-studio-emulator/)
- [iOS simulator](https://docs.expo.dev/workflow/ios-simulator/)
- [Expo Go](https://expo.dev/go), a limited sandbox for trying out app development with Expo

You can start developing by editing the files inside the **app** directory. This project uses [file-based routing](https://docs.expo.dev/router/introduction).

## Selecao de academia no login

Quando a conta possui mais de uma academia, o login abre o
[TenantSelectionModal](./src/components/ds/TenantSelectionModal.tsx). Selecionar
uma opcao apenas marca a academia; a autenticacao continua ao confirmar em
"Entrar na ...".

"Lembrar minha escolha" salva uma preferencia local por usuario, usada para
pre-selecionar a academia no proximo login, sem pular a confirmacao. Desmarcar
essa opcao e concluir a entrada remove a preferencia. O selo "Ultimo acesso"
indica a ultima academia escolhida com sucesso nesse dispositivo.

## Tema na versao web

O menu lateral oferece **Claro**, **Escuro** e **Sistema** em **Aparencia**.
A escolha e salva neste navegador e permanece ao recarregar ou sair da conta.
Sem escolha explicita, o tema acompanha a preferencia do sistema operacional.

As paletas ficam em [theme.css](./src/theme/theme.css), com variaveis CSS sob
`data-theme="light"` e `data-theme="dark"`. Os tokens do design system e as
cores semanticas compartilhadas usam essas variaveis no bundle web; o bundle
nativo mantem as cores anteriores. Componentes novos devem usar esses tokens
em vez de cores claras fixas.

## Confirmacao manual de check-in

A tela da turma abre a confirmacao manual como uma folha inferior do
[Modal do design system](./src/components/ds/Modal.tsx), com identificacao do
aluno, resumo financeiro rolavel e acao fixa "Confirmar check-in".
Fechar pelo X, pelo fundo ou pelo voltar nao registra o check-in.

O resumo distingue pagamentos pagos, pendentes e atrasados, mantendo as datas
de vencimento no calendario local. O aviso de atraso e informativo: a
autorizacao do check-in continua sendo decidida pela API. Erros de matricula
pendente usam o mesmo [dialogo de pagamento](./src/components/ds/PendingPaymentModal.tsx)
nas telas de turma e check-in, preservando a mensagem e o vencimento da API.
As duas sobreposicoes usam os tokens de cores dos temas claro e escuro.

## Recordes pessoais

Em **Recordes > Meus**, toque numa prova (ou no destaque) para abrir a evolucao,
o grafico por data e o historico. O botao **+** permite escolher uma prova e
registrar uma marca; provas sem marcas tambem podem receber o primeiro registro.
O filtro de seis meses usa datas reais, e nao uma quantidade fixa de tentativas.
Os links de detalhe usam a rota absoluta `/recorde-prova`, tanto na entrada
raiz quanto em `/(tabs)/recordes`; a tela de detalhe nao pertence ao grupo de abas.

O formulario usa as metricas cadastradas pela academia: tempo em minutos,
segundos e centesimos, inteiros ou decimais com ate tres casas. Ao editar apenas
data/observacao, a precisao original dos valores em milissegundos e preservada.
Edicao e exclusao sao permitidas apenas para marcas proprias de origem `aluno`;
marcas da academia aparecem no historico, mas sao somente leitura.

Contrato da API v2 (JWT e academia do token):

- `GET /mobile/recordes`: resumo pessoal, recordistas e definicoes ativas.
- `GET /mobile/recordes/ranking/{definicaoId}`: classificacao por prova/categoria.
- `GET /mobile/recordes/prova/{definicaoId}`: metricas, marcas e `melhor_id`.
- `POST /mobile/recordes`: `{ definicao_id, data_recorde, observacoes, valores }`.
- `PUT /mobile/recordes/{id}`: mesma estrutura, sem trocar a prova.
- `DELETE /mobile/recordes/{id}`: exclusao definitiva com confirmacao no app.

`data_recorde` usa `AAAA-MM-DD`; `valores` e uma lista de
`{ metrica_id, valor }`. Tempo e enviado em milissegundos. A metrica principal
e sempre obrigatoria; as demais seguem sua configuracao. Datas futuras, valores
nao positivos, metricas alheias/duplicadas e precisao fora do contrato retornam
422. Melhor marca e ranking sao recalculados apos as alteracoes.

Meta pessoal e classificacao treino/competicao do prototipo nao fazem parte
do contrato atual e nao sao apresentados como configuracoes persistidas.
Publicar API v2 e mobile juntos; nao ha migracao de banco adicional.

Validacao dos formatadores e payloads: `npm run test:recordes`.

## Get a fresh project

When you're ready, run:

```bash
npm run reset-project
```

This command will move the starter code to the **app-example** directory and create a blank **app** directory where you can start developing.

## Learn more

To learn more about developing your project with Expo, look at the following resources:

- [Expo documentation](https://docs.expo.dev/): Learn fundamentals, or go into advanced topics with our [guides](https://docs.expo.dev/guides).
- [Learn Expo tutorial](https://docs.expo.dev/tutorial/introduction/): Follow a step-by-step tutorial where you'll create a project that runs on Android, iOS, and the web.

## Join the community

Join our community of developers creating universal apps.

- [Expo on GitHub](https://github.com/expo/expo): View our open source platform and contribute.
- [Discord community](https://chat.expo.dev): Chat with Expo users and ask questions.
