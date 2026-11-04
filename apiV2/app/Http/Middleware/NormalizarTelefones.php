<?php

namespace App\Http\Middleware;

use App\Support\Telefone;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizarTelefones
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)
            && ! $request->is('v2/webhooks/*')) {
            $request->merge($this->normalizar($request->all()));
        }

        return $next($request);
    }

    private function normalizar(array $dados, string $prefixo = ''): array
    {
        foreach ($dados as $campo => $valor) {
            $chave = $prefixo.$campo;
            if (in_array($campo, Telefone::CAMPOS, true)) {
                $dados[$campo] = Telefone::normalizar($valor, $chave);
            } elseif (is_array($valor)) {
                $dados[$campo] = $this->normalizar($valor, $chave.'.');
            }
        }

        return $dados;
    }
}
