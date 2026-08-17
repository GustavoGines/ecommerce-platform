<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Compra #{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }} — {{ \App\Models\StoreSetting::getSettings()->store_name ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 print:bg-white text-gray-900 min-h-screen py-10 px-4 sm:px-6">

    {{-- Botones de acción (ocultos al imprimir) --}}
    <div class="print:hidden max-w-3xl mx-auto mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <a href="{{ route('home') }}"
           class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900 font-medium transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver a la tienda
        </a>
        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
            <a href="{{ route('my-orders') }}"
               class="w-full sm:w-auto inline-flex justify-center items-center gap-2 px-4 py-2 rounded-full text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:border-gray-300 hover:bg-gray-50 shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Mis órdenes
            </a>
            <button onclick="window.print()"
                    class="w-full sm:w-auto inline-flex justify-center items-center gap-2 px-4 py-2 rounded-full text-sm font-bold text-white shadow-md transition-all hover:opacity-90 bg-emerald-600">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Imprimir / PDF
            </button>
        </div>
    </div>

    {{-- Comprobante --}}
    <div class="max-w-3xl mx-auto bg-white rounded-3xl overflow-hidden shadow-xl border border-gray-200 print:shadow-none print:border-none print:rounded-none">

        {{-- Header verde de éxito --}}
        <div class="bg-gradient-to-r from-emerald-500 to-teal-500 p-6 sm:p-8 text-white print:bg-white print:text-black print:border-b print:border-gray-200 print:p-0 print:pb-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-2 print:hidden">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-white/20 flex items-center justify-center backdrop-blur-sm">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <span class="text-base sm:text-lg font-bold">¡Pago aprobado!</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black">Comprobante de Compra</h1>
                    <p class="text-emerald-50 print:text-gray-500 mt-1 font-medium">Orden #{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</p>
                </div>
                <div class="text-left sm:text-right mt-4 sm:mt-0">
                    <div class="text-3xl sm:text-4xl font-black print:text-gray-900">${{ number_format($order->total, 2) }}</div>
                    <div class="text-emerald-50 print:text-gray-500 text-sm mt-1 font-medium">Total pagado</div>
                </div>
            </div>
        </div>

        {{-- Cuerpo del comprobante --}}
        <div class="p-6 sm:p-8 print:px-0">

            {{-- Datos del comprobante en grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-8 pb-8 border-b border-gray-100 print:border-gray-300">
                <div>
                    <p class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Fecha y hora</p>
                    <p class="text-gray-800 font-bold">{{ $order->created_at->format('d/m/Y \a\l\a\s H:i') }} hs</p>
                </div>
                <div>
                    <p class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Estado</p>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 print:bg-transparent print:p-0 print:text-gray-800">
                        <svg class="w-3.5 h-3.5 mr-1 print:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        {{ ucfirst($order->status) }}
                    </span>
                </div>
                @if($order->mp_payment_id)
                <div>
                    <p class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">N° Operación MP</p>
                    <p class="text-gray-800 font-mono text-sm font-bold">{{ $order->mp_payment_id }}</p>
                </div>
                @endif
                <div>
                    <p class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Tipo de compra</p>
                    <p class="text-gray-800 font-bold">{{ $order->role_applied === 'por_volumen' ? 'Descuento por Volumen' : 'Precio de Lista' }}</p>
                </div>
            </div>

            {{-- Datos del comprador y entrega --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 mb-8 pb-8 border-b border-gray-100 print:border-gray-300">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400 mb-4">Datos del comprador</h2>
                    <div class="space-y-3">
                        <div>
                            <p class="text-[10px] text-gray-400 font-medium uppercase tracking-wider">Nombre</p>
                            <p class="text-gray-800 font-bold">{{ $order->user->name }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-medium uppercase tracking-wider">Email</p>
                            <p class="text-gray-800 font-bold">{{ $order->user->email }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400 mb-4">Forma de entrega</h2>
                    <div class="text-gray-800 font-bold flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center print:hidden">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        Retiro en Local
                    </div>
                </div>
            </div>

            {{-- Lista de productos (Responsive Flex List) --}}
            <div class="mb-8">
                <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400 mb-4">Productos</h2>
                
                <div class="bg-gray-50 rounded-2xl border border-gray-200 overflow-hidden print:bg-transparent print:border-none print:rounded-none">
                    {{-- Encabezado (solo visible en sm+) --}}
                    <div class="hidden sm:flex bg-gray-100 text-xs font-bold uppercase tracking-wider text-gray-500 px-6 py-3 print:bg-transparent print:border-b print:border-gray-300 print:px-0">
                        <div class="flex-1">Producto</div>
                        <div class="w-24 text-center">Cant.</div>
                        <div class="w-32 text-right">P. Unit.</div>
                        <div class="w-32 text-right">Subtotal</div>
                    </div>

                    {{-- Filas de productos --}}
                    <div class="divide-y divide-gray-200 print:divide-gray-300">
                        @foreach($order->items as $item)
                        <div class="flex flex-col sm:flex-row sm:items-center px-4 sm:px-6 py-4 gap-3 sm:gap-0 print:px-0">
                            {{-- Info de Producto --}}
                            <div class="flex items-center gap-3 flex-1">
                                <div class="w-12 h-12 rounded-xl bg-white border border-gray-200 overflow-hidden flex-shrink-0 shadow-sm print:hidden">
                                    @if($item->product && $item->product->image_url)
                                        <img src="{{ tenant_asset($item->product->image_url) }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    @endif
                                </div>
                                <span class="text-sm font-bold text-gray-900">
                                    {{ $item->product ? $item->product->name : 'Producto eliminado' }}
                                </span>
                            </div>
                            
                            {{-- Precios y Cantidad --}}
                            <div class="flex justify-between sm:justify-end items-center sm:gap-4 text-sm mt-3 sm:mt-0 w-full sm:w-auto">
                                <div class="w-auto sm:w-24 text-gray-500 text-left sm:text-center print:text-gray-900">
                                    <span class="sm:hidden font-bold mr-1 text-[10px] uppercase">Cant:</span>{{ $item->quantity }}
                                </div>
                                <div class="w-auto sm:w-32 text-gray-500 text-right print:text-gray-900">
                                    <span class="sm:hidden font-bold mr-1 text-[10px] uppercase">P.U:</span>${{ number_format($item->price, 2) }}
                                </div>
                                <div class="w-auto sm:w-32 font-bold text-gray-900 text-right">
                                    <span class="sm:hidden mr-1 text-[10px] uppercase text-gray-400">Total:</span>${{ number_format($item->price * $item->quantity, 2) }}
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    {{-- Footer del total --}}
                    <div class="bg-gray-100 flex justify-between sm:justify-end items-center px-4 sm:px-6 py-4 sm:py-5 border-t border-gray-200 print:bg-transparent print:border-gray-800 print:px-0">
                        <span class="text-gray-500 font-bold uppercase tracking-wider text-sm sm:mr-8 print:text-gray-900">Total pagado</span>
                        <span class="text-2xl sm:text-3xl font-black text-gray-900 print:!text-gray-900 text-emerald-600">${{ number_format($order->total, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Footer Legal del comprobante --}}
            <div class="text-center text-gray-400 text-xs pt-6">
                <p class="font-bold text-gray-300 uppercase tracking-widest mb-2 print:text-gray-600">Documento no válido como factura</p>
                <p class="print:text-gray-700">{{ \App\Models\StoreSetting::getSettings()->store_name ?? config('app.name') }} • Comprobante generado el {{ now()->format('d/m/Y H:i') }}</p>
                <p class="mt-1 print:text-gray-700">Este comprobante es únicamente una constancia interna de tu pedido.</p>
            </div>
        </div>
    </div>

    <div class="print:hidden max-w-3xl mx-auto mt-6 text-center">
        <p class="text-gray-500 text-sm">¿Necesitás el comprobante en papel? Usá el botón "Imprimir / PDF" de arriba.</p>
    </div>

</body>
</html>
