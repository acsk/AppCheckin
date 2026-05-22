# Deploy na Hostinger (mobile.appcheckin.com.br)

## Problema: `Unexpected token '<'` no entry-*.js

O servidor devolve **HTML** (`index.html`) em vez do JavaScript quando a pasta de bundles **não existe** no `public_html`.

Na Hostinger é comum a pasta **`_expo`** não ser enviada (FTP oculta pastas com `_`).

Este projeto renomeia automaticamente no build:

- `dist/_expo` → **`dist/expo-static`**
- Links no HTML: `/expo-static/static/js/web/entry-....js`

## Deploy correto

```bash
cd mobile
npm run web:build:clean
```

Envie **todo o conteúdo** de `mobile/dist/` para `public_html/` (não só `index.html`):

| Obrigatório | Tamanho aprox. |
|-------------|----------------|
| `index.html` | ~17 KB |
| **`expo-static/`** | **~1,8 MB** (bundle JS) |
| `fonts/` | 3 arquivos .ttf |
| `fonts.css` | ~500 B |
| `assets/` | imagens |
| `.htaccess` | regras SPA |

## Verificar após upload

```bash
npm run web:check-remote
```

Ou no navegador, abra diretamente (deve baixar JS, não página HTML):

`https://mobile.appcheckin.com.br/expo-static/static/js/web/entry-XXXXXXXX.js`

O hash muda a cada build; use o que está no `index.html` atual.

## Checklist rápido

- [ ] Pasta `expo-static` visível no Gerenciador de Arquivos da Hostinger
- [ ] Arquivo `.htaccess` na raiz do site
- [ ] Hard refresh no navegador (Ctrl+Shift+R)
