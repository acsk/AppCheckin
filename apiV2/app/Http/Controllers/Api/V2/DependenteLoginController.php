<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\DependenteLoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Login próprio do dependente (público). Ver DependenteLoginService. */
class DependenteLoginController extends Controller
{
    public function __construct(
        private readonly DependenteLoginService $login,
    ) {}

    public function perfis(Request $request): JsonResponse
    {
        return $this->json($this->login->perfis((string) $request->input('email', ''), $this->ip($request)));
    }

    public function login(Request $request): JsonResponse
    {
        $tenantId = (int) $request->input('tenant_id', 0);

        return $this->json($this->login->entrar(
            (string) $request->input('email', ''),
            (int) $request->input('dependente_id', 0),
            (string) $request->input('pin', ''),
            $tenantId > 0 ? $tenantId : null,
            $this->ip($request),
        ));
    }

    private function ip(Request $request): string
    {
        foreach (['CF-Connecting-IP', 'X-Real-IP', 'X-Forwarded-For'] as $h) {
            $ip = trim(explode(',', (string) $request->header($h, ''))[0]);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return $request->ip() ?? '0.0.0.0';
    }

    /** @param  array{status: int, body: array<string, mixed>}  $r */
    private function json(array $r): JsonResponse
    {
        return response()->json($r['body'], $r['status'], [], JSON_UNESCAPED_UNICODE);
    }
}
