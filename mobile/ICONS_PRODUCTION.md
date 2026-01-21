# Solução: Ícones em Produção

## Problema

Os ícones do `@expo/vector-icons` (especificamente Feather icons) não estavam aparecendo no build de produção web.

### Causa Raiz

1. **Fontes não eram copiadas**: Os arquivos `.ttf` das fontes do vector-icons estavam em:

   ```
   node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/Fonts/
   ```

   Mas não eram incluídos no diretório `dist/` durante o build.

2. **HTML não fazia referência às fontes**: O arquivo `index.html` gerado pelo Expo não tinha um `<link>` para um arquivo CSS que definisse os `@font-face`.

3. **Falta de definição de fontes**: Não havia um arquivo CSS com as declarações `@font-face` para as fontes do vector-icons.

## Solução Implementada

### 1. Arquivo de Configuração de Fontes (`/public/fonts.css`)

Criado arquivo com definições `@font-face` para todas as fontes disponíveis:

```css
@font-face {
  font-family: "Feather";
  src: url("../fonts/Feather.ttf") format("truetype");
}

@font-face {
  font-family: "MaterialIcons";
  src: url("../fonts/MaterialIcons.ttf") format("truetype");
}

/* ... mais 15+ fontes ... */
```

Este arquivo é copiado para `dist/fonts.css` durante o build.

### 2. Script de Injeção (`/scripts/inject-fonts.sh`)

Script bash que injeta automaticamente o link do CSS no HTML gerado:

```bash
#!/bin/bash
# Injeta <link rel="stylesheet" href="/fonts.css" /> antes de </head>
sed -i.bak 's|</head>|<link rel="stylesheet" href="/fonts.css" /></head>|' dist/index.html
```

### 3. Atualização dos Scripts de Build (`package.json`)

**Antes:**

```json
"web:build": "... && cp -r node_modules/@expo/vector-icons/fonts dist/fonts ..."
```

**Depois:**

```json
"web:build": "mv .env.development .env.development.bak && cp .env.production .env && expo export --platform web --clear && rm .env && mv .env.development.bak .env.development && cp -r assets dist/ && mkdir -p dist/fonts && cp -r node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/Fonts/* dist/fonts/ && bash scripts/inject-fonts.sh && cp public/fonts.css dist/fonts.css"
```

**Mudanças principais:**

- ✅ Corrigido caminho das fontes: `build/vendor/react-native-vector-icons/Fonts/`
- ✅ Adicionado `mkdir -p dist/fonts` para garantir a pasta
- ✅ Adicionado chamada ao `inject-fonts.sh`
- ✅ Adicionado `cp public/fonts.css dist/fonts.css`

Aplicado para ambos: `web:build` (produção) e `web:build:dev` (desenvolvimento)

## Estrutura de Arquivos Criados

```
mobile/
├── public/
│   └── fonts.css           (NOVO - Definições de @font-face)
├── scripts/
│   └── inject-fonts.sh     (NOVO - Script de injeção)
└── dist/
    ├── fonts/              (Copiado automaticamente)
    │   ├── Feather.ttf
    │   ├── MaterialIcons.ttf
    │   ├── Ionicons.ttf
    │   └── ... (19 mais)
    ├── fonts.css           (Copiado automaticamente)
    └── index.html          (Link injetado automaticamente)
```

## Fluxo de Trabalho

1. **Durante o build** (`npm run web:build`):

   ```
   Expo gera dist/
      ↓
   Copia assets/
      ↓
   Copia @expo/vector-icons/build/.../Fonts/* → dist/fonts/
      ↓
   Executa inject-fonts.sh (adiciona <link> no HTML)
      ↓
   Copia public/fonts.css → dist/fonts.css
      ↓
   Build completo com ícones! ✅
   ```

2. **No navegador** (quando faz deploy):
   ```
   HTML carrega <link rel="stylesheet" href="/fonts.css" />
      ↓
   CSS declara @font-face para Feather, MaterialIcons, etc
      ↓
   CSS faz referência a ./fonts/Feather.ttf, etc
      ↓
   Ícones aparecem corretamente em produção! 🎉
   ```

## Ícones Inclusos

As seguintes fontes estão disponíveis após o build:

