# 🚀 Guia de Publicação - App Check-in

## Ambientes Disponíveis

### **Desenvolvimento (DEV)**
- URL da API: `http://localhost:8080`
- Arquivo de config: `.env.development`
- Logs de debug: **ATIVADOS**
- Sem otimizações

### **Produção (PROD)**
- URL da API: `https://api.appcheckin.com` (ajuste conforme necessário)
- Arquivo de config: `.env.production`
- Logs de debug: **DESATIVADOS**
- Otimizado para performance

---

## 📝 Configuração de Ambientes

### **Arquivo `.env.development`**
```env
EXPO_PUBLIC_APP_ENV=development
EXPO_PUBLIC_API_URL=http://localhost:8080
EXPO_PUBLIC_DEBUG_LOGS=true
EXPO_PUBLIC_APP_NAME=App Check-in (Dev)
```

### **Arquivo `.env.production`**
```env
EXPO_PUBLIC_APP_ENV=production
EXPO_PUBLIC_API_URL=https://api.appcheckin.com
EXPO_PUBLIC_DEBUG_LOGS=false
EXPO_PUBLIC_APP_NAME=App Check-in
```

---

## 🌐 Build para WEB

### **Build Development**
```bash
cd mobile

# Usando .env.development
npm run web
```
Acessa em: `http://localhost:8081`

### **Build Production**
```bash
cd mobile

# Exportar aplicação estática para produção
EXPO_PUBLIC_APP_ENV=production \
  EXPO_PUBLIC_API_URL=https://api.appcheckin.com \
  npm run web -- --web-output dist
```

**Arquivos gerados em:** `dist/`

### **Deploy Web (Vercel, Netlify, etc)**

#### **Vercel**
```bash
# 1. Instalar CLI
npm install -g vercel

# 2. Build para prod
EXPO_PUBLIC_APP_ENV=production npm run web -- --web-output dist

# 3. Deploy
vercel deploy dist
```

#### **Netlify**
```bash
# 1. Instalar CLI
npm install -g netlify-cli

# 2. Build para prod
EXPO_PUBLIC_APP_ENV=production npm run web -- --web-output dist

# 3. Deploy
netlify deploy --prod --dir=dist
```

#### **GitHub Pages**
```bash
# 1. Adicionar ao package.json
"homepage": "https://username.github.io/appcheckin",

# 2. Build
EXPO_PUBLIC_APP_ENV=production npm run web -- --web-output dist

# 3. Deploy com gh-pages
npm install --save-dev gh-pages
npx gh-pages -d dist
```

---

## 📱 Build para Mobile

### **iOS (Dev)**
```bash
cd mobile
npm run ios
```

### **iOS (Prod)**
```bash
cd mobile
eas build --platform ios --profile production
```

### **Android (Dev)**
```bash
cd mobile
npm run android
```

### **Android (Prod)**
```bash
cd mobile
eas build --platform android --profile production
```

---

## 🔑 Variáveis de Ambiente Obrigatórias

| Variável | Dev | Prod | Exemplo |
|----------|-----|------|---------|
| `EXPO_PUBLIC_APP_ENV` | ✅ | ✅ | `development` / `production` |
| `EXPO_PUBLIC_API_URL` | ✅ | ✅ | `http://localhost:8080` / `https://api.example.com` |
| `EXPO_PUBLIC_DEBUG_LOGS` | ✅ | ✅ | `true` / `false` |
| `EXPO_PUBLIC_APP_NAME` | ✅ | ✅ | `App Check-in (Dev)` / `App Check-in` |

---

## 🔐 Boas Práticas

### **1. Nunca commitar `.env` com dados sensíveis**
```bash
# Adicionar ao .gitignore
echo ".env.local" >> .gitignore
echo ".env.*.local" >> .gitignore
```

### **2. Usar `.env.example` para documentar**
```bash
cp .env.development .env.example
# Remover valores sensíveis do arquivo
```

### **3. Variáveis de Ambiente no CI/CD**
Configurar no GitHub Actions, GitLab CI, etc:

```yaml
# .github/workflows/deploy.yml
env:
  EXPO_PUBLIC_API_URL: ${{ secrets.API_URL }}
  EXPO_PUBLIC_APP_ENV: production
```

---

## 📊 Checklist de Deploy

### **Antes de Publicar (Ambos)**
- [ ] Todos os testes passando
- [ ] Sem console.error() em produção
- [ ] Versão atualizada em `app.json`
- [ ] Changelog atualizado

### **Web**
- [ ] `.env.production` configurado corretamente
- [ ] API URL apontando para servidor de produção
- [ ] Debug logs desativados
- [ ] Build gerado sem erros
- [ ] Testado em navegadores principais (Chrome, Safari, Firefox)
- [ ] CORS configurado no backend

### **Mobile (iOS)**
- [ ] Certificados Apple atualizados
- [ ] Version e build number incrementados
- [ ] App.json com metadata completa
- [ ] Screenshots e descrição da App Store
- [ ] Privacy Policy em português

### **Mobile (Android)**
- [ ] Release key configurada
- [ ] Versionamento atualizado
- [ ] APK/AAB testado em dispositivos reais
- [ ] Screenshots e descrição da Play Store
- [ ] Privacy Policy em português

---

## 🆘 Troubleshooting

### **API não conecta em produção**
```
Verificar:
1. EXPO_PUBLIC_API_URL correto
2. CORS habilitado no backend
3. SSL certificate válido (HTTPS)
4. Firewall/WAF bloqueando requisições
```

### **Build falha com "Missing env variable"**
```bash
# Verificar se .env.production existe
ls -la .env.production

# Ou passar variáveis direto
EXPO_PUBLIC_API_URL=https://... npm run web -- --web-output dist
```

### **Web carrega mas não consegue dados**
```
F12 > Network > Verificar requisições
- Status 403/401: Problema de autenticação
- Status 404: URL da API errada
- CORS error: Configurar backend
```

---

## 📚 Referências

- [Expo Environment Variables](https://docs.expo.dev/guides/environment-variables/)
- [React Native Build Process](https://reactnative.dev/docs/build-procedure)
- [Vercel Deployment](https://vercel.com/docs)
- [Netlify Deployment](https://docs.netlify.com/)

---

**Status**: ✅ Pronto para publicação
**Última atualização**: 14 de janeiro de 2026
