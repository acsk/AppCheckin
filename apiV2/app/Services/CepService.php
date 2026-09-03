<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class CepService
{
    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscar(string $cep): array
    {
        $cepLimpo = preg_replace('/[^0-9]/', '', $cep);

        if (strlen($cepLimpo) !== 8) {
            return [
                'status' => 400,
                'body' => [
                    'type' => 'error',
                    'message' => 'CEP inválido. Deve conter 8 dígitos.',
                ],
            ];
        }

        try {
            $response = Http::timeout(10)->get("https://viacep.com.br/ws/{$cepLimpo}/json/");

            if (! $response->successful()) {
                throw new \RuntimeException('API ViaCEP retornou status '.$response->status());
            }

            $dados = $response->json();

            if (isset($dados['erro']) && $dados['erro'] === true) {
                return [
                    'status' => 404,
                    'body' => [
                        'type' => 'error',
                        'message' => 'CEP não encontrado',
                    ],
                ];
            }

            $dadosVazios = empty($dados['logradouro'])
                && empty($dados['bairro'])
                && empty($dados['localidade']);

            if ($dadosVazios) {
                return [
                    'status' => 200,
                    'body' => [
                        'type' => 'warning',
                        'message' => 'CEP válido, mas não há dados disponíveis. Tente outro CEP.',
                        'data' => [
                            'cep' => $dados['cep'] ?? $cepLimpo,
                            'logradouro' => '',
                            'complemento' => '',
                            'bairro' => '',
                            'cidade' => '',
                            'estado' => '',
                            'ibge' => '',
                            'gia' => '',
                            'ddd' => '',
                            'siafi' => '',
                        ],
                    ],
                ];
            }

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'data' => [
                        'cep' => $dados['cep'] ?? '',
                        'logradouro' => $dados['logradouro'] ?? '',
                        'complemento' => $dados['complemento'] ?? '',
                        'bairro' => $dados['bairro'] ?? '',
                        'cidade' => $dados['localidade'] ?? '',
                        'estado' => $dados['uf'] ?? '',
                        'ibge' => $dados['ibge'] ?? '',
                        'gia' => $dados['gia'] ?? '',
                        'ddd' => $dados['ddd'] ?? '',
                        'siafi' => $dados['siafi'] ?? '',
                    ],
                ],
            ];
        } catch (Throwable $e) {
            error_log('[CepService::buscar] Erro: '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'type' => 'error',
                    'message' => 'Erro ao consultar CEP. Tente novamente mais tarde.',
                ],
            ];
        }
    }
}
