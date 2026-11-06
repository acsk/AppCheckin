<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminTurmaFixaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TurmaFixaController extends Controller
{
    private function tenant(Request $request): int
    {
        $id = (int) $request->attributes->get('tenantId');
        abort_unless($id > 0, 400, 'Nenhum tenant selecionado.');

        return $id;
    }

    public function index(Request $request, AdminTurmaFixaService $service): JsonResponse
    {
        return response()->json($service->listar($this->tenant($request)));
    }

    public function store(Request $request, AdminTurmaFixaService $service): JsonResponse
    {
        return response()->json($service->criar($this->tenant($request), $request->all()), 201);
    }

    public function acesso(Request $request, int $id, AdminTurmaFixaService $service): JsonResponse
    {
        return response()->json($service->vincularMatricula($this->tenant($request), $id, $request->all()));
    }

    public function update(Request $request, int $id, AdminTurmaFixaService $service): JsonResponse
    {
        return response()->json($service->atualizar($this->tenant($request), $id, $request->all()));
    }
}