- ✅ Feather (usado no projeto)
- ✅ MaterialIcons
- ✅ AntDesign
- ✅ Entypo
- ✅ EvilIcons
- ✅ FontAwesome (Regular, Solid, Brands)
- ✅ Fontisto
- ✅ Foundation
- ✅ Ionicons
- ✅ MaterialCommunityIcons
- ✅ Octicons
- ✅ Zocial
- ✅ SimpleLineIcons

## Como Usar

Após fazer build com `npm run web:build`, os ícones estarão disponíveis:

```javascript
import { Feather } from "@expo/vector-icons";

export default function Component() {
  return <Feather name="check-circle" size={24} color="#000" />;
}
```

---

# Processo Completo de Deploy

## Scripts npm Disponíveis

### Desenvolvimento Local

```bash
npm start              # Inicia o Expo em modo desenvolvimento
npm run web:dev        # Inicia web em dev (localhost:8080, logs ativos)
npm run web:prod       # Inicia web em prod (api.appcheckin.com)
npm run android        # Inicia app Android
npm run ios            # Inicia app iOS
npm run lint           # Verifica lint com ESLint
```

### Build para Produção

```bash
npm run web:build      # Build web para PRODUÇÃO
npm run web:build:dev  # Build web para DESENVOLVIMENTO
```

## Variáveis de Ambiente

O projeto usa dois arquivos `.env`:

### `.env.production` (Produção)

```bash
EXPO_PUBLIC_APP_ENV=production
EXPO_PUBLIC_API_URL=https://api.appcheckin.com
EXPO_PUBLIC_DEBUG_LOGS=false
```

### `.env.development` (Desenvolvimento)

```bash
EXPO_PUBLIC_APP_ENV=development
EXPO_PUBLIC_API_URL=http://localhost:8080
EXPO_PUBLIC_DEBUG_LOGS=true
```

## Fluxo de Build para Produção (`npm run web:build`)

### Passo 1: Preparar Ambiente

```bash
mv .env.development .env.development.bak  # Backup env dev
cp .env.production .env                   # Usar env prod
```

⚠️ Garante que Expo usa variáveis de produção

### Passo 2: Gerar Build do Expo

```bash
expo export --platform web --clear
```

✅ Cria `dist/` com:

- `index.html` (minificado e otimizado)
- JavaScript bundles compilados
- `assets/` (imagens do projeto)
- Estilos compilados

### Passo 3: Restaurar Ambiente Local

```bash
rm .env                                   # Remove .env temporário
mv .env.development.bak .env.development  # Restaura dev
```

⚠️ Protege o `.env.development` local

### Passo 4: Copiar Assets

```bash
cp -r assets dist/
```

✅ Garante que imagens do projeto estejam em `dist/assets/`

### Passo 5: Preparar Fontes de Ícones

```bash
mkdir -p dist/fonts
cp -r node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/Fonts/* dist/fonts/
```

✅ Copia ~22 arquivos `.ttf` para `dist/fonts/`

Fonts inclusos:

- Feather.ttf, MaterialIcons.ttf, AntDesign.ttf
- Ionicons.ttf, FontAwesome6_Regular.ttf
- FontAwesome6_Solid.ttf, FontAwesome6_Brands.ttf
- - 15 mais

### Passo 6: Injetar CSS de Fontes no HTML

```bash
bash scripts/inject-fonts.sh
```

✅ Script bash que:

1. Abre `dist/index.html`
2. Localiza `</head>`
3. Injeta antes: `<link rel="stylesheet" href="/fonts.css" />`

Resultado:

```html
...other tags...
<link rel="stylesheet" href="/fonts.css" /></head>
```

### Passo 7: Copiar Arquivo de Configuração de Fontes

```bash
cp public/fonts.css dist/fonts.css
```

✅ Define todos os `@font-face` para as fontes

Exemplo do conteúdo:

```css
@font-face {
  font-family: "Feather";
  src: url("../fonts/Feather.ttf") format("truetype");
}
/* 14+ mais @font-face... */
```

### Resultado Final da Build

Estrutura gerada em `dist/`:

