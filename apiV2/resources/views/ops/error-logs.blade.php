<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erros apiV2 — AppCheckin</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; background: #f4f6f8; color: #1f2937; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 24px; }
        h1 { margin: 0 0 8px; font-size: 1.6rem; }
        .meta { color: #6b7280; margin-bottom: 20px; }
        .badge { display: inline-block; background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 999px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        th, td { padding: 12px 14px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
        th { background: #111827; color: #fff; font-size: 13px; }
        tr:hover td { background: #f9fafb; }
        a { color: #ea580c; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .desc { font-weight: 600; max-width: 420px; word-break: break-word; }
        .empty { background: #fff; padding: 24px; border-radius: 10px; text-align: center; color: #6b7280; }
        .warn { background: #fff7ed; border: 1px solid #fdba74; color: #9a3412; padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Erros da API v2</h1>
    <p class="meta">
        Total de eventos: <strong>{{ number_format($totalEvents, 0, ',', '.') }}</strong>
        · Alertas por e-mail: <strong>{{ $alertEmail ?: 'não configurado' }}</strong>
        · Horários em <strong>BRT</strong>
    </p>

    @if (!$tableReady)
        <div class="warn">
            Tabela <code>application_error_logs</code> não existe. Rode a migration ou o SQL em
            <code>database/sql/application_error_logs.sql</code>.
        </div>
    @endif

    @if (count($groups) === 0)
        <div class="empty">Nenhum erro registrado ainda.</div>
    @else
        <table>
            <thead>
            <tr>
                <th>Descrição</th>
                <th>Ocorrências</th>
                <th>Primeira</th>
                <th>Última</th>
                <th>Nível</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($groups as $group)
                <tr>
                    <td class="desc">{{ $group['description'] }}</td>
                    <td><span class="badge">{{ $group['occurrences'] }}</span></td>
                    <td>{{ \App\Support\DisplayDateTime::label($group['first_seen']) }}</td>
                    <td>{{ \App\Support\DisplayDateTime::label($group['last_seen']) }}</td>
                    <td>{{ strtoupper($group['max_level'] ?? 'ERROR') }}</td>
                    <td>
                        <a href="{{ url('/ops/errors/'.$group['fingerprint'].'?token='.urlencode(request('token'))) }}">Detalhes</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
</body>
</html>
