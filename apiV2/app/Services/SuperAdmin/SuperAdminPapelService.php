<?php

namespace App\Services\SuperAdmin;

use Illuminate\Support\Facades\DB;

class SuperAdminPapelService
{
    /**
     * GET /superadmin/papeis
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarPapeis(): array
    {
        $papeis = [
            ['id' => 1, 'nome' => 'Aluno', 'descricao' => 'Pode acessar o app mobile e fazer check-in'],
            ['id' => 2, 'nome' => 'Professor', 'descricao' => 'Pode marcar presença e gerenciar turmas'],
            ['id' => 3, 'nome' => 'Admin', 'descricao' => 'Pode acessar o painel administrativo'],
        ];

        return [
            'status' => 200,
            'body' => ['papeis' => $papeis],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getPapeisMapa(): array
    {
        $papeis = DB::table('papeis')
            ->where('ativo', 1)
            ->orderBy('id')
            ->get(['id', 'nome']);

        $mapa = [];
        foreach ($papeis as $papel) {
            $mapa[(int) $papel->id] = ucfirst((string) $papel->nome);
        }

        return $mapa;
    }
}
