<?php

namespace Database\Seeders;

use App\Support\SexoAluno;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Carga inicial de alunos.sexo.
 *
 * - A lista abaixo (id;nome) vira "feminino". O id é conferido pelo nome: se o nome do aluno
 *   com esse id não bater, tenta alunos.usuario_id; sem nome igual, a linha é pulada e listada.
 * - "O restante" = demais alunos dos MESMOS tenants dessas alunas, ainda sem sexo → "masculino".
 *   Quem já tem sexo preenchido não é alterado, então rodar de novo é seguro.
 *
 * php artisan migrate && php artisan db:seed --class=AlunoSexoSeeder
 */
class AlunoSexoSeeder extends Seeder
{
    private const FEMININO = <<<'TXT'
355;CAROLINNE RODRIGUES DOS SANTOS
350;SARAH BARBOSA
347;AYLA LIMA PORTO
342;LETÍCIA DA SILVA NUNES
340;GLÓRIA MARIA LEMOS MOREIRA
337;ALINE RAFAELA ARAÚJO DOS SANTOS
335;LINDINÊS COLETA DA SILVA
334;GABRIELA DE LIMA SILVA
197;ANA JÚLIA SANTOS
320;PAULA FERNANDA DOS SANTOS SILVA
329;THAYEMI ELLEN
327;ANA CAROLINA DOS SANTOS SILVA
325;YANNE CLARA LINS DE ALCÂNTARA
322;SÂMMYA INGRID BARBOSA DUARTE
202;MARIA FLÁVIA BRITO SILVA
315;KRISLAYNE MILENE
226;KÍSSYA ROLIM
311;RAFAELA LARISSA MELO DE PAIVA
308;RIANNY ANGELO AMORIM
310;ANIDAYÊ ANGELO AMORIM
306;KAUANNY ANGELO AMORIM
307;SHIRLEY SANTOS
305;MARIA CLARA ALVES DE CARVALHO
304;ILDENICE VIANA
271;MARIA GRAZIELLY DE MENEZES
277;WANESSA DA SILVA SANTOS
276;MARIA ANTONIA DOS SANTOS NETA
272;CAMILY VITÓRIA SILVA ALVES
268;ERICA MARIA CAVALCANTE DOS SANTOS
265;GABRIELA BARROS SIQUEIRA
252;JOSEANE GOMES
251;ELIANE MIDIAN BARBOSA
235;ALINE SANTOS DE FREITAS
218;MARIA LUIZA PEREIRA CAVALCANTE SILVA
214;KAREN NICOLI ALVES LIMA
205;RENATA MACEDO DE MELO SOARES
206;RIQUELE GAMA DOS SANTOS
134;LEANE ARAÚJO
161;JHENIFFER RAYSSA MENEZES BRITO
170;MÔNICA COLATINO DE LIMA ALVES
169;MARIA JASMINE SANTOS SILVA
167;HELLOÁ VICTÓRIA FARIAS DE OLIVEIRA
1;VISSIA VITÓRIA FERRO LIMA
102;POLIANA DOS SANTOS SILVA
114;ALESSANDRA NUNES
110;LUCIANA SALGUEIRO
111;GABRIELA OLIVEIRA
115;VALÉRIA SALGUEIRO SANTOS MARTINS
103;KÉSIA VAZ
112;MARIA BEATRIZ SANTOS OLIVEIRA
83;LUANNY VICTORIA
74;MANUELLY NUNES DA SILVA
67;ROSANGELA DA ROCHA SIQUEIRA TAVARES
35;RAFAELLE CAVALCANTI
30;VIVIANE SILVA
11;STEFFANY SOARES DA SILVA
6;ANA PAULA FERREIRA
5;ITAMARA DE SOUZA SANTOS
4;MILENA KELMANY DA SILVA
TXT;

    public function run(): void
    {
        if (! SexoAluno::colunaExiste()) {
            $this->command?->error('Coluna alunos.sexo não existe. Rode "php artisan migrate" antes.');

            return;
        }

        $resultado = $this->aplicar();

        $this->command?->info("Feminino: {$resultado['femininas']} aluna(s) atualizada(s).");
        $this->command?->info("Masculino: {$resultado['masculinos']} aluno(s) sem sexo atualizado(s) nos tenants ".implode(', ', $resultado['tenants']).'.');
        foreach ($resultado['nao_encontrados'] as $linha) {
            $this->command?->warn("Não encontrado (id e nome não batem): {$linha}");
        }
    }

    /**
     * @return array{femininas: int, masculinos: int, tenants: int[], nao_encontrados: string[]}
     */
    public function aplicar(): array
    {
        return DB::transaction(function () {
            $alunoIds = [];
            $naoEncontrados = [];

            foreach (self::lista() as [$id, $nome]) {
                $alunoId = $this->localizar($id, $nome);
                if ($alunoId === null) {
                    $naoEncontrados[] = "{$id};{$nome}";

                    continue;
                }
                $alunoIds[] = $alunoId;
            }

            $alunoIds = array_values(array_unique($alunoIds));
            $femininas = $alunoIds === []
                ? 0
                : DB::table('alunos')->whereIn('id', $alunoIds)->update(['sexo' => SexoAluno::FEMININO]);

            $usuarioIds = DB::table('alunos')->whereIn('id', $alunoIds)->pluck('usuario_id')->filter()->all();
            $tenants = $usuarioIds === []
                ? []
                : DB::table('tenant_usuario_papel')
                    ->whereIn('usuario_id', $usuarioIds)
                    ->where('papel_id', 1)
                    ->distinct()
                    ->orderBy('tenant_id')
                    ->pluck('tenant_id')
                    ->map(fn ($t) => (int) $t)
                    ->all();

            $masculinos = 0;
            if ($tenants !== []) {
                $masculinos = DB::table('alunos')
                    ->whereNull('sexo')
                    ->whereNotIn('id', $alunoIds)
                    ->whereIn('usuario_id', function ($q) use ($tenants) {
                        $q->select('usuario_id')
                            ->from('tenant_usuario_papel')
                            ->whereIn('tenant_id', $tenants)
                            ->where('papel_id', 1);
                    })
                    ->update(['sexo' => SexoAluno::MASCULINO]);
            }

            return [
                'femininas' => $femininas,
                'masculinos' => $masculinos,
                'tenants' => $tenants,
                'nao_encontrados' => $naoEncontrados,
            ];
        });
    }

    /** @return array<int, array{0: int, 1: string}> */
    public static function lista(): array
    {
        $linhas = [];
        foreach (preg_split('/\R/', trim(self::FEMININO)) as $linha) {
            [$id, $nome] = array_map('trim', explode(';', $linha, 2));
            $linhas[] = [(int) $id, $nome];
        }

        return $linhas;
    }

    /** Aluno pelo id (ou usuario_id), só se o nome confere. */
    private function localizar(int $id, string $nome): ?int
    {
        foreach (['id', 'usuario_id'] as $coluna) {
            $aluno = DB::table('alunos')->where($coluna, $id)->first(['id', 'nome']);
            if ($aluno && self::mesmoNome((string) $aluno->nome, $nome)) {
                return (int) $aluno->id;
            }
        }

        return null;
    }

    /** Compara sem acento, caixa e espaços repetidos. */
    public static function mesmoNome(string $a, string $b): bool
    {
        $norm = fn (string $s) => preg_replace('/\s+/', ' ', mb_strtoupper(Str::ascii(trim($s)), 'UTF-8'));

        return $norm($a) === $norm($b);
    }
}
