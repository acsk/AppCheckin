<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Motivos iniciais do "Acessar como academia" (motivos_acesso_suporte).
 *
 * Idempotente pelo nome: rodar de novo atualiza ordem/detalhe e não duplica. Motivos criados
 * depois (ou desativados) não são tocados. A migration de criação da tabela já roda este seeder.
 *
 * php artisan db:seed --class=MotivoAcessoSuporteSeeder
 */
class MotivoAcessoSuporteSeeder extends Seeder
{
    public const OUTRO = 'Outro';

    /** nome => exige_detalhe */
    private const MOTIVOS = [
        'Dúvida ou orientação de uso a pedido da academia' => false,
        'Configuração inicial da academia' => false,
        'Ajuste de plano ou cobrança a pedido da academia' => false,
        'Correção de matrícula ou pagamento' => false,
        'Importação ou correção de dados' => false,
        'Ajuste de equipe ou permissões' => false,
        'Investigação de erro reportado' => false,
        self::OUTRO => true,
    ];

    public function run(): void
    {
        $agora = now();
        $ordem = 0;
        foreach (self::MOTIVOS as $nome => $exigeDetalhe) {
            $ordem += 10;
            DB::table('motivos_acesso_suporte')->updateOrInsert(
                ['nome' => $nome],
                ['exige_detalhe' => $exigeDetalhe, 'ordem' => $ordem, 'updated_at' => $agora],
            );
            DB::table('motivos_acesso_suporte')->where('nome', $nome)->whereNull('created_at')->update(['created_at' => $agora]);
        }
    }
}