```
dist/
├── index.html                    ← Link para /fonts.css injetado
├── fonts.css                     ← @font-face definitions
├── fonts/                        ← 22 arquivos .ttf
│   ├── Feather.ttf
│   ├── MaterialIcons.ttf
│   ├── AntDesign.ttf
│   ├── Ionicons.ttf
│   ├── FontAwesome6_Regular.ttf
│   ├── FontAwesome6_Solid.ttf
│   ├── FontAwesome6_Brands.ttf
│   ├── MaterialCommunityIcons.ttf
│   ├── EvilIcons.ttf
│   ├── Entypo.ttf
│   ├── SimpleLineIcons.ttf
│   ├── Octicons.ttf
│   ├── Zocial.ttf
│   ├── Foundation.ttf
│   ├── Fontisto.ttf
│   └── ... (+ 7 mais)
├── assets/                       ← Imagens do projeto
│   └── images/
│       ├── icon.png
│       ├── favicon.png
│       ├── splash-icon.png
│       └── ... (imagens do app)
├── _expo/
├── (tabs)/
│   ├── index.html
│   ├── account.html
│   ├── checkin.html
│   ├── wod.html
│   └── ... (páginas compiladas)
├── (auth)/
│   └── login.html
├── matricula.html
├── planos.html
├── turma-detalhes.html
├── _sitemap.html
├── +not-found.html
└── favicon.ico
```

## Mudanças no `package.json`

### Scripts Originais vs Atualizados

#### `web:build:dev` (para teste com env dev)

**Antes:**

```bash
"web:build:dev": "mv .env.production .env.production.bak && cp .env.development .env && expo export --platform web --clear && rm .env && mv .env.production.bak .env.production && cp -r assets dist/ && cp -r node_modules/@expo/vector-icons/fonts dist/fonts 2>/dev/null || true"
```

**Depois:**

```bash
"web:build:dev": "mv .env.production .env.production.bak && cp .env.development .env && expo export --platform web --clear && rm .env && mv .env.production.bak .env.production && cp -r assets dist/ && mkdir -p dist/fonts && cp -r node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/Fonts/* dist/fonts/ && bash scripts/inject-fonts.sh && cp public/fonts.css dist/fonts.css"
```

#### `web:build` (para produção)

**Antes:**

```bash
"web:build": "mv .env.development .env.development.bak && cp .env.production .env && expo export --platform web --clear && rm .env && mv .env.development.bak .env.development && cp -r assets dist/ && cp -r node_modules/@expo/vector-icons/fonts dist/fonts 2>/dev/null || true"
```

**Depois:**

```bash
"web:build": "mv .env.development .env.development.bak && cp .env.production .env && expo export --platform web --clear && rm .env && mv .env.development.bak .env.development && cp -r assets dist/ && mkdir -p dist/fonts && cp -r node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/Fonts/* dist/fonts/ && bash scripts/inject-fonts.sh && cp public/fonts.css dist/fonts.css"
```

### Comparação Detalhada das Mudanças

| Aspecto               | Antes                                   | Depois                                                                          | Motivo                                         |
| --------------------- | --------------------------------------- | ------------------------------------------------------------------------------- | ---------------------------------------------- |
| **Caminho das fonts** | `node_modules/@expo/vector-icons/fonts` | `node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/Fonts/` | Localização correta no @expo/vector-icons v15+ |
| **Criar pasta**       | ❌ Não                                  | ✅ `mkdir -p dist/fonts`                                                        | Garante pasta existe                           |
| **Cópia com erro**    | `2>/dev/null \|\| true`                 | ❌ Removido                                                                     | Falha visível se houver problema               |
| **Injetar CSS**       | ❌ Não fazia                            | ✅ `bash scripts/inject-fonts.sh`                                               | Adiciona link no HTML                          |
| **Copiar config**     | ❌ Não fazia                            | ✅ `cp public/fonts.css dist/fonts.css`                                         | Fonts.css fica disponível em produção          |

## Stack Tecnológico

