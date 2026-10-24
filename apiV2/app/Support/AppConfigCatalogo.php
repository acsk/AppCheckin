<?php

namespace App\Support;

/**
 * Catálogo da configuração remota do app: o que existe, de que tipo, com que padrão e limites.
 * O banco guarda só os valores (app_config_valores); chave fora daqui é recusada. O padrão de
 * cada item é o comportamento do app antes da configuração remota, então sem nada publicado
 * (ou sem API) o app continua igual.
 *
 * Chaves: funcionalidade.<k>, rollout.<k>, bloqueio.<k>, parametro.<k>, versao.<k>.
 * escopo: global (só o super admin no geral) ou tenant (pode ser sobrescrito por academia).
 */
final class AppConfigCatalogo
{
    public const VERSAO_CONTRATO = 1;

    /**
     * versao_minima: a partir de qual build nativo o app sabe obedecer à flag (null = todas).
     * papeis: só esses papéis veem a funcionalidade (null = todos).
     */
    public const FUNCIONALIDADES = [
        'checkin' => ['nome' => 'Check-in', 'modulo' => 'Aulas', 'descricao' => 'Aba Check-in e registro de presença nas turmas.'],
        'matriculas' => ['nome' => 'Matrículas', 'modulo' => 'Planos', 'descricao' => 'Aba Planos: contratar e trocar de plano pelo app.'],
        'pagamentos' => ['nome' => 'Pagamentos', 'modulo' => 'Financeiro', 'descricao' => 'Gerar PIX/cartão e pagar pacotes pelo app. Pagamentos já iniciados continuam sendo conferidos.'],
        'cancelamento' => ['nome' => 'Cancelar pelo app', 'modulo' => 'Financeiro', 'descricao' => 'Cancelar assinatura ou diária pelo próprio app.'],
        'ranking' => ['nome' => 'Ranking de check-ins', 'modulo' => 'Início', 'descricao' => 'Pódio e ranking de check-ins na tela inicial.'],
        'recordes' => ['nome' => 'Recordes', 'modulo' => 'Treino', 'descricao' => 'Recordes pessoais, da escola e ranking por prova.'],
        'grupos_whatsapp' => ['nome' => 'Grupos do WhatsApp', 'modulo' => 'Menu', 'descricao' => 'Links dos grupos da academia no menu lateral.'],
    ];

    public const PARAMETROS = [
        'checkin_exigir_matricula_ativa' => ['nome' => 'Exigir matrícula ativa para acessar Check-in', 'modulo' => 'Aulas', 'tipo' => 'booleano', 'padrao' => true, 'escopo' => 'tenant',
            'descricao' => 'Alunos só acessam a tela de Check-in com matrícula ativa e plano vinculado nesta academia. Desligado libera a tela; o registro da presença continua sujeito às regras da API.'],
        'ranking_limite_home' => ['nome' => 'Pessoas no ranking da Home', 'modulo' => 'Início', 'tipo' => 'inteiro', 'min' => 1, 'max' => 100, 'padrao' => 15, 'escopo' => 'tenant',
            'descricao' => 'De 1 a 100. O pódio continua mostrando os três campeões.'],
        'aviso_home' => ['nome' => 'Aviso na tela inicial', 'modulo' => 'Início', 'tipo' => 'texto', 'max' => 280, 'padrao' => '', 'escopo' => 'tenant',
            'descricao' => 'Mensagem curta no topo da Home. Vazio = sem aviso.'],
        'cache_ttl_segundos' => ['nome' => 'Intervalo de atualização da configuração', 'modulo' => 'Sistema', 'tipo' => 'inteiro', 'min' => 60, 'max' => 86400, 'padrao' => 300, 'escopo' => 'global',
            'descricao' => 'Em segundos. O app busca a configuração de novo depois desse tempo (e sempre ao abrir).'],
    ];

