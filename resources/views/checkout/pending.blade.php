<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago pendiente — {{ \App\Models\StoreSetting::getSettings()->store_name ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-950 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full text-center">
        <div class="mb-6 flex justify-center">
            <div class="w-24 h-24 rounded-full bg-yellow-500/20 border border-yellow-500/40 flex items-center justify-center">
                <svg class="w-12 h-12 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
        <h1 class="text-3xl font-black text-white mb-2">Pago en proceso</h1>
        <p class="text-gray-400 mb-2">Tu orden <span class="text-white font-bold">#{{ $order->id }}</span> está siendo procesada.</p>
        <p class="text-gray-500 text-sm mb-8">Algunos medios de pago pueden demorar hasta 72hs en confirmarse (ej: transferencias). Te notificaremos cuando acreditemos el pago.</p>
        <a href="{{ route('my-orders') }}"
           class="inline-flex items-center gap-2 px-8 py-3 rounded-full text-white font-bold transition-all hover:opacity-90"
           style="background-color: var(--color-primary)">
            Ver estado de mi orden
        </a>
    </div>
</body>
</html>
