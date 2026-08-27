<?php

declare(strict_types=1);

use App\Models\Checkin;

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('America/Sao_Paulo');

$ok = true;

function assertTrue(bool $cond, string $msg): void
{
    global $ok;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $ok = false;
    }
}

// Caso do aluno 63 / matrícula 204: ciclo novo após renovação paga, 0 check-ins.
assertTrue(
    Checkin::pendenteDeveReativarPorSaldoCiclo(9, 0, true),
    'ciclo novo pago com 0 usos deve reativar'
);

assertTrue(
    Checkin::pendenteDeveReativarPorSaldoCiclo(9, 5, true),
    'ciclo pago com saldo restante deve reativar'
);

assertTrue(
    !Checkin::pendenteDeveReativarPorSaldoCiclo(9, 9, true),
    'limite empatado não deve reativar'
);

assertTrue(
    !Checkin::pendenteDeveReativarPorSaldoCiclo(9, 0, false),
    '1ª compra sem pagamento não deve reativar'
);

assertTrue(
    !Checkin::pendenteDeveReativarPorSaldoCiclo(0, 0, true),
    'sem direito de check-in não deve reativar'
);

assertTrue(
    Checkin::pendenteDeveInformarLimiteCiclo(3, true),
    'tenant válido com pagamento pago pode ser limite de ciclo'
);

assertTrue(
    !Checkin::pendenteDeveInformarLimiteCiclo(3, false),
    'sem pagamento deve ser aguardando pagamento, não limite'
);

assertTrue(
    !Checkin::pendenteDeveInformarLimiteCiclo(0, true),
    'tenant_id 0 não deve cair em LIMITE_CHECKINS_CICLO'
);

assertTrue(
    !Checkin::pendenteDeveInformarLimiteCiclo(-1, false),
    'tenant_id inválido sem pagamento não deve ser limite de ciclo'
);

if ($ok) {
    echo "OK CheckinSaldoCiclo\n";
    exit(0);
}
exit(1);
