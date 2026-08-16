<?php

use Livewire\Volt\Component;
use App\Models\Product;
use App\Models\Category;

new class extends Component {
    use Livewire\WithPagination;

    public $selectedCategory = null;

    public function mount()
    {
    }

    public function setCategory($categoryId)
    {
        $this->selectedCategory = $categoryId;
        $this->resetPage();
    }

    public function with()
    {
        $query = Product::with(['category', 'brands']);

        // Priorizar SIEMPRE los productos con stock
        $query->orderByRaw('stock > 0 DESC');

        if ($this->selectedCategory) {
            $query->where('category_id', $this->selectedCategory);
        }

        return [
            'products' => $query->paginate(12),
            'categories' => Category::has('products')->withCount('products')->get(),
            'totalProducts' => \App\Models\Product::count()
        ];
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Encabezado del catálogo --}}
    <div class="text-center mb-10">
        <h2 class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white tracking-tight">
            Nuestro Catálogo
        </h2>
        <p class="mt-2 text-slate-500 dark:text-slate-400 text-sm">Encontrá el componente perfecto para tu build</p>
    </div>

    {{-- Category Filters --}}
    <div class="mb-10 flex flex-wrap justify-center gap-2.5">
        <button wire:click="setCategory(null)"
                class="filter-pill px-5 py-2 rounded-xl text-sm font-semibold border transition-all
                {{ $selectedCategory === null
                    ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 border-slate-900 dark:border-white shadow-lg'
                    : 'bg-white dark:bg-slate-800/60 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700/60 hover:border-slate-400 dark:hover:border-slate-500' }}">
            Todos
        </button>
        @foreach($categories as $category)
            <button wire:click="setCategory({{ $category->id }})"
                    class="filter-pill px-5 py-2 rounded-xl text-sm font-semibold border transition-all
                    {{ $selectedCategory == $category->id
                        ? 'text-white border-transparent shadow-lg'
                        : 'bg-white dark:bg-slate-800/60 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700/60 hover:border-slate-400 dark:hover:border-slate-500' }}"
                    @if($selectedCategory == $category->id) style="background-color: var(--color-primary);" @endif>
                {{ $category->name }}
                <span class="ml-1.5 text-[10px] opacity-60 font-normal">({{ $category->products_count }})</span>
            </button>
        @endforeach
    </div>

    {{-- Products Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @forelse ($products as $index => $product)
            <article wire:key="product-{{ $product->id }}"
                     class="card-hover group relative flex flex-col rounded-2xl overflow-hidden border
                            bg-white dark:bg-slate-800/50
                            border-slate-200/80 dark:border-slate-700/50
                            shadow-sm dark:shadow-none">

                {{-- Imagen --}}
                <a href="{{ route('product.detail', $product->slug) }}" wire:navigate
                   class="relative block aspect-[4/3] bg-slate-100 dark:bg-slate-900/70 overflow-hidden">
                    @if($product->image_url)
                        <img src="{{ asset('storage/' . $product->image_url) }}"
                             alt="{{ $product->name }}"
                             class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                    @else
                        <div class="w-full h-full flex items-center justify-center">
                            <svg class="h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    @endif

                    {{-- Overlay on hover --}}
                    <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent
                                opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    </div>
                </a>

                {{-- Contenido de la Tarjeta --}}
                <div class="p-4 bg-white flex flex-col flex-grow relative z-20">
                    <div class="flex justify-between items-start mb-2 gap-2">
                        @if($product->category)
                            <span class="text-[10px] font-bold uppercase tracking-widest text-[var(--color-primary)] opacity-80 block truncate">
                                {{ $product->category->name }}
                            </span>
                        @else
                            <span></span>
                        @endif

                        {{-- Stock Badge --}}
                        @if($product->stock <= 0)
                            <span class="shrink-0 text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-md bg-rose-100 text-rose-700 shadow-sm whitespace-nowrap">
                                Agotado
                            </span>
                        @elseif($product->stock <= ($product->min_stock ?? 5))
                            <span class="shrink-0 text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-md bg-orange-100 text-orange-700 shadow-sm whitespace-nowrap" title="Stock Disponible">
                                ¡Quedan {{ $product->stock }}!
                            </span>
                        @else
                            <span class="shrink-0 text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-600 shadow-sm whitespace-nowrap" title="Stock Disponible">
                                Stock: {{ $product->stock }}
                            </span>
                        @endif
                    </div>
                    
                    <div class="flex-grow">
                        <a href="{{ route('product.detail', $product->slug) }}" wire:navigate>
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100
                                       leading-snug group-hover:text-[var(--color-primary)]
                                       dark:group-hover:text-white transition-colors line-clamp-2">
                                {{ $product->name }}
                            </h3>
                        </a>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed">
                            {{ $product->description }}
                        </p>
                    </div>

                    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/50 space-y-3">
                        <div class="flex flex-col gap-2">
                            <div class="flex items-end justify-between">
                                <div>
                                    <p class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Precio Unitario</p>
                                    <p class="text-xl font-black text-slate-900 dark:text-white leading-none">${{ number_format($product->retail_price, 2) }}</p>
                                </div>
                                <div class="text-right flex flex-col items-end">
                                    <span class="inline-flex items-center gap-1.5 text-[9px] font-bold uppercase tracking-widest text-emerald-600 dark:text-emerald-400 mb-1">
                                        <span class="relative flex h-2 w-2">
                                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                          <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                        </span>
                                        Precio Mayorista
                                    </span>
                                    <div class="relative group cursor-help mt-0.5" title="Descuento automático al llevar {{ \App\Services\PricingService::GLOBAL_WHOLESALE_MIN }} o más artículos en total">
                                        <div class="absolute -inset-0.5 bg-gradient-to-r from-emerald-500 to-teal-400 rounded-lg blur opacity-30 group-hover:opacity-70 transition duration-500"></div>
                                        <span class="relative flex items-center gap-1 text-xs font-black text-emerald-900 dark:text-emerald-100 bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:from-slate-800 dark:to-slate-900 border border-emerald-200/50 dark:border-emerald-700/50 px-2.5 py-1 rounded-lg shadow-sm">
                                            ${{ number_format($product->wholesale_price, 2) }} 
                                            <span class="opacity-75 font-bold text-[9px] bg-emerald-200/50 dark:bg-emerald-900/50 px-1 py-0.5 rounded">C/U</span>
                                        </span>
                                    </div>
                                    <span class="text-[8px] font-semibold text-slate-400 dark:text-slate-500 mt-1.5 uppercase tracking-wider">
                                        Llevando {{ \App\Services\PricingService::GLOBAL_WHOLESALE_MIN }} o más artículos en total
                                    </span>
                                </div>
                            </div>
                        </div>

                        <livewire:add-to-cart :product="$product" :compact="true" wire:key="add-cart-{{ $product->id }}" />
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full py-24 text-center">
                <svg class="mx-auto h-16 w-16 text-slate-300 dark:text-slate-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Sin productos</h3>
                <p class="mt-1 text-slate-500 dark:text-slate-400 text-sm">No hay productos en esta categoría.</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-12">
        {{ $products->links() }}
    </div>

</div>
