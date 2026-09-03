<?php

namespace App\Services;

use App\Repositories\FormaPagamentoCatalogRepository;
use Illuminate\Http\Request;

class FormaPagamentoCatalogService
{
    public function __construct(
        private readonly FormaPagamentoCatalogRepository $formas,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(Request $request): array
    {
        $tenantId = $request->attributes->get('tenantId');

        return [
            'status' => 200,
            'body' => [
                'formas' => $this->formas->listarTodas($tenantId ? (int) $tenantId : null),
            ],
        ];
    }
}
