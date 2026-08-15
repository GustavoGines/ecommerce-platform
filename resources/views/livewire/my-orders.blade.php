<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $filtro = 'todas';
    public $search = '';

    public function updatedSearch() { $this->resetPage(); }
    public function updatedFiltro() { $this->resetPage(); }

    public function mount()
    {
        // ...
    }

    public function with(): array
    {
        $query = Order::where('user_id', auth()->id())
            ->with('items.product');

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('id', 'like', '%' . $this->search . '%')
                  ->orWhereHas('items.product', function ($q2) {
                      $q2->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->filtro !== 'todas') {
            $query->where('status', $this->filtro);
        }

        return [
            'orders' => $query->orderBy('created_at', 'desc')->paginate(10)
        ];
    }

    public function cancelarOrden($id)
    {
        $order = Order::where('user_id', auth()->id())
            ->with('items.product')
            ->find($id);

        if ($order && $order->status === 'pendiente') {
            DB::transaction(function () use ($order) {
                // BUG-05 FIX: Restore stock before cancelling.
                foreach ($order->items as $item) {
                    if ($item->product) {
                        $item->product->increment('stock', $item->quantity);
                    }
                }
                $order->update(['status' => 'cancelado']);
            });
        }
    }

    public function eliminarOrden($id)
    {
        $order = Order::where('user_id', auth()->id())
            ->with('items.product')
            ->find($id);

        if ($order && in_array($order->status, ['pendiente', 'cancelado'])) {
            DB::transaction(function () use ($order) {
                // BUG-05 FIX: Restaurar stock antes de eliminar.
                // Solo restaura si la orden estaba pendiente (canceladas ya restauraron el stock al cancelar).
                if ($order->status === 'pendiente') {
                    foreach ($order->items as $item) {
                        if ($item->product) {
                            $item->product->increment('stock', $item->quantity);
                        }
                    }
                }
                $order->items()->delete();
                $order->delete();
            });
        }
    }
}; ?>

