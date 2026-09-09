<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhe do erro — AppCheckin</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; background: #f4f6f8; color: #1f2937; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 24px; }
        h1 { font-size: 1.4rem; margin-bottom: 6px; }
        .desc { color: #374151; margin-bottom: 18px; }
        a { color: #ea580c; }
        .card { background: #fff; border-radius: 10px; padding: 16px; margin-bottom: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .meta { font-size: 13px; color: #6b7280; margin-bottom: 8px; }
        pre { background: #111827; color: #e5e7eb; padding: 12px; border-radius: 8px; overflow: auto; font-size: 12px; white-space: pre-wrap; }
    </style>
</head>
<body>
<div class="wrap">
    <p><a href="{{ url('/ops/errors?token='.urlencode(request('token'))) }}">← Voltar</a></p>
    <h1>Detalhes do erro</h1>
    <p class="desc">{{ $description }}</p>
    <p class="meta"><code>{{ $fingerprint }}</code></p>

    @foreach ($events as $event)
        <div class="card">
            <div class="meta">
                #{{ $event['id'] }}
                · {{ $event['created_at'] }}
                · {{ strtoupper($event['level']) }}
                · {{ $event['request_method'] ?? '-' }} {{ $event['request_path'] ?? '' }}
                @if(!empty($event['user_id'])) · user {{ $event['user_id'] }} @endif
                @if(!empty($event['tenant_id'])) · tenant {{ $event['tenant_id'] }} @endif
            </div>
            <pre>{{ $event['message'] }}</pre>
        </div>
    @endforeach
</div>
</body>
</html>
