<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acesso negado — {{ config('app.name', 'SIE') }}</title>
    <style>
        *{ box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Instrument Sans', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            display: grid;
            place-items: center;
            padding: 24px;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #0b1120; color: #e2e8f0; }
            .card { background: #0f172a; border-color: #1e293b; }
            .msg { color: #94a3b8; }
            .btn-ghost { color: #cbd5e1; border-color: #334155; }
        }
        .card {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 40px 32px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(2, 6, 23, 0.06);
        }
        .badge {
            width: 76px; height: 76px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: grid; place-items: center;
            background: linear-gradient(135deg, #6366f1, #d946ef);
            color: #fff;
        }
        .badge svg { width: 38px; height: 38px; }
        .code { font-size: 13px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #d946ef; }
        h1 { font-size: 22px; font-weight: 700; margin: 6px 0 10px; }
        .msg { font-size: 14.5px; line-height: 1.6; color: #64748b; }
        .actions { margin-top: 26px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 18px; border-radius: 10px;
            font-size: 14px; font-weight: 600; text-decoration: none;
            border: 1px solid transparent; cursor: pointer;
        }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #d946ef); color: #fff; }
        .btn-primary:hover { filter: brightness(1.05); }
        .btn-ghost { background: transparent; color: #475569; border-color: #e2e8f0; }
        .btn-ghost:hover { background: rgba(100,116,139,.08); }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>
        <div class="code">Erro 403</div>
        <h1>Você não tem acesso a esta página</h1>
        <p class="msg">
            {{ $exception?->getMessage() ?: 'Seu perfil não tem permissão para acessar este recurso. Se acha que isso é um engano, fale com o administrador do sistema.' }}
        </p>
        <div class="actions">
            <a href="javascript:history.back()" class="btn btn-ghost">← Voltar</a>
            <a href="{{ url('/dashboard') }}" class="btn btn-primary">Ir para o Painel</a>
        </div>
    </div>
</body>
</html>
