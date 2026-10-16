<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Sandbox de pagamento no mobile: visível só para Admin (papel 3) e Super Admin.
 */
final class PaymentSandboxAccess
{
    public static function fromRequest(Request $request): bool
    {
        if ((bool) $request->attributes->get('is_super_admin', false)) {
            return true;
        }

        $usuario = $request->attributes->get('usuario');
        $papel = is_array($usuario) ? (int) ($usuario['papel_id'] ?? 0) : 0;

        return in_array($papel, [3, 4], true);
    }
}
