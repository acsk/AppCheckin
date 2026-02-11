# 🚀 Guia de Deploy - Hospedagem Compartilhada

## Configuração para mobile.appcheckin.com.br

### Estrutura na Hospedagem Compartilhada

Opção A (dist dentro da raiz):
```
/public_html/
├── .htaccess
├── dist/
│   ├── index.html
│   ├── _expo/
│   ├── assets/
│   └── ...
```

Opção B (conteúdo da dist na raiz):
```
/public_html/
├── .htaccess
├── index.html
├── _expo/
├── assets/
└── ...
```

### Passo 1: Build Local

```bash
npm install
npm run web
```

Isso vai gerar a pasta `dist/` com a aplicação pronta para produção.

### Passo 2: Upload para Hospedagem

Use FTP ou seu gerenciador de arquivos para:

1. Se usar a Opção A, **fazer upload da pasta `dist/`** para a raiz (`/public_html/`)
2. Se usar a Opção B, **fazer upload do CONTEÚDO de `dist/`** para a raiz (`/public_html/`)
3. **Fazer upload do arquivo `.htaccess`** para a raiz (`/public_html/`)

### Passo 3: Configurações Necessárias

#### API Base URL
Já configurado em `src/config/api.js`:
```javascript
export const API_BASE_URL = 'https://api.appcheckin.com.br';
```

#### .htaccess - Reescrita de URLs
O arquivo `.htaccess` já contém:
- Reescrita de rotas compatível com `dist/` na raiz OU conteúdo da dist na raiz
- Compressão GZIP
- Cache control para arquivos estáticos
- Tipos MIME corretos

### Passo 4: Testar

1. Acesse https://mobile.appcheckin.com.br
2. Verifique no console do navegador se há erros
3. Teste as requisições para a API: https://api.appcheckin.com.br

### Troubleshooting

**❌ Erro 404 nas rotas internas**
- Verificar se `.htaccess` foi uploadado corretamente
- Verificar se `mod_rewrite` está ativado no servidor
- Testar com arquivo simples na raiz

**❌ CORS errors da API**
- A API em `https://api.appcheckin.com.br` precisa aceitar requisições de `https://mobile.appcheckin.com.br`
- Configurar headers CORS na API

**❌ Arquivos CSS/JS não carregam**
- Verificar paths relativos:
- Para Opção A: devem começar com `/dist/_expo/`
- Para Opção B: devem começar com `/_expo/`
- Verificar tipos MIME no `.htaccess`

### Ambiente (Node Vars)

Se precisar usar variáveis de ambiente:
```bash
EXPO_PUBLIC_API_URL=https://api.appcheckin.com.br npm run web
```

### Script Automatizado

Use o script `scripts/deploy.sh` (Unix/Mac/Linux):
```bash
chmod +x scripts/deploy.sh
./scripts/deploy.sh
```

Por padrão, ele prepara paths para a Opção B (dist na raiz).  
Para preparar para a Opção A, use:
```bash
DEPLOY_BASE_PATH=/dist ./scripts/deploy.sh
```

---

**API:** https://api.appcheckin.com.br  
**App Web:** https://mobile.appcheckin.com.br  
**Suporte:** Para problemas, verificar logs no servidor
