# ✅ Scripts de Deploy - Resumo Final

## 🎯 Objetivo

Automatizar o deploy completo do Expo Web com cópia de fonts dos ícones.

## 📝 Scripts Criados

### 1. **scripts/deploy.sh** (Script Completo - Recomendado)
```bash
./scripts/deploy.sh
```

**O que faz:**
- Executa `npx expo export --platform web`
- Copia 19 fonts para `dist/_expo/Fonts/`
- Copia `fonts.css` para `dist/`
- Injeta link de CSS no `index.html`
- Verifica estrutura final

**Resultado:** Pasta `dist/` pronta para deploy

---

### 2. **scripts/copy-fonts-only.sh** (Script Auxiliar)
```bash
npx expo export --platform web
./scripts/copy-fonts-only.sh
```

**O que faz:**
- Copia fonts para `dist/_expo/Fonts/`
- Copia `fonts.css`
- Injeta link no HTML

**Uso:** Se preferir controlar o export manualmente

---

### 3. **DEPLOY_SCRIPTS.md** (Documentação)
Guia completo com:
- Instruções de uso
- Configuração de servidor (nginx, apache)
- Troubleshooting
- Checklist de deploy

---

## 🚀 Como Usar

### Opção 1: Automático (Recomendado)
```bash
cd /Users/andrecabral/Projetos/AppCheckin/painel
./scripts/deploy.sh
```

### Opção 2: Manual
```bash
cd /Users/andrecabral/Projetos/AppCheckin/painel
npx expo export --platform web
./scripts/copy-fonts-only.sh
```

---

## 📊 Estrutura do dist/ Gerado

```
dist/
├── index.html              ← Atualizado com fonts.css
├── fonts.css               ← Novo! CSS dos fonts
├── favicon.ico
├── _expo/
│   ├── Fonts/              ← Novo! 19 arquivos .ttf
│   │   ├── AntDesign.ttf
│   │   ├── Feather.ttf
│   │   ├── Ionicons.ttf
│   │   ├── MaterialCommunityIcons.ttf
│   │   ├── MaterialIcons.ttf
│   │   └── ... (15 fonts mais)
│   └── static/
│       ├── css/
│       ├── js/
│       └── ...
└── ...
```

---

## ✨ Verificação

Após executar o script, verificar:

```bash
# 1. Fonts copiaos
ls /Users/andrecabral/Projetos/AppCheckin/painel/dist/_expo/Fonts/

# 2. CSS existe
ls /Users/andrecabral/Projetos/AppCheckin/painel/dist/fonts.css

# 3. HTML atualizado
grep "fonts.css" /Users/andrecabral/Projetos/AppCheckin/painel/dist/index.html
```

---

## 🔑 Chaves da Solução

| Arquivo | Conteúdo |
|---------|----------|
| `scripts/deploy.sh` | Script bash executável |
| `scripts/copy-fonts-only.sh` | Script bash auxiliar |
| `DEPLOY_SCRIPTS.md` | Documentação completa |
| `dist/fonts.css` | CSS gerado com @font-face |
| `dist/_expo/Fonts/*.ttf` | Fonts copiados |
| `dist/index.html` | Atualizado com link |

---

## 💻 Exemplo de Execução

```
$ ./scripts/deploy.sh

🚀 Iniciando deploy do Expo Web...

📦 Step 1: Exportando Expo para Web...
✅ Export concluído

📋 Step 2: Copiando fonts dos ícones...
✅ 19 fonts copiados para: /Users/andrecabral/Projetos/AppCheckin/painel/dist/_expo/Fonts

📄 Step 3: Copiando fonts.css...
✅ fonts.css copiado para: /Users/andrecabral/Projetos/AppCheckin/painel/dist/fonts.css

🔗 Step 4: Injetando link de fonts no index.html...
✅ Link para fonts.css injetado

✨ Step 5: Verificando distribuição...

📊 Resumo:
  • Dist criado em: /Users/andrecabral/Projetos/AppCheckin/painel/dist
  • Fonts copiados: 19 arquivos
  • Fonte: /Users/andrecabral/Projetos/AppCheckin/painel/dist/_expo/Fonts/
  • CSS: /Users/andrecabral/Projetos/AppCheckin/painel/dist/fonts.css

📁 Arquivos principais:
  • index.html: 15K
  • fonts.css: 2.6K
  • Fonts: 3.5M

🔍 Verificando links no HTML:
  ✅ CSS links corretos
  ✅ JS links corretos
  ✅ Fonts CSS link correto

✨ Deploy concluído com sucesso!

📝 Próximos passos:
  1. Fazer upload da pasta 'dist' para seu servidor
  2. Certificar que nginx/apache está configurado para servir de '/' (raiz)
  3. Testar em: https://seu-dominio.com
```

---

## 🔗 Integração com CI/CD

Para GitLab CI, GitHub Actions, etc:

```bash
# Executar antes do deploy
./scripts/deploy.sh

# Depois fazer upload de dist/
```

---

## ✅ Checklist Final

- [x] Script `scripts/deploy.sh` criado
- [x] Script `scripts/copy-fonts-only.sh` criado
- [x] Documentação `DEPLOY_SCRIPTS.md` criada
- [x] Ambos scripts são executáveis
- [x] Scripts copiam 19 fonts TTF
- [x] Scripts injetam CSS no HTML
- [x] Scripts verificam estrutura final

---

**Status:** ✅ Pronto para Produção

Todos os scripts estão prontos e documentados. Execute `./scripts/deploy.sh` quando for fazer o próximo deploy!
