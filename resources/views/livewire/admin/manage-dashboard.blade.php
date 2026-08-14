<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\PageVisit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    
    public function resetVisits()
    {
        PageVisit::truncate();
        session()->forget('last_visit_date');
        $this->dispatch('visitsResetted');
    }


    public function with(): array
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // Ingresos Hoy
        $revenueToday = Order::whereIn('status', [\App\Enums\OrderStatus::Paid, \App\Enums\OrderStatus::Completed])
            ->whereDate('created_at', $today)
            ->sum('total');

        // Ingresos Mes
        $revenueMonth = Order::whereIn('status', [\App\Enums\OrderStatus::Paid, \App\Enums\OrderStatus::Completed])
            ->where('created_at', '>=', $startOfMonth)
            ->sum('total');

        // Ingresos Mes Anterior (para %)
        $revenueLastMonth = Order::whereIn('status', [\App\Enums\OrderStatus::Paid, \App\Enums\OrderStatus::Completed])
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('total');
            
        if ($revenueLastMonth > 0) {
            $revenueGrowth = (($revenueMonth - $revenueLastMonth) / $revenueLastMonth) * 100;
        } elseif ($revenueMonth > 0) {
            $revenueGrowth = 100; // Pasó de 0 a tener ventas
        } else {
            $revenueGrowth = 0;
        }

        // Órdenes Hoy
        $ordersTodayCount = Order::whereDate('created_at', $today)->count();

        // Órdenes Pendientes Críticas (> 24hs)
        $pendingCritical = Order::where('status', \App\Enums\OrderStatus::Pending)
            ->where('created_at', '<', Carbon::now()->subHours(24))
            ->count();

        // Stock Valor (Costo) - Ignoramos los que tienen stock 999 (Modelo a Pedido)
        $stockValue = Product::where('stock', '<', 900)->select(DB::raw('SUM(stock * cost_price) as total_value'))->value('total_value') ?? 0;

        // Ticket Promedio (Mes)
        $avgTicket = Order::whereIn('status', [\App\Enums\OrderStatus::Paid, \App\Enums\OrderStatus::Completed])
            ->where('created_at', '>=', $startOfMonth)
            ->avg('total') ?? 0;

        // Top 5 Productos sin stock
        $outOfStock = Product::whereColumn('stock', '<=', 'min_stock')->take(5)->get();
        $outOfStockCount = Product::whereColumn('stock', '<=', 'min_stock')->count();

        // Top 5 Productos más vendidos (solo de órdenes pagadas/completadas)
        $topSelling = \App\Models\OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'))
            ->whereHas('order', function($q) {
                $q->whereIn('status', [\App\Enums\OrderStatus::Paid, \App\Enums\OrderStatus::Completed]);
            })
            ->with('product')
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->take(5)
            ->get();

        // Visitas a la página
        $visitsToday = PageVisit::whereDate('created_at', $today)->count();
        $visitsWeek = PageVisit::where('created_at', '>=', Carbon::now()->startOfWeek())->count();
        $visitsMonth = PageVisit::where('created_at', '>=', $startOfMonth)->count();

        // Últimas 5 Órdenes
        $latestOrders = Order::with('user')->latest()->take(5)->get();

        // Gráfico de Ingresos Diarios (Últimos 30 días)
        $dailyRevenueRaw = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total) as daily_total')
            )
            ->whereIn('status', [\App\Enums\OrderStatus::Paid, \App\Enums\OrderStatus::Completed])
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'ASC')
            ->get();
            
        $dailyRevenue = [];
        $dailyRevenueLabels = [];
        // Fill missing days with 0
        for ($i = 29; $i >= 0; $i--) {
            $dateStr = Carbon::now()->subDays($i)->format('Y-m-d');
            $dailyRevenueLabels[] = Carbon::now()->subDays($i)->format('d/m');
            $match = $dailyRevenueRaw->firstWhere('date', $dateStr);
            $dailyRevenue[] = $match ? (float)$match->daily_total : 0;
        }

        // Gráfico de Órdenes por Estado (Mes Actual)
        $ordersByStatus = Order::select('status', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $startOfMonth)
            ->groupBy('status')
            ->pluck('count', 'status')->toArray();

        return compact(
            'revenueToday', 'revenueMonth', 'revenueGrowth', 'ordersTodayCount', 
            'pendingCritical', 'stockValue', 'avgTicket', 'outOfStock', 
            'outOfStockCount', 'topSelling', 'latestOrders',
            'dailyRevenue', 'dailyRevenueLabels', 'ordersByStatus',
            'visitsToday', 'visitsWeek', 'visitsMonth'
        );
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data>
    <div class="mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">Panel de Control</h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Resumen de estadísticas y salud del negocio</p>
        </div>
        <div class="grid grid-cols-2 sm:flex sm:flex-row gap-2 w-full sm:w-auto">

            <a href="{{ route('admin.orders') }}" class="w-full inline-flex justify-center items-center gap-1.5 px-2 sm:px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-xs sm:text-sm font-semibold text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                Órdenes
            </a>
            <a href="{{ route('admin.products') }}" class="w-full inline-flex justify-center items-center gap-1.5 px-2 sm:px-4 py-2 bg-[var(--color-primary)] text-white rounded-xl text-xs sm:text-sm font-bold shadow-sm hover:opacity-90 transition-opacity">
                Productos
            </a>
        </div>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-4 sm:gap-6 mb-8">
        <!-- Ingresos Mes -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 sm:p-6 border border-gray-200 dark:border-gray-700 shadow-sm flex flex-col justify-between relative overflow-hidden transition-all hover:shadow-md hover:-translate-y-1">
            <div>
                <p class="text-[9px] sm:text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5">Facturación del Mes</p>
                <h3 class="text-xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight">${{ number_format($revenueMonth, 0, ',', '.') }}</h3>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-xs font-bold {{ $revenueGrowth >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="{{ $revenueGrowth >= 0 ? 'M5 10l7-7m0 0l7 7m-7-7v18' : 'M19 14l-7 7m0 0l-7-7m7 7V3' }}" />
                </svg>
                {{ number_format(abs($revenueGrowth), 1) }}% vs mes anterior
            </div>
        </div>

        <!-- Ingresos Hoy -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 sm:p-6 border border-gray-200 dark:border-gray-700 shadow-sm flex flex-col justify-between relative overflow-hidden transition-all hover:shadow-md hover:-translate-y-1">
            <div>
                <p class="text-[9px] sm:text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5">La Caja de Hoy</p>
                <h3 class="text-xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight">${{ number_format($revenueToday, 0, ',', '.') }}</h3>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-gray-400">
                <span>{{ $ordersTodayCount }} ventas hoy</span>
            </div>
        </div>

        <!-- Ticket Promedio -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 sm:p-6 border border-gray-200 dark:border-gray-700 shadow-sm flex flex-col justify-between relative overflow-hidden transition-all hover:shadow-md hover:-translate-y-1">
            <div>
                <p class="text-[9px] sm:text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5">Ticket Promedio</p>
                <h3 class="text-xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight">${{ number_format($avgTicket, 0, ',', '.') }}</h3>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-gray-400">
                <span>Del mes actual</span>
            </div>
        </div>

        <!-- Órdenes Pendientes -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 sm:p-6 border border-gray-200 dark:border-gray-700 shadow-sm flex flex-col justify-between relative overflow-hidden transition-all hover:shadow-md hover:-translate-y-1 {{ $pendingCritical > 0 ? 'ring-2 ring-rose-500 ring-inset' : '' }}">
            <div>
                <p class="text-[9px] sm:text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5">Pedidos Colgados</p>
                <h3 class="text-xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ $pendingCritical }}</h3>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-xs font-bold {{ $pendingCritical > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                <span>> 24hs sin atender</span>
            </div>
        </div>

        <!-- Visitas a la página -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 sm:p-6 border border-gray-200 dark:border-gray-700 shadow-sm flex flex-col justify-between relative overflow-hidden transition-all hover:shadow-md hover:-translate-y-1 group">
            <div>
                <p class="text-[9px] sm:text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5">Visitas a la Tienda</p>
                <div class="flex items-baseline gap-2">
                    <h3 class="text-xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ number_format($visitsToday, 0, ',', '.') }}</h3>
                    <span class="text-[10px] font-bold text-gray-400 uppercase">hoy</span>
                </div>
            </div>
            <div class="mt-4 flex flex-col gap-0.5 text-[10px] font-bold text-gray-500 dark:text-gray-400 relative">
                <span>Esta semana: {{ number_format($visitsWeek, 0, ',', '.') }}</span>
                <span>Este mes: {{ number_format($visitsMonth, 0, ',', '.') }}</span>
                
                {{-- Botón de reseteo de visitas --}}
                <button wire:click="resetVisits" wire:confirm="¿Estás seguro de que quieres borrar el historial de visitas a CERO? Esto no se puede deshacer." 
                        class="absolute right-0 bottom-0 opacity-0 group-hover:opacity-100 transition-opacity bg-rose-100 text-rose-700 hover:bg-rose-600 hover:text-white px-2 py-1 rounded text-[9px] uppercase tracking-wider font-bold">
                    Resetear
                </button>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Gráfico Ingresos Diarios -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="mb-4">
                <h3 class="text-base font-black tracking-tight text-gray-900 dark:text-white">Ingresos (Últimos 30 días)</h3>
            </div>
            <div class="h-48 sm:h-64 relative">
                <canvas id="dailyRevenueChart"></canvas>
            </div>
        </div>

        <!-- Gráfico Estado de Órdenes -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="mb-4">
                <h3 class="text-base font-black tracking-tight text-gray-900 dark:text-white">Estado de Órdenes (Mes)</h3>
            </div>
            <div class="h-48 sm:h-64 relative flex justify-center">
                <canvas id="ordersStatusChart"></canvas>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Últimas Órdenes -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm lg:col-span-2 overflow-hidden flex flex-col">
            <div class="p-6 border-b border-gray-100 dark:border-gray-700/50 flex justify-between items-center">
                <h3 class="text-base font-black tracking-tight text-gray-900 dark:text-white">Últimas Órdenes</h3>
                <a href="{{ route('admin.orders') }}" class="text-xs font-bold uppercase tracking-wider text-[var(--color-primary)] hover:underline">Ver todas</a>
            </div>
            <div class="overflow-x-auto flex-grow hidden md:block">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-700/50 bg-gray-50/50 dark:bg-gray-800/30 text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Orden</th>
                            <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-700/50 bg-gray-50/50 dark:bg-gray-800/30 text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Cliente</th>
                            <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-700/50 bg-gray-50/50 dark:bg-gray-800/30 text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Total</th>
                            <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-700/50 bg-gray-50/50 dark:bg-gray-800/30 text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Estado</th>
                            <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-700/50 bg-gray-50/50 dark:bg-gray-800/30 text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest text-right">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                        @forelse($latestOrders as $order)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-black text-gray-900 dark:text-white">#{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $order->user?->name ?? 'Invitado' }}</div>
                                <div class="text-xs font-medium text-gray-500">{{ $order->user?->email }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-black text-gray-900 dark:text-white">${{ number_format($order->total, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 inline-flex text-[10px] font-black uppercase tracking-widest rounded-md 
                                    @if($order->status === 'pagado' || $order->status === 'completado') bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400
                                    @elseif($order->status === 'pendiente') bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400
                                    @elseif($order->status === 'cancelado') bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400
                                    @else bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 @endif">
                                    {{ $order->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-medium text-gray-500 text-right">{{ $order->created_at->diffForHumans() }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm font-medium text-gray-500 dark:text-gray-400">No hay órdenes recientes</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Vista Móvil para Órdenes (Tarjetas) -->
            <div class="block md:hidden divide-y divide-gray-100 dark:divide-gray-700/50">
                @forelse($latestOrders as $order)
                    <div class="p-4 flex flex-col gap-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-black text-gray-900 dark:text-white">#{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</span>
                            <span class="px-2 py-0.5 inline-flex text-[10px] font-black uppercase tracking-widest rounded-md 
                                @if($order->status === 'pagado' || $order->status === 'completado') bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400
                                @elseif($order->status === 'pendiente') bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400
                                @elseif($order->status === 'cancelado') bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400
                                @else bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 @endif">
                                {{ $order->status }}
                            </span>
                        </div>
                        <div>
                            <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $order->user?->name ?? 'Invitado' }}</div>
                            <div class="text-xs font-medium text-gray-500">{{ $order->user?->email }}</div>
                        </div>
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-sm font-black text-[var(--color-primary)]">${{ number_format($order->total, 0, ',', '.') }}</span>
                            <span class="text-xs font-medium text-gray-500">{{ $order->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-sm font-medium text-gray-500 dark:text-gray-400">
                        No hay órdenes recientes
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Top Productos -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm lg:col-span-1 flex flex-col overflow-hidden">
            <div class="p-6 border-b border-gray-100 dark:border-gray-700/50">
                <h3 class="text-base font-black tracking-tight text-gray-900 dark:text-white">Lo más vendido</h3>
            </div>
            <div class="p-6 flex-grow">
                @if($topSelling->count() > 0)
                    <ul class="space-y-5">
                        @foreach($topSelling as $index => $item)
                            @if($item->product)
                            <li class="flex items-center gap-4 group">
                                <div class="w-10 h-10 bg-gray-50 dark:bg-gray-900 rounded-xl flex items-center justify-center border border-gray-100 dark:border-gray-700 overflow-hidden shrink-0 transition-transform group-hover:scale-105">
                                    @if($item->product->image_url)
                                        <img src="{{ asset('storage/' . $item->product->image_url) }}" class="w-full h-full object-contain p-1">
                                    @endif
                                </div>
                                <div class="flex-grow min-w-0">
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white truncate group-hover:text-[var(--color-primary)] transition-colors">{{ $item->product->name }}</h4>
                                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500">${{ number_format($item->product->retail_price, 0, ',', '.') }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="inline-flex items-center justify-center px-2 py-1 rounded bg-[var(--color-primary)]/10 text-[var(--color-primary)] text-xs font-black">
                                        {{ $item->total_sold }} un.
                                    </span>
                                </div>
                            </li>
                            @endif
                        @endforeach
                    </ul>
                @else
                    <div class="h-full flex flex-col items-center justify-center text-center py-8">
                        <p class="text-sm font-semibold text-gray-400 dark:text-gray-500">Sin datos de ventas</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Stock Crítico -->
        <div class="bg-white dark:bg-gray-800 border border-rose-200 dark:border-rose-900/40 rounded-2xl shadow-sm p-6 relative overflow-hidden flex flex-col">
            <div class="absolute -top-12 -right-12 w-48 h-48 bg-rose-500/10 dark:bg-rose-500/5 rounded-full blur-2xl pointer-events-none"></div>
            <div class="relative z-10 flex justify-between items-center mb-6">
                <h3 class="text-base font-black tracking-tight text-rose-600 dark:text-rose-400 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    Stock Crítico ({{ $outOfStockCount }})
                </h3>
                <a href="{{ route('admin.products') }}?sort=stock&dir=asc" class="text-xs font-bold uppercase tracking-wider text-rose-500 hover:underline">Ver todos</a>
            </div>
            
            <div class="relative z-10 flex-grow">
                @if($outOfStock->count() > 0)
                    <ul class="space-y-3">
                        @foreach($outOfStock as $product)
                            <li>
                                <a href="{{ route('admin.products', ['edit' => $product->id]) }}" class="flex items-center justify-between p-3 bg-rose-50/50 dark:bg-rose-900/10 rounded-xl border border-rose-100 dark:border-rose-900/20 hover:bg-rose-100/50 dark:hover:bg-rose-900/30 transition-colors cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-white dark:bg-gray-800 rounded-lg flex items-center justify-center shadow-sm p-1">
                                            @if($product->image_url)
                                                <img src="{{ asset('storage/' . $product->image_url) }}" class="max-w-full max-h-full object-contain">
                                            @endif
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-gray-900 dark:text-white line-clamp-1">{{ $product->name }}</p>
                                            <p class="text-[10px] text-rose-500 font-bold tracking-widest uppercase mt-0.5">SKU: {{ $product->sku ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-1 bg-rose-500 text-white text-[10px] font-black uppercase tracking-widest rounded shadow-sm">
                                            + Stock
                                        </span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="h-full flex flex-col items-center justify-center bg-emerald-50/50 dark:bg-emerald-900/10 border border-emerald-100 dark:border-emerald-900/20 rounded-xl p-8 text-center">
                        <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-full flex items-center justify-center mb-3">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <p class="text-sm font-bold text-emerald-700 dark:text-emerald-400">Inventario Saludable</p>
                        <p class="text-xs text-emerald-600/70 dark:text-emerald-400/70 font-semibold mt-1">Todos los productos cuentan con stock disponible.</p>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Valor del Inventario -->
        <div class="bg-gradient-to-br from-[#0f172a] to-[#1e293b] dark:from-gray-800 dark:to-gray-900 rounded-2xl shadow-xl p-8 text-white relative overflow-hidden flex flex-col justify-between border border-gray-800">
            <!-- Decorative Elements -->
            <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-[var(--color-primary)] to-purple-600 opacity-20 rounded-full blur-3xl pointer-events-none -mr-20 -mt-20"></div>
            <div class="absolute bottom-0 left-0 w-40 h-40 bg-[var(--color-primary)] opacity-10 rounded-full blur-2xl pointer-events-none -ml-10 -mb-10"></div>
            <div class="absolute inset-0 opacity-10 pointer-events-none" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"></div>
            
            <div class="relative z-10">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white/10 border border-white/10 mb-4 backdrop-blur-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-white/90">Capital Inmovilizado</span>
                </div>
                <h3 class="text-4xl sm:text-5xl font-black tracking-tighter">${{ number_format($stockValue, 0, ',', '.') }}</h3>
                <p class="text-sm font-medium text-gray-400 mt-2 max-w-xs">Valorización del inventario real (excluye stock de productos bajo pedido).</p>
            </div>
            
            <div class="relative z-10 mt-10 pt-6 border-t border-gray-700/50 flex justify-between items-end">
                <div>
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Módulo Financiero</p>
                    <p class="text-sm font-bold text-gray-300 mt-0.5">Resumen de Activos</p>
                </div>
                <div class="w-12 h-12 bg-white/5 border border-white/10 rounded-xl flex items-center justify-center backdrop-blur-md shadow-inner text-gray-400">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
        </div>

    </div>

    <script>
        /**
         * Dashboard Charts — inicialización robusta para Livewire SPA.
         */

        const buildCharts = () => {
            const isDark      = document.documentElement.classList.contains('dark');
            const textColor   = isDark ? '#9ca3af' : '#4b5563';
            const gridColor   = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)';
            const primaryColor = getComputedStyle(document.documentElement)
                                    .getPropertyValue('--color-primary').trim() || '#DC2626';

            Chart.defaults.color       = textColor;
            Chart.defaults.font.family = "'Inter', sans-serif";

            // ── Gráfico 1: Ingresos Diarios (línea) ──────────────────────────
            const ctxRevenue = document.getElementById('dailyRevenueChart');
            if (ctxRevenue) {
                const existing = Chart.getChart(ctxRevenue);
                if (existing) existing.destroy();

                new Chart(ctxRevenue, {
                    type: 'line',
                    data: {
                        labels: @json($dailyRevenueLabels),
                        datasets: [{
                            label: 'Ingresos',
                            data: @json($dailyRevenue),
                            borderColor: primaryColor,
                            backgroundColor: isDark
                                ? 'rgba(255,255,255,0.04)'
                                : 'rgba(220,38,38,0.07)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2.5,
                            pointBackgroundColor: primaryColor,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: isDark ? '#1f2937' : '#fff',
                                titleColor:      isDark ? '#fff'    : '#111827',
                                bodyColor:       isDark ? '#d1d5db' : '#4b5563',
                                borderColor:     isDark ? '#374151' : '#e5e7eb',
                                borderWidth: 1,
                                padding: 12,
                                displayColors: false,
                                callbacks: {
                                    label: ctx => '$' + ctx.parsed.y.toLocaleString('es-AR'),
                                },
                            },
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                grid: { color: gridColor },
                                ticks: {
                                    callback: v => {
                                        if (v >= 1_000_000) return '$' + (v / 1_000_000).toFixed(1) + 'M';
                                        if (v >= 1_000)     return '$' + (v / 1_000).toFixed(0) + 'k';
                                        return '$' + v;
                                    },
                                },
                            },
                        },
                    },
                });
            }

            // ── Gráfico 2: Órdenes por Estado (doughnut) ─────────────────────
            const ctxStatus = document.getElementById('ordersStatusChart');
            if (ctxStatus) {
                const existing = Chart.getChart(ctxStatus);
                if (existing) existing.destroy();

                const statusData = @json($ordersByStatus);
                const labels = Object.keys(statusData).map(k => k.charAt(0).toUpperCase() + k.slice(1));
                const data   = Object.values(statusData);
                const colors = labels.map(l => {
                    const v = l.toLowerCase();
                    if (v === 'pagado' || v === 'completado') return '#10b981';
                    if (v === 'pendiente')                    return '#f59e0b';
                    if (v === 'cancelado')                    return '#f43f5e';
                    return '#6b7280';
                });

                new Chart(ctxStatus, {
                    type: 'doughnut',
                    data: {
                        labels,
                        datasets: [{ data, backgroundColor: colors, borderWidth: 0, hoverOffset: 4 }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { padding: 20, usePointStyle: true, pointStyle: 'circle' },
                            },
                        },
                    },
                });
            }
        };

        const initCharts = () => {
            if (window.Chart) {
                buildCharts();
                return;
            }

            const existing = document.getElementById('chartjs-cdn');
            if (existing) {
                let waited = 0;
                const poll = setInterval(() => {
                    waited += 100;
                    if (window.Chart) { clearInterval(poll); buildCharts(); }
                    else if (waited >= 5000) clearInterval(poll);
                }, 100);
                return;
            }

            const script  = document.createElement('script');
            script.id     = 'chartjs-cdn';
            script.src    = 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
            script.onload = () => buildCharts();
            document.head.appendChild(script);
        };

        // Ejecutar en navegación SPA
        document.addEventListener('livewire:navigated', () => {
            // Solo intentar cargar si estamos en la vista que tiene los gráficos
            if (document.getElementById('dailyRevenueChart')) {
                initCharts();
            }
        });
        
        // Ejecutar en primera carga si la página se cargó directamente
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                if (document.getElementById('dailyRevenueChart')) initCharts();
            });
        } else {
            if (document.getElementById('dailyRevenueChart')) initCharts();
        }
    </script>
</div>
