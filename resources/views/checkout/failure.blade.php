<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago fallido — {{ \App\Models\StoreSetting::getSettings()->store_name ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-950 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full text-center">
        <div class="mb-6 flex justify-center">
            <div class="w-24 h-24 rounded-full bg-red-500/20 border border-red-500/40 flex items-center justify-center">
                <svg class="w-12 h-12 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>
        </div>
        <h1 class="text-3xl font-black text-white mb-2">Pago no procesado</h1>
        <p class="text-gray-400 mb-2">Tu orden <span class="text-white font-bold">#{{ $order->id }}</span> no pudo ser cobrada.</p>
        <p class="text-gray-500 text-sm mb-8">Podés intentarlo de nuevo con otro medio de pago. Tu carrito fue conservado.</p>
        <div class="flex gap-4 justify-center">
            <a href="{{ route('checkout') }}"
               class="inline-flex items-center gap-2 px-8 py-3 rounded-full text-white font-bold transition-all hover:opacity-90"
               style="background-color: var(--color-primary)">
                Reintentar pago
            </a>
            <a href="{{ route('home') }}"
               class="inline-flex items-center gap-2 px-8 py-3 rounded-full text-gray-300 font-bold border border-gray-700 hover:border-gray-500 transition-all">
                Volver a la tienda
            </a>
        </div>
    </div>
</body>
</html>
