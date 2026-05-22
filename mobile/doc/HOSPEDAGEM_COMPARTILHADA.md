# Hospedagem compartilhada (Hostinger)

## O que NÃO publicar

Não envie o projeto inteiro (`app/`, `package.json`, `node_modules/`, etc.) para `public_html`.

O site deve servir **somente o conteúdo da pasta `dist/`** depois do build.

## Build local

```bash
cd mobile
npm run web:build:clean
```

## O que deve existir em `dist/` (antes do upload)

```bash
ls -la dist/
# index.html
# fonts.css
# .htaccess
# _expo/          ← bundle JavaScript (~1,8 MB) — OBRIGATÓRIO
# fonts/          ← 3 arquivos .ttf
# assets/         ← imagens
# *.html          ← rotas estáticas
```

Se `_expo/` não aparecer no `ls`, o build falhou ou a pasta não foi gerada.

## Upload (FTP / Gerenciador de arquivos)

**Opção recomendada:** conteúdo de `dist/` na **raiz** do domínio (`public_html/`):

```
public_html/
├── .htaccess
├── index.html
├── fonts.css
├── _expo/
│   └── static/js/web/entry-XXXXXXXX.js
├── fonts/
├── assets/
└── … (demais .html)
```

No FTP: ative **“mostrar arquivos ocultos”** para enviar a pasta `_expo`.

**Não** deixe o site apontando para `public_html/mobile/dist/` com o código-fonte ao lado — isso expõe o projeto e costuma faltar `_expo` no caminho que o navegador usa.

## Document root no hPanel

Em **Domínios → mobile.appcheckin.com.br → Document root**, use a pasta que contém `index.html` e `_expo/` no mesmo nível (em geral `public_html`, não `public_html/mobile`).

## Verificar após upload

Abra no navegador (substitua pelo hash do seu `index.html`):

`https://mobile.appcheckin.com.br/_expo/static/js/web/entry-XXXXXXXX.js`

- Se aparecer código JS (`var __BUNDLE_START_TIME__`…) → OK  
- Se aparecer página HTML → falta a pasta `_expo` ou o caminho do site está errado

```bash
npm run web:check-remote
```
