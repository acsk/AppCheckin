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