    /** Todas as definições, indexadas pela chave completa. */
    public static function definicoes(): array
    {
        $defs = [];
        foreach (self::FUNCIONALIDADES as $k => $f) {
            $base = ['grupo' => 'funcionalidade', 'item' => $k, 'nome' => $f['nome'], 'modulo' => $f['modulo']];
            $defs["funcionalidade.{$k}"] = $base + ['tipo' => 'booleano', 'padrao' => true, 'escopo' => 'tenant', 'descricao' => $f['descricao'],
                'versao_minima' => $f['versao_minima'] ?? ['android' => null, 'ios' => null], 'papeis' => $f['papeis'] ?? null];
            $defs["rollout.{$k}"] = $base + ['tipo' => 'inteiro', 'min' => 0, 'max' => 100, 'padrao' => 100, 'escopo' => 'global', 'descricao' => 'Percentual de usuários que recebem a funcionalidade.'];
            // Kill switch: só pela ação de bloqueio (publica na hora); nenhuma sobrescrita religa
            $defs["bloqueio.{$k}"] = $base + ['tipo' => 'booleano', 'padrao' => false, 'escopo' => 'global', 'descricao' => 'Desligamento emergencial.', 'interno' => true];
        }
        foreach (self::PARAMETROS as $k => $p) {
            $defs["parametro.{$k}"] = ['grupo' => 'parametro', 'item' => $k] + $p;
        }

        $android = config('appcheckin.android_release', []);
        $versoes = [
            'web_obrigatoria' => ['nome' => 'Atualização obrigatória (web)', 'tipo' => 'booleano', 'padrao' => true,
                'descricao' => 'Quem está numa versão anterior do app web fica bloqueado até atualizar; desligado, só vê um aviso.'],
            'mensagem_atualizacao' => ['nome' => 'Mensagem de atualização', 'tipo' => 'texto', 'max' => 200, 'padrao' => '',
                'descricao' => 'Opcional. Aparece no aviso de nova versão.'],
            'android_versao_recomendada' => ['nome' => 'Android: versão publicada (APK)', 'tipo' => 'versao', 'padrao' => (string) ($android['version'] ?? ''),
                'descricao' => 'Quem tem versão menor vê "Nova versão disponível".'],
            'android_versao_minima' => ['nome' => 'Android: versão mínima', 'tipo' => 'versao', 'padrao' => '',
                'descricao' => 'Abaixo dela o app fica bloqueado até atualizar. Vazio = nunca bloqueia.'],
            'android_apk_url' => ['nome' => 'Android: link do APK', 'tipo' => 'url', 'padrao' => (string) ($android['apk_url'] ?? ''), 'descricao' => 'Endereço https do APK oficial.'],
            'ios_versao_recomendada' => ['nome' => 'iOS: versão publicada', 'tipo' => 'versao', 'padrao' => '', 'descricao' => 'Para quando o app estiver na App Store.'],
            'ios_versao_minima' => ['nome' => 'iOS: versão mínima', 'tipo' => 'versao', 'padrao' => '', 'descricao' => 'Abaixo dela o app fica bloqueado até atualizar.'],
            'ios_loja_url' => ['nome' => 'iOS: link da App Store', 'tipo' => 'url', 'padrao' => '', 'descricao' => 'Endereço https da página na App Store.'],
        ];
        foreach ($versoes as $k => $v) {
            $defs["versao.{$k}"] = ['grupo' => 'versao', 'item' => $k, 'modulo' => 'Versões', 'escopo' => 'global'] + $v;
        }

        return $defs;
    }

    /** @return array<string, mixed>|null */
    public static function definicao(string $chave): ?array
    {
        return self::definicoes()[$chave] ?? null;
    }

    /**
     * Valida e normaliza um valor para a chave. Devolve [valor, erro].
     *
     * @return array{0: mixed, 1: string|null}
     */
    public static function validar(string $chave, mixed $valor): array
    {
        $d = self::definicao($chave);
        if (! $d) {
            return [null, 'Configuração desconhecida.'];
        }

        switch ($d['tipo']) {
            case 'booleano':
                $b = filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                return $b === null ? [null, 'Use ligado ou desligado.'] : [$b, null];
            case 'inteiro':
                if (! is_numeric($valor) || (int) $valor != $valor) {
                    return [null, 'Informe um número inteiro.'];
                }
                $n = (int) $valor;
                if ($n < $d['min'] || $n > $d['max']) {
                    return [null, "Use um valor entre {$d['min']} e {$d['max']}."];
                }

                return [$n, null];
            case 'texto':
                $t = trim((string) $valor);
                if (mb_strlen($t) > $d['max']) {
                    return [null, "Use no máximo {$d['max']} caracteres."];
                }

                return [$t, null];
            case 'versao':
                $t = ltrim(trim((string) $valor), 'vV');

                return $t === '' || preg_match('/^\d{1,4}(\.\d{1,4}){0,2}$/', $t) ? [$t, null] : [null, 'Use o formato 1.2.3.'];
            case 'url':
                $t = trim((string) $valor);

                return $t === '' || (filter_var($t, FILTER_VALIDATE_URL) && str_starts_with($t, 'https://')) ? [$t, null] : [null, 'Use um endereço https:// válido.'];
        }

        return [null, 'Tipo não suportado.'];
    }

    /** Lista para o painel (sem os itens internos). */
    public static function lista(): array
    {
        $out = [];
        foreach (self::definicoes() as $chave => $d) {
            if (empty($d['interno'])) {
                $out[] = ['chave' => $chave] + $d;
            }
        }

        return $out;
    }

    /** Compara versões "1.2.3" / "v1.47" (<0, 0, >0). */
    public static function compararVersoes(string $a, string $b): int
    {
        $pa = array_map('intval', explode('.', ltrim($a, 'vV')));
        $pb = array_map('intval', explode('.', ltrim($b, 'vV')));
        $n = max(count($pa), count($pb));
        for ($i = 0; $i < $n; $i++) {
            $d = ($pa[$i] ?? 0) <=> ($pb[$i] ?? 0);
            if ($d !== 0) {
                return $d;
            }
        }

        return 0;
    }
}
