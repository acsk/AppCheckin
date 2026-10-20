<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Assinaturas avulsas pagas ficavam sem data_fim (as compras pelo app não gravavam) ou com o fim
 * do período anterior (a linha é reaproveitada a cada compra e só o data_inicio era regravado).
 * Preenche com até quando a matrícula dá acesso, a mesma regra que o webhook passa a usar.
 * Só a assinatura mais recente de cada matrícula: as anteriores são de períodos já encerrados.
 */
return new class extends Migration
{
    public function up(): void
    {
        $paga = DB::table('assinatura_status')->where('codigo', 'paga')->value('id');
        if (! $paga) {
            return;
        }

        $rows = DB::table('assinaturas as a')
            ->join('matriculas as m', function ($j) {
                $j->on('m.id', '=', 'a.matricula_id')->on('m.tenant_id', '=', 'a.tenant_id');
            })
            ->whereRaw('a.id = (SELECT MAX(a2.id) FROM assinaturas a2 WHERE a2.matricula_id = a.matricula_id AND a2.tenant_id = a.tenant_id)')
            ->where(fn ($q) => $q->where('a.tipo_cobranca', 'avulso')->orWhereNull('a.tipo_cobranca'))
            ->where('a.status_id', $paga)
            ->whereNotNull('m.proxima_data_vencimento')
            ->where(fn ($q) => $q->whereNull('a.data_fim')->orWhereColumn('a.data_fim', '<', 'a.data_inicio'))
            ->get(['a.id', 'a.data_fim', 'm.proxima_data_vencimento']);

        foreach ($rows as $r) {
            DB::table('assinaturas')->where('id', $r->id)->update(['data_fim' => substr((string) $r->proxima_data_vencimento, 0, 10)]);
        }

        if ($rows->isNotEmpty()) {
            Log::info('Assinaturas avulsas: data_fim preenchida', [
                'total' => $rows->count(),
                // Para desfazer à mão, se preciso: id => data_fim anterior
                'anteriores' => $rows->mapWithKeys(fn ($r) => [$r->id => $r->data_fim])->all(),
            ]);
        }
    }

    public function down(): void
    {
        // Sem volta automática: os valores anteriores ficam no log (laravel.log) da migration.
    }
};
