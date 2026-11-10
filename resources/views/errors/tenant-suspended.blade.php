<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Tienda temporalmente no disponible</title>
    <style>
        :root {
            color-scheme: dark;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at top, #1e293b 0%, #0f172a 70%);
            color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 1.5rem;
        }
        .card {
            max-width: 480px;
            width: 100%;
            background: rgba(30, 41, 59, 0.85);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 1rem;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.45);
        }
        .icon {
            width: 3.5rem;
            height: 3.5rem;
            margin: 0 auto 1.25rem;
            border-radius: 9999px;
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fbbf24;
        }
        h1 {
            font-size: 1.375rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            letter-spacing: -0.01em;
        }
        p {
            font-size: 0.95rem;
            line-height: 1.6;
            color: #cbd5e1;
        }
        .badge {
            display: inline-block;
            margin-top: 1.5rem;
            padding: 0.35rem 0.85rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 9999px;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
        </div>
        <h1>Tienda temporalmente no disponible</h1>
        <p>{{ $message ?? 'Esta tienda se encuentra temporalmente fuera de servicio por tareas de mantenimiento. Por favor, volvé a intentarlo más tarde.' }}</p>
        <span class="badge">Código 503 &middot; Mantenimiento</span>
    </main>
</body>
</html>
