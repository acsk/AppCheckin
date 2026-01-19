# 🚀 Guia de Deploy - Hospedagem Compartilhada

## Configuração para mobile.appcheckin.com.br

### Estrutura na Hospedagem Compartilhada

```
/public_html/
├── .htaccess
├── dist/
│   ├── index.html
│   ├── .htaccess
│   ├── js/
│   ├── css/
│   └── ...
```

### Passo 1: Build Local

```bash
npm install
npm run web
```

Isso vai gerar a pasta `dist/` com a aplicação pronta para produção.

### Passo 2: Upload para Hospedagem

Use FTP ou seu gerenciador de arquivos para:

1. **Fazer upload da pasta `dist/`** para a raiz (`/public_html/`)
2. **Fazer upload do arquivo `.htaccess`** para a raiz (`/public_html/`)
3. **Fazer upload do `.htaccess`** também dentro de `dist/`

### Passo 3: Configurações Necessárias

#### API Base URL
Já configurado em `src/config/api.js`:
```javascript
export const API_BASE_URL = 'https://api.appcheckin.com.br';
```

#### .htaccess - Reescrita de URLs
O arquivo `.htaccess` já contém:
- Reescrita de rotas para `dist/index.html` (SPA routing)
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
- Verificar paths relativos (devem começar com `/dist/`)
- Verificar tipos MIME no `.htaccess`

### Ambiente (Node Vars)

Se precisar usar variáveis de ambiente:
```bash
EXPO_PUBLIC_API_URL=https://api.appcheckin.com.br npm run web
```

### Script Automatizado

Use o script `deploy.sh` (Unix/Mac/Linux):
```bash
chmod +x deploy.sh
./deploy.sh
```

Isso fará o build e preparará os arquivos.

---

**API:** https://api.appcheckin.com.br  
**App Web:** https://mobile.appcheckin.com.br  
**Suporte:** Para problemas, verificar logs no servidor
