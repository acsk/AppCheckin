<?php
// Limpar OPcache - arquivo temporário para forçar recarregar o código
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache limpo com sucesso\n";
} else {
    echo "⚠️ OPcache não habilitado ou função não disponível\n";
}

// Remover este arquivo após executar
echo "Você pode deletar este arquivo agora.\n";
?>