<div x-data="{ previewImageOpen: false, previewImageUrl: '' }">
    <x-slot name="header">
        @if(auth()->check() && auth()->user()->isAdmin())
            <div class="flex items-center space-x-6">
                <a href="{{ route('admin.orders') }}" wire:navigate class="font-semibold text-xl text-gray-500 hover:text-gray-900 dark:hover:text-gray-100 transition-colors leading-tight">
                    {{ __('Todas las Órdenes') }}
                </a>
                <span class="text-gray-300 dark:text-gray-700">|</span>
                <a href="{{ route('my-orders') }}" wire:navigate class="font-semibold text-xl text-[var(--color-primary)] leading-tight">
                    {{ __('Mis Compras') }}
                </a>
            </div>
        @else
            <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100 leading-tight">
                Mis Compras
            </h2>
        @endif
    </x-slot>

    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">

        {{-- Encabezado con contador --}}
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-black text-gray-900 dark:text-white">Historial de Compras</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ $orders->total() }} {{ $orders->total() === 1 ? 'orden encontrada' : 'órdenes encontradas' }}
                </p>
            </div>
            <a href="{{ route('home') }}" wire:navigate
               class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium text-white transition-all hover:opacity-90"
               style="background-color: var(--color-primary);">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                Seguir comprando
            </a>
        </div>

        {{-- Filtros y Búsqueda --}}
        <div class="flex flex-col sm:flex-row gap-3 mb-6">
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar por orden o producto..." class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-800/40 text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all outline-none shadow-sm">
            </div>
            <div class="w-full sm:w-48">
                <select wire:model.live="filtro" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-800/40 text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all outline-none cursor-pointer shadow-sm">
                    <option value="todas">Todas las Órdenes</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="pagado">Pagado</option>
                    <option value="completado">Completado</option>
                    <option value="cancelado">Cancelado</option>
                </select>
            </div>
        </div>

        {{-- Lista de órdenes --}}
        <div class="space-y-3">
            @forelse($orders as $order)
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 shadow-sm relative transition-all"
                     x-data="{ expandido: false }" :class="expandido ? 'ring-1 ring-[var(--color-primary)]' : ''">

                    {{-- Cabecera clickeable --}}
                    <div @click="expandido = !expandido" class="flex justify-between items-center cursor-pointer">
                        <div class="flex flex-col gap-0.5 max-w-[65%]">
                            <div class="flex items-center gap-1.5">
                                <span class="text-sm font-black text-gray-900 dark:text-white leading-none">#{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</span>
                                <span class="w-1.5 h-1.5 rounded-full 
                                    @if($order->status === 'pendiente') bg-yellow-400
                                    @elseif($order->status === 'pagado') bg-blue-400
                                    @elseif($order->status === 'completado') bg-green-400
                                    @elseif($order->status === 'cancelado') bg-red-400
                                    @endif"></span>
                                <span class="text-[10px] font-bold uppercase tracking-wide
                                    @if($order->status === 'pendiente') text-yellow-600 dark:text-yellow-400
                                    @elseif($order->status === 'pagado') text-blue-600 dark:text-blue-400
                                    @elseif($order->status === 'completado') text-green-600 dark:text-green-400
                                    @else text-red-600 dark:text-red-400 @endif">{{ $order->status }}</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium truncate">{{ $order->created_at->format('d/m/Y') }} · {{ $order->items->count() }} prod.</span>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-black text-gray-900 dark:text-white">${{ number_format($order->total, 2) }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="expandido ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </div>
                    </div>

                    {{-- Detalle expandible --}}
                    <div x-show="expandido" x-transition x-cloak class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700/50">
                        {{-- Entrega --}}
                        <div class="flex items-center gap-1.5 mb-3 px-1">
                            <span class="text-gray-400 text-xs">🚚</span>
                            <span class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400">{{ $order->delivery_label }}</span>
                        </div>

                        {{-- Productos --}}
                        <div class="mb-4">
                            <div class="text-[9px] font-bold text-gray-400 uppercase tracking-wider mb-1 px-1">Productos</div>
                            <ul class="space-y-1.5">
                                @foreach($order->items as $item)
                                    <li class="flex items-center justify-between gap-2 bg-gray-50 dark:bg-gray-800/50 p-1.5 rounded-lg border border-gray-100 dark:border-gray-700/50">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex-shrink-0 overflow-hidden">
                                                @if($item->product && $item->product->image_url)
                                                    <img src="{{ asset('storage/' . $item->product->image_url) }}" class="w-full h-full object-cover cursor-pointer hover:opacity-80 transition-opacity" @click.stop="previewImageUrl = '{{ asset('storage/' . $item->product->image_url) }}'; previewImageOpen = true">
                                                @endif
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-[11px] font-medium text-gray-800 dark:text-gray-200 line-clamp-1">{{ $item->product ? $item->product->name : 'Producto eliminado' }}</span>
                                                <span class="text-[10px] text-gray-500">{{ $item->quantity }}x ${{ number_format($item->price, 2) }}</span>
                                            </div>
                                        </div>
                                        <span class="font-bold text-gray-900 dark:text-white text-[11px] pr-1">${{ number_format($item->price * $item->quantity, 2) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        {{-- Acciones --}}
                        <div class="flex flex-col sm:flex-row gap-2 pt-3 border-t border-gray-100 dark:border-gray-700/50">
                            @if(in_array($order->status, ['pagado', 'completado']))
                                <a href="{{ route('checkout.success', $order) }}"
                                   target="_blank"
                                   class="w-full sm:w-auto inline-flex justify-center items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold text-white transition-all hover:opacity-90"
                                   style="background-color: var(--color-primary);">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                    </svg>
                                    Ver comprobante
                                </a>
                            @elseif($order->status === 'pendiente' && $order->mp_preference_id)
                                @php
                                    $mpUrl = app()->isProduction() 
                                        ? "https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=" . $order->mp_preference_id
                                        : "https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=" . $order->mp_preference_id;
                                @endphp
                                <a href="{{ $mpUrl }}"
                                   style="background-color: #facc15; color: #111827;"
                                   class="w-full sm:w-auto inline-flex justify-center items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold transition-all hover:opacity-80">
                                    Pagar ahora
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </a>
                                <button wire:click="cancelarOrden({{ $order->id }})"
                                        wire:confirm="¿Seguro que querés cancelar esta orden?"
                                        class="w-full sm:w-auto inline-flex justify-center items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-gray-500 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    Cancelar
                                </button>
                            @endif

                            @if(in_array($order->status, ['pendiente', 'cancelado']))
                                <button wire:click="eliminarOrden({{ $order->id }})"
                                        wire:confirm="¿Estás seguro de eliminar permanentemente esta orden del historial?"
                                        class="w-full sm:w-auto inline-flex justify-center items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold text-red-500 bg-red-50 dark:bg-red-900/10 hover:bg-red-100 dark:hover:bg-red-900/20 border border-red-100 dark:border-red-900/30 transition-colors sm:ml-auto">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Eliminar
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-24 bg-white/50 dark:bg-gray-800/20 backdrop-blur-sm border border-gray-200 dark:border-gray-700/50 rounded-3xl">
                    <svg class="mx-auto h-16 w-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">
                        {{ $filtro === 'todas' ? 'Aún no tenés órdenes' : 'No hay órdenes con este estado' }}
                    </h3>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mb-6">
                        {{ $filtro === 'todas' ? 'Cuando realices compras, aparecerán aquí.' : 'Probá cambiando el filtro.' }}
                    </p>
                    @if($filtro !== 'todas')
                        <button wire:click="setFiltro('todas')"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-gray-500 transition-all">
                            Ver todas las órdenes
                        </button>
                    @else
                        <a href="{{ route('home') }}" wire:navigate
                           class="inline-flex items-center gap-2 px-6 py-3 rounded-full text-sm font-bold text-white transition-all hover:opacity-90"
                           style="background-color: var(--color-primary);">
                            Ir a la tienda
                        </a>
                    @endif
                </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $orders->links() }}
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div x-show="previewImageOpen" class="fixed z-50 inset-0 overflow-y-auto" style="z-index: 60;" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-cloak>
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
            <div x-show="previewImageOpen" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity" 
                 @click="previewImageOpen = false" aria-hidden="true"></div>

            <div x-show="previewImageOpen" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 class="inline-block align-bottom rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle max-w-4xl w-full">
                <div class="relative">
                    <button @click="previewImageOpen = false" class="absolute top-4 right-4 bg-black/50 hover:bg-black/80 text-white rounded-full p-2 transition-colors z-10 backdrop-blur-md">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                    <img :src="previewImageUrl" class="w-full h-auto max-h-[85vh] object-contain bg-transparent mx-auto">
                </div>
            </div>
        </div>
    </div>
</div>
