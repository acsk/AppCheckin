<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sandbox de pagamento no mobile: visível só para Admin (papel 3) e Super Admin.
 * Quem é aluno e admin no mesmo tenant usa o maior papel da academia atual.
 */
final class PaymentSandboxAccess
{
    public static function fromRequest(Request $request): bool
    {
        if ((bool) $request->attributes->get('is_super_admin', false)) {
            return true;
        }

        $userId = (int) $request->attributes->get('userId', 0);
        $tenantId = (int) ($request->attributes->get('tenantId') ?? 0);
        if ($userId > 0 && $tenantId > 0) {
            try {
                $maxPapel = (int) (DB::table('tenant_usuario_papel')
                    ->where('usuario_id', $userId)
                    ->where('tenant_id', $tenantId)
                    ->where('ativo', 1)
                    ->max('papel_id') ?: 0);
                if (in_array($maxPapel, [3, 4], true)) {
                    return true;
                }
            } catch (\Throwable) {
            }
        }

        $papel = (int) $request->attributes->get('papel_id', 0);
        if ($papel === 0) {
            $usuario = $request->attributes->get('usuario');
            $papel = is_array($usuario) ? (int) ($usuario['papel_id'] ?? 0) : 0;
        }

        return in_array($papel, [3, 4], true);
    }
}
