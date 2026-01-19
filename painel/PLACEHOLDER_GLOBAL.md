# Estilos Globais - Placeholders Claros

## 📝 Problema Resolvido
Os placeholders dos inputs estavam muito escuros (preto), dificultando a visualização.

## ✅ Solução Implementada

### 1. Arquivo de Estilos Globais
Criado: `src/styles/globalStyles.js`

Este arquivo contém:
- **globalStyles**: Estilos reutilizáveis para inputs, labels, etc
- **colors**: Paleta de cores do sistema
- **getInputProps()**: Função helper para aplicar estilos de input

### 2. Cor do Placeholder
```javascript
// Cinza claro (#9ca3af) para melhor contraste
placeholderTextColor={colors.placeholder}
```

## 🔧 Como Usar em Outras Telas

### Passo 1: Importar
```javascript
import { colors, globalStyles } from '../../styles/globalStyles';
```

### Passo 2: Aplicar nos TextInputs
```javascript
<TextInput
  style={styles.input}
  placeholder="Digite aqui..."
  placeholderTextColor={colors.placeholder}  // <-- Adicione esta linha
  value={value}
  onChangeText={setValue}
/>
```

### Passo 3 (Opcional): Usar Estilos Globais
```javascript
// Em vez de criar estilos locais, use os globais
<TextInput
  style={[globalStyles.input, error && globalStyles.inputError]}
  placeholder="Digite aqui..."
  placeholderTextColor={colors.placeholder}
  value={value}
/>
```

## 📋 Checklist para Outras Telas

Aplique o `placeholderTextColor` em todas as telas com inputs:

- [x] FormPlanoScreen.js
- [ ] NovoContratoScreen.js
- [ ] EditarContratoScreen.js
- [ ] TrocarPlanoScreen.js
- [ ] FormModalidadeScreen.js (ou equivalente)
- [ ] FormUsuarioScreen.js (ou equivalente)
- [ ] SearchableDropdown.js
- [ ] Qualquer outra tela com TextInput

## 🎨 Cores Disponíveis

```javascript
colors.placeholder        // #9ca3af - Placeholder (cinza claro)
colors.textPrimary       // #111827 - Texto principal
colors.textSecondary     // #6b7280 - Texto secundário
colors.textTertiary      // #9ca3af - Texto terciário
colors.primary           // #3b82f6 - Cor primária
colors.error             // #ef4444 - Erros
colors.success           // #10b981 - Sucesso
```

## 💡 Benefícios

1. **Consistência**: Todos os placeholders com a mesma cor
2. **Acessibilidade**: Contraste adequado entre placeholder e texto digitado
3. **Manutenção**: Fácil alterar a cor em um único lugar
4. **Reutilização**: Estilos prontos para usar em qualquer tela

## 🚀 Próximos Passos

1. Aplicar `placeholderTextColor` em todas as telas restantes
2. Considerar migrar estilos locais para `globalStyles.js`
3. Criar variantes de componentes (InputField, TextArea, etc)
