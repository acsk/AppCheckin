<?php

namespace App\Services\Mobile;

use App\Services\AppConfigService;
use App\Support\AcademyDateTime;
use Illuminate\Support\Facades\DB;

class MobileCheckinAcessoService
{
    public function __construct(private readonly AppConfigService $config) {}

    /** @return array{permitido: bool, motivo: string|null} */
    public function consultar(int $usuarioId, ?int $tenantId): array
    {
        if (! $tenantId) {
            return ['permitido' => false, 'motivo' => 'Selecione uma academia para acessar o Check-in.'];
        }
        $papeis = DB::table('tenant_usuario_papel as tup')
            ->join('usuarios as u', 'u.id', '=', 'tup.usuario_id')
            ->where('tup.usuario_id', $usuarioId)->where('tup.tenant_id', $tenantId)
            ->where('tup.ativo', 1)->where('u.ativo', 1)->pluck('tup.papel_id')->map(fn ($id) => (int) $id)->all();
        if ($papeis === []) {
            return ['permitido' => false, 'motivo' => 'Você não possui acesso ativo a esta academia.'];
        }
        // Equipe utiliza esta área para acompanhar turmas e controlar presenças.
        if (array_intersect($papeis, [2, 3, 4]) !== [] || $this->config->parametro('checkin_exigir_matricula_ativa', $tenantId) === false) {
            return ['permitido' => true, 'motivo' => null];
        }
        $ativa = in_array(1, $papeis, true) && DB::table('matriculas as m')
            ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('a.usuario_id', $usuarioId)->where('m.tenant_id', $tenantId)->where('p.tenant_id', $tenantId)
            ->where('sm.codigo', 'ativa')
            ->where(fn ($q) => $q->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) IS NULL')
                ->orWhereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= ?', [AcademyDateTime::today()]))
            ->exists();

        return ['permitido' => $ativa, 'motivo' => $ativa ? null : 'Para acessar o Check-in, você precisa de uma matrícula ativa com plano nesta academia.'];
    }
}
