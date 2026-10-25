<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Configurações centrais do produto, disponíveis exclusivamente ao Super Admin. */
class MonetizacaoService
{
    public function listar(string $provedor, string $ambiente): array
    {
        abort_unless(in_array($provedor, ['adsense', 'admob'], true), 404);
        Validator::make(['ambiente' => $ambiente], ['ambiente' => ['required', Rule::in(['development', 'production'])]])->validate();
        $global = DB::table('monetizacao_ambientes')->where('ambiente', $ambiente)->first();
        $ids = DB::table('monetizacao_provedores')->where('provedor', $provedor)->where('ambiente', $ambiente)->orderBy('id')->pluck('id');

        return [
            'global' => ['habilitado' => (bool) $global->habilitado, 'versao' => (int) $global->versao, 'ambiente' => $ambiente],
            'configuracoes' => $ids->map(fn ($id) => $this->configuracao((int) $id))->all(),
            'auditoria' => DB::table('monetizacao_auditoria')->whereIn('provedor_id', $ids)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($r) => ['id' => $r->id, 'usuario_id' => $r->usuario_id, 'provedor_id' => $r->provedor_id, 'created_at' => $r->created_at,
                    'antes' => json_decode($r->antes, true), 'depois' => json_decode($r->depois, true)])->all(),
        ];
    }

    private function configuracao(int $id): array
    {
        $r = DB::table('monetizacao_provedores as p')->join('monetizacao_aplicacoes as a', 'a.id', '=', 'p.aplicacao_id')
            ->where('p.id', $id)->select('p.*', 'a.nome as aplicacao_nome', 'a.identificador as aplicacao', 'a.plataforma')->first();
        abort_unless($r, 404);
        $posicoes = DB::table('monetizacao_posicionamentos')->where('provedor_id', $id)->orderBy('prioridade')->orderBy('id')->get()
            ->map(fn ($p) => ['identificador' => $p->identificador, 'nome' => $p->nome, 'formato' => $p->formato,
                'unidade_id' => $p->unidade_id ?? '', 'rota' => $p->rota ?? '', 'evento' => $p->evento ?? '',
                'habilitado' => (bool) $p->habilitado, 'prioridade' => (int) $p->prioridade])->all();

        return ['id' => (int) $r->id, 'aplicacao' => $r->aplicacao, 'aplicacao_nome' => $r->aplicacao_nome, 'plataforma' => $r->plataforma,
            'provedor' => $r->provedor, 'ambiente' => $r->ambiente, 'habilitado' => (bool) $r->habilitado,
            'publisher_id' => $r->publisher_id ?? '', 'app_id' => $r->app_id ?? '', 'dominio' => $r->dominio ?? '',
            'configuracoes' => json_decode($r->configuracoes, true), 'versao' => (int) $r->versao,
            'updated_at' => $r->updated_at, 'posicionamentos' => $posicoes,
            'integracao_disponivel' => false];
    }

    public function salvar(string $provedor, int $id, array $entrada, ?int $usuarioId): array
    {
        $atual = $this->configuracao($id);
        abort_unless($atual['provedor'] === $provedor, 404);
        $adSense = $provedor === 'adsense';
        $formatos = $adSense ? ['banner'] : ['banner', 'adaptativo', 'interstitial', 'rewarded', 'rewarded_interstitial'];
        $regras = [
            'versao' => 'required|integer|min:1', 'versao_global' => 'required|integer|min:1',
            'global_habilitado' => 'required|boolean', 'habilitado' => 'required|boolean',
            'publisher_id' => $adSense ? ['nullable', 'regex:/^(?:ca-)?pub-\d{16}$/'] : ['nullable', 'prohibited'],
            'app_id' => $adSense ? ['nullable', 'prohibited'] : ['nullable', 'regex:/^ca-app-pub-\d{16}~\d{10}$/'],
            'dominio' => $adSense ? ['nullable', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z]{2,63}$/'] : ['nullable', 'prohibited'],
            'configuracoes' => 'required|array:modo,gratuitos,premium,desktop,mobile_web,exigir_consentimento,somente_maiores,rotas_permitidas,rotas_bloqueadas',
            'configuracoes.modo' => ['required', Rule::in(['manual', 'automatico'])],
            'configuracoes.rotas_permitidas' => 'present|array|max:100', 'configuracoes.rotas_bloqueadas' => 'present|array|max:100',
            'configuracoes.rotas_permitidas.*' => ['string', 'max:200', 'regex:#^/[^\s<>]*$#'],
            'configuracoes.rotas_bloqueadas.*' => ['string', 'max:200', 'regex:#^/[^\s<>]*$#'],
            'posicionamentos' => 'present|array|max:100',
            'posicionamentos.*' => 'array:identificador,nome,formato,unidade_id,rota,evento,habilitado,prioridade',
            'posicionamentos.*.identificador' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'posicionamentos.*.nome' => 'required|string|max:120', 'posicionamentos.*.formato' => ['required', Rule::in($formatos)],
            'posicionamentos.*.unidade_id' => $adSense ? ['nullable', 'regex:/^\d{10}$/'] : ['nullable', 'regex:/^ca-app-pub-\d{16}\/\d{10}$/'],
            'posicionamentos.*.rota' => ['nullable', 'string', 'max:200', 'regex:#^/[^\s<>]*$#'],
            'posicionamentos.*.evento' => ['nullable', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_.:-]+$/'],
            'posicionamentos.*.habilitado' => 'required|boolean', 'posicionamentos.*.prioridade' => 'required|integer|min:0|max:10000',
        ];
        foreach (['gratuitos', 'premium', 'desktop', 'mobile_web', 'exigir_consentimento', 'somente_maiores'] as $k) {
            $regras['configuracoes.'.$k] = 'required|boolean';
        }
        $d = Validator::make($entrada, $regras)->validate();
        if ($d['habilitado'] && ($adSense ? empty($d['publisher_id']) || empty($d['dominio']) : empty($d['app_id']))) {
            throw ValidationException::withMessages(['habilitado' => 'Preencha os identificadores do provedor antes de habilitar a configuração.']);
        }
        foreach ($d['posicionamentos'] as $p) {
            if ($p['habilitado'] && empty($p['unidade_id'])) {
                throw ValidationException::withMessages(['posicionamentos' => 'Posicionamentos habilitados precisam de um identificador de anúncio.']);
            }
        }

        DB::transaction(function () use ($atual, $d, $id, $usuarioId) {
            $global = DB::table('monetizacao_ambientes')->where('ambiente', $atual['ambiente'])->lockForUpdate()->first();
            $registro = DB::table('monetizacao_provedores')->where('id', $id)->lockForUpdate()->first();
            abort_if((int) $registro->versao !== (int) $d['versao'] || (int) $global->versao !== (int) $d['versao_global'], 409, 'A configuração mudou. Recarregue antes de salvar.');
            $antes = $this->configuracao($id) + ['global_habilitado' => (bool) $global->habilitado];
            if ((bool) $global->habilitado !== (bool) $d['global_habilitado']) {
                DB::table('monetizacao_ambientes')->where('ambiente', $atual['ambiente'])->update(['habilitado' => $d['global_habilitado'], 'versao' => $global->versao + 1, 'updated_at' => now()]);
            }
            DB::table('monetizacao_provedores')->where('id', $id)->update([
                'habilitado' => $d['habilitado'], 'publisher_id' => $d['publisher_id'] ?? null, 'app_id' => $d['app_id'] ?? null,
                'dominio' => $d['dominio'] ?? null, 'configuracoes' => json_encode($d['configuracoes']), 'versao' => $registro->versao + 1, 'updated_at' => now(),
            ]);
            $chaves = array_column($d['posicionamentos'], 'identificador');
            DB::table('monetizacao_posicionamentos')->where('provedor_id', $id)->whereNotIn('identificador', $chaves)->delete();
            if ($d['posicionamentos'] !== []) {
                $linhas = array_map(fn ($p) => $p + ['provedor_id' => $id, 'updated_at' => now(), 'created_at' => now()], $d['posicionamentos']);
                DB::table('monetizacao_posicionamentos')->upsert($linhas, ['provedor_id', 'identificador'],
                    ['nome', 'formato', 'unidade_id', 'rota', 'evento', 'habilitado', 'prioridade', 'updated_at']);
            }
            DB::table('monetizacao_auditoria')->insert(['provedor_id' => $id, 'usuario_id' => $usuarioId,
                'antes' => json_encode($antes), 'depois' => json_encode($this->configuracao($id) + ['global_habilitado' => (bool) $d['global_habilitado']]), 'created_at' => now()]);
        });

        return $this->listar($provedor, $atual['ambiente']);
    }
}