| Tecnologia         | Versão   | Uso                                |
| ------------------ | -------- | ---------------------------------- |
| Expo               | ~54.0.31 | Framework React Native Web         |
| React              | 19.1.0   | UI Library                         |
| React Native       | 0.81.5   | Cross-platform bridge              |
| React DOM          | 19.1.0   | Rendering web                      |
| Expo Router        | ~6.0.21  | Roteamento (File-based routing)    |
| TypeScript         | ~5.9.2   | Type safety                        |
| @expo/vector-icons | ^15.0.3  | Ícones SVG/Fonts                   |
| AsyncStorage       | ^2.2.0   | Persistência local (tokens, dados) |
| Axios              | ^1.13.2  | HTTP requests (API)                |
| NativeWind         | ^4.2.1   | Tailwind CSS para React Native     |
| Reanimated         | ~4.1.1   | Animações                          |
| React Navigation   | ~7.x     | Navigation libs                    |
| Gesture Handler    | ~2.28.0  | Gestures                           |

## Sequência de Deploy

### 1️⃣ Preparar Release

```bash
# Verificar código está commitado
git status

# Criar branch de release (recomendado)
git checkout -b release/v1.x.x

# Verificar logs
git log --oneline -5
```

### 2️⃣ Build para Produção

```bash
# Garantir .env.production está correto com API real
cat .env.production

# Executar build
npm run web:build

# Verificar saída
ls -la dist/ | head -20
```

### 3️⃣ Verificar Integridade do Build

```bash
# Fontes foram copiadas?
ls -la dist/fonts/ | wc -l  # Deve ter ~22 arquivos

# CSS de fontes existe?
ls -l dist/fonts.css        # Deve existir

# Link foi injetado?
grep "fonts.css" dist/index.html  # Deve encontrar

# Assets foram copiados?
ls -la dist/assets/images/  # Deve ter imagens
```

### 4️⃣ Testar Build Localmente (Opcional)

```bash
# Servir dist/ localmente (Python)
python -m http.server 8000 -d dist/

# Ou usar Node
npx http-server dist/

# Abrir http://localhost:8000
# ✅ Testar se ícones aparecem
# ✅ Testar se imagens carregam
# ✅ Verificar console por erros
```

### 5️⃣ Deploy para Servidor

**Opção A - FTP/SSH Manual:**

```bash
# Copiar para servidor
scp -r dist/* user@server.com:/var/www/app/

# Ou com rsync (mais eficiente)
rsync -avz dist/* user@server.com:/var/www/app/
```

**Opção B - Docker:**

```dockerfile
FROM nginx:alpine

# Copiar build
COPY dist/ /usr/share/nginx/html/

# Configurar nginx para servir SPA
COPY nginx.conf /etc/nginx/nginx.conf

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
```

Build e push:

```bash
docker build -t appcheckin-web:1.0.0 .
docker push your-registry/appcheckin-web:1.0.0
```

**Opção C - Plataformas Cloud:**

Vercel:

```bash
npm i -g vercel
vercel deploy dist/ --prod
```

Netlify:

```bash
npm i -g netlify-cli
netlify deploy --prod --dir dist/
```

AWS S3 + CloudFront:

```bash
aws s3 sync dist/ s3://appcheckin-web/
aws cloudfront create-invalidation --distribution-id E123ABC --paths "/*"
```

### 6️⃣ Verificar Deploy em Produção

```bash
# Testar se ícones carregam (HTTPS)
curl -I https://api.appcheckin.com/fonts/Feather.ttf
# Esperar: HTTP/2 200

# Testar se HTML referencia CSS
curl https://api.appcheckin.com | grep -i "fonts.css"
# Esperar: <link rel="stylesheet" href="/fonts.css" />

# Testar se assets carregam
curl -I https://api.appcheckin.com/assets/images/icon.png
# Esperar: HTTP/2 200

# Abrir no navegador
# 1. Abrir DevTools (F12)
# 2. Aba Console
# 3. Não deve haver erros vermelhos
# 4. Verificar se ícones aparecem
```

### 7️⃣ Validar Funcionamento

```bash
# Abrir a aplicação
# 1. Testar login com credenciais inválidas
#    ✅ Deve mostrar mensagem específica (não genérica)
# 2. Testar login com credenciais válidas
#    ✅ Deve fazer login e navegar para dashboard
# 3. Verificar todas as páginas com ícones
#    ✅ Ícones devem aparecer (Feather, etc)
# 4. Testar em mobile responsivo
#    ✅ Interface deve adaptar
```

### 8️⃣ Commitear e Taggear Release

