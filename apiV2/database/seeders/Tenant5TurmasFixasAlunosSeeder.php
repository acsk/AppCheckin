<?php

namespace Database\Seeders;

use App\Repositories\UsuarioRepository;
use App\Support\Papel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Tenant5TurmasFixasAlunosSeeder extends Seeder
{
    public function run(): void
    {
        $senha = getenv('TURMAS_FIXAS_TEST_PASSWORD');
        if (! is_string($senha) || strlen($senha) < 12) {
            throw new RuntimeException('Defina TURMAS_FIXAS_TEST_PASSWORD com pelo menos 12 caracteres antes de executar o seed.');
        }

        DB::transaction(function () use ($senha) {
            $tenant = DB::table('tenants')->where('id', 5)->lockForUpdate()->first();
            if (! $tenant || ! $tenant->ativo) {
                throw new RuntimeException('O tenant 5 precisa existir e estar ativo. O seed não cria nem altera tenants.');
            }

            foreach (['01', '02', '03'] as $numero) {
                $nome = "ALUNO TESTE TURMAS {$numero}";
                $email = "appcheckin-tenant5-turmas-{$numero}@example.invalid";
                $usuario = DB::table('usuarios')->where(function ($query) use ($email) {
                    $query->where('email', $email)->orWhere('email_global', $email);
                })->lockForUpdate()->get();

                if ($usuario->isNotEmpty()) {
                    $existente = $usuario->first();
                    $vinculado = DB::table('tenant_usuario_papel')
                        ->where('usuario_id', $existente->id)->where('tenant_id', 5)
                        ->where('papel_id', Papel::ALUNO)->where('ativo', 1)->exists();
                    $aluno = DB::table('alunos')->where('usuario_id', $existente->id)->where('ativo', 1)->exists();
                    if ($usuario->count() !== 1 || $existente->nome !== $nome || ! $existente->ativo || ! $vinculado || ! $aluno) {
                        throw new RuntimeException("O e-mail {$email} já existe com dados ou vínculos diferentes. Nenhum cadastro será sobrescrito.");
                    }

                    continue;
                }

                $id = app(UsuarioRepository::class)->createUsuario([
                    'nome' => $nome, 'email' => $email, 'senha' => $senha, 'ativo' => 1,
                ], 5, Papel::ALUNO);
                if ($id === null) {
                    throw new RuntimeException("Não foi possível criar {$email}. A transação do seed será desfeita.");
                }
            }
        });

        $this->command?->info('Três alunos de teste disponíveis no tenant 5. Matrículas, turmas e cobranças não foram alteradas.');
    }
}
