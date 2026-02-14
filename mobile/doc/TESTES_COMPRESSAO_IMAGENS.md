# 🧪 Testes de Compressão de Imagens

## ✅ Testes Manuais

### TC-001: Compressão Básica

**Passos:**

1. Abra a tela "Minha Conta"
2. Clique em "Trocar Foto de Perfil"
3. Selecione uma imagem da galeria
4. Observe console para logs

**Resultado Esperado:**

- ✅ Logs de compressão aparecem
- ✅ Imagem é redimensionada
- ✅ Tamanho é reduzido significativamente
- ✅ Upload completa rapidamente

---

### TC-002: Verificar Console

**Passos:**

1. Abra DevTools (F12)
2. Vá para Console
3. Selecione uma foto
4. Procure por "🎨 Iniciando compressão"

**Esperado:**

```
🎨 Iniciando compressão de imagem...
📸 === COMPRESSÃO DE IMAGEM ===
📏 Dimensões: 1080x1080
📦 Tamanho original: 3.5 MB
📦 Tamanho comprimido: 512 KB
📉 Compressão: 85.43% redução
```

---

### TC-003: Web vs Mobile

**Web:**

1. Abra app no navegador
2. Teste upload de foto
3. Verifique console

**Mobile:**

1. Abra app no dispositivo
2. Teste upload de foto
3. Verifique com console.log

**Esperado:**

- ✅ Ambos funcionam
- ✅ Ambos reduzem tamanho
- ✅ Performance aceitável

---

### TC-004: Diferentes Tamanhos

**Teste com:**

- Imagem pequena (~100KB)
- Imagem média (~2MB)
- Imagem grande (~10MB)

**Esperado:**

- ✅ Todas são comprimidas
- ✅ Redução > 30%
- ✅ Sem erros

---

### TC-005: Diferentes Formatos

**Teste com:**

- JPEG (padrão)
- PNG (se câmera suportar)
- Screenshots

**Esperado:**

- ✅ Qualidade mantida
- ✅ Tamanho reduzido
- ✅ Upload bem-sucedido

---

### TC-006: Velocidade de Upload

**Antes:**

- Medir tempo de upload com imagem original

**Depois:**

- Medir tempo de upload com imagem comprimida

**Esperado:**

- ✅ Upload comprimido é 3-10x mais rápido

---

### TC-007: Uso de Banda

**Medir:**

1. Banda usada para upload original
2. Banda usada para upload comprimido

**Esperado:**

- ✅ Economia de 70-90%

---

### TC-008: Erro de Compressão

**Simular:**

1. Desabilitar permissões de galeria
2. Usar imagem corrompida
3. Sem espaço em disco

**Esperado:**

- ✅ Erro é tratado graciosamente
- ✅ Mensagem clara ao usuário
- ✅ Fallback para original

---

### TC-009: Qualidade Visual

**Verificar:**

1. Foto comprimida 0.9 (alta)
2. Foto comprimida 0.5 (baixa)
3. Compara com original

**Esperado:**

- ✅ 0.9: praticamente idêntica
- ✅ 0.5: perda notável mas aceitável
- ✅ Sem artefatos graves

---

### TC-010: Performance

**Medir:**

- Tempo de compressão
- Uso de memória
- CPU durante processo

**Esperado:**

- Compressão: < 1 segundo
- Memória: < 50MB
- CPU: breve pico

---

## 📱 Teste em Diferentes Dispositivos

### iPhone

- [ ] iPhone 12
- [ ] iPhone 14
- [ ] iPhone SE

### Android

- [ ] Samsung Galaxy
- [ ] Pixel
- [ ] Dispositivo antigo

### Web

- [ ] Chrome
- [ ] Firefox
- [ ] Safari
- [ ] Edge

---

## 🔍 Verificações de Código

### TypeScript

```bash
npm run lint
# Esperado: ✅ Sem erros
```

### Build

```bash
npm run build
# Esperado: ✅ Compilação sucede
```

### Imports

```typescript
import { compressImage } from "@/src/utils/imageCompression";
// Esperado: ✅ Sem erro de import
```

---

## 🐛 Debugging

### Console Logs

```javascript
// Ativar logs detalhados
localStorage.setItem("DEBUG_COMPRESSION", "true");
```

### Inspecionar Resultado

```javascript
window.lastCompressionResult = result;
console.log(window.lastCompressionResult);
```

### Teste de Performance

```javascript
console.time("compress");
const result = await compressImage(uri);
console.timeEnd("compress");
```

---

## 📊 Métricas para Acompanhar

| Métrica             | Mínimo    | Alvo   | Máximo |
| ------------------- | --------- | ------ | ------ |
| Tempo de compressão | -         | 300ms  | 2000ms |
| Redução de tamanho  | 30%       | 80%    | -      |
| Qualidade visual    | Aceitável | Ótima  | -      |
| Taxa de sucesso     | 95%       | 99.9%  | 100%   |
| Memória usada       | -         | < 50MB | 200MB  |

---

## ✅ Checklist Final

### Funcionalidade

- [ ] Compressão funciona em web
- [ ] Compressão funciona em mobile
- [ ] Upload é mais rápido
- [ ] Qualidade é aceitável
- [ ] Erros são tratados

### Performance

- [ ] Compressão < 1 segundo
- [ ] Sem lag durante processo
- [ ] Memória estável
- [ ] CPU uso normal

### UX

- [ ] User feedback claro
- [ ] Sem mensagens confusas
- [ ] Operação transparente
- [ ] Resultado visível

### Código

- [ ] Lint passa
- [ ] Build sucede
- [ ] Imports corretos
- [ ] Sem console errors

---

## 📝 Report de Testes

**Data**: **_/_**/**\_\_**
**Testador**: ******\_\_\_\_******
**Ambiente**: [ ] Web [ ] Mobile [ ] Ambos

### Resultados

- Compressão: [ ] Pass [ ] Fail
- Performance: [ ] Pass [ ] Fail
- UX: [ ] Pass [ ] Fail
- Quality: [ ] Pass [ ] Fail

### Observações

```
_________________________________________________
_________________________________________________
_________________________________________________
```

### Bugs Encontrados

```
_________________________________________________
_________________________________________________
_________________________________________________
```

**Status Final**: [ ] ✅ APROVADO [ ] ❌ REJEITAR

---

**Última Atualização**: 23/01/2026