```bash
# Commit final
git add .
git commit -m "build: web production build v1.x.x"

# Tag de release
git tag -a v1.x.x -m "Release version 1.x.x"

# Push para produção
git push origin release/v1.x.x
git push origin v1.x.x

# Merge em main (opcional)
git checkout main
git merge release/v1.x.x
git push origin main
```

## Troubleshooting

### ❌ Problema: Ícones não aparecem

```bash
# 1. Verificar se fonts.css existe
ls -l dist/fonts.css
# Se não existir: re-rodar npm run web:build

# 2. Verificar se fonts foram copiadas
ls dist/fonts/ | wc -l
# Se vazio: verificar permissões

# 3. Verificar se link foi injetado
grep "fonts.css" dist/index.html
# Se não houver: script inject-fonts.sh não rodou

# 4. Verificar permissão do script
ls -l scripts/inject-fonts.sh
# Deve ter 'x': -rwxr-xr-x
# Se não tiver:
chmod +x scripts/inject-fonts.sh

# 5. Verificar console do navegador
# DevTools → Console → Procurar por erros 404
```

### ❌ Problema: Build falha

```bash
# 1. Limpar cache Expo
rm -rf .expo/

# 2. Limpar node_modules
rm -rf node_modules/
npm install

# 3. Limpar dist anterior
rm -rf dist/

# 4. Rodar build novamente com verbose
npm run web:build 2>&1 | tail -50
```

### ❌ Problema: Variáveis de ambiente incorretas

```bash
# 1. Verificar .env.production
cat .env.production
# Esperar: EXPO_PUBLIC_API_URL=https://api.appcheckin.com

# 2. Verificar durante build
grep EXPO_PUBLIC_API_URL dist/index.html
# Ou em qualquer arquivo JS do dist/

# 3. Em último caso, rodar com verbose
EXPO_DEBUG=true npm run web:build 2>&1 | grep "EXPO_PUBLIC"
```

### ❌ Problema: Alguns arquivos faltam no dist

```bash
# 1. Verificar completeness
echo "Contando arquivos..."
find dist/ -type f | wc -l
# Histórico: deve ter 100+ files

# 2. Verificar se assets estão
ls -la dist/assets/
# Deve ter images/

# 3. Verificar se expo metadata está
ls -la dist/_expo/
# Deve existir
```

## Verificação Final

Checklist antes de marcar como pronto:

- [ ] `dist/fonts/` contém ~22 arquivos `.ttf`
- [ ] `dist/fonts.css` existe e tem 15+ `@font-face`
- [ ] `dist/index.html` contém `<link rel="stylesheet" href="/fonts.css" />`
- [ ] `dist/assets/` contém todas as imagens
- [ ] `dist/_expo/` existe
- [ ] Teste local funciona (`http://localhost:8000`)
- [ ] Ícones aparecem no navegador
- [ ] Console não tem erros 404
- [ ] Deploy feito para produção
- [ ] URL de produção carrega sem erros
- [ ] Teste de login funciona
- [ ] Ícones aparecem em produção

## Próximos Passos

- ✅ Deploy da pasta `dist/` com os ícones inclusos
- ✅ Ícones funcionarão corretamente em produção! 🚀
- ⏳ Monitorar logs de produção por erros
- ⏳ Coletar feedback dos usuários

## Notas Importantes

- ⚠️ **Scripts**: Precisam de permissão de execução: `chmod +x scripts/inject-fonts.sh`
- ⚠️ **Vector Icons**: Caminho específico da v15.0.3+ - se atualizar, verificar se muda
- ⚠️ **Controle de Versão**: `public/fonts.css` e `scripts/inject-fonts.sh` devem estar commitados
- ⚠️ **Gitignore**: Nunca commitir `.env` - adicionar à `.gitignore`
- ⚠️ **Build**: Sempre usar `npm run web:build` para produção, nunca `web:build:dev`
- ⚠️ **Ambiente**: Script detecta automaticamente se é prod ou dev via `.env.production` / `.env.development`
- ⚠️ **HTTPS**: Produção deve usar HTTPS para carregar fontes adequadamente
- ⚠️ **Cache**: Se atualizar ícones, limpar cache CDN/navegador
