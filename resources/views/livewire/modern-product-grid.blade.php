<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

new class extends Component {
    use Livewire\WithPagination;

    #[Url(as: 'categoria')]
    public $selectedCategory = null;

    #[Url(as: 'marca')]
    public $selectedBrand = null;
    
    #[Url(as: 'q')]
    public $search = '';
    
    #[Url(as: 'min')]
    public $minPrice = null;
    
    #[Url(as: 'max')]
    public $maxPrice = null;

    #[Url(as: 'sort')]
    public $sort = 'default';

    public function mount()
    {
        if ($this->selectedCategory && !is_numeric($this->selectedCategory)) {
            $cat = Category::where('name', 'like', '%' . $this->selectedCategory . '%')->first();
            if ($cat) {
                $this->selectedCategory = $cat->id;
            } else {
                $this->selectedCategory = null;
            }
        }
    }

    public function setCategory($categoryId)
    {
        $this->selectedCategory = $categoryId;
        $this->resetPage();
    }
    
    public function setBrand($brandId)
    {
        $this->selectedBrand = $brandId;
        $this->resetPage();
    }
    
    public function setTag($tag)
    {
        $this->search = $tag;
        $this->resetPage();
    }

    public function with()
    {
        $query = Product::with(['category', 'brands']);

        // Prioridad de Stock: Los productos sin stock van al final
        $query->orderByRaw('stock > 0 DESC');

        if ($this->selectedCategory) {
            $query->where('category_id', $this->selectedCategory);
        }
        
        if ($this->selectedBrand) {
            $query->whereHas('brands', fn($q) => $q->where('brands.id', $this->selectedBrand));
        }
        
        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('sku', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }
        
        if ($this->minPrice) {
            $query->where('retail_price', '>=', $this->minPrice);
        }
        
        if ($this->maxPrice) {
            $query->where('retail_price', '<=', $this->maxPrice);
        }

        if ($this->sort === 'price_asc') {
            $query->orderBy('retail_price', 'asc');
        } elseif ($this->sort === 'price_desc') {
            $query->orderBy('retail_price', 'desc');
        } elseif ($this->sort === 'recent') {
            $query->latest();
        }
        
        $recentlyViewedIds = session()->get('recently_viewed', []);
        $recentlyViewedProducts = collect();
        if (count($recentlyViewedIds) > 0) {
            $recentlyViewedProducts = Product::whereIn('id', $recentlyViewedIds)
                                              ->get()
                                              ->sortBy(function($model) use ($recentlyViewedIds) {
                                                  return array_search($model->id, $recentlyViewedIds);
                                              });
        }

        return [
            'products' => $query->paginate(15),
            'popularProducts' => \Illuminate\Support\Facades\Cache::remember('popularProducts', 3600, fn() => Product::latest()->take(3)->get()),
            'recentlyViewedProducts' => $recentlyViewedProducts,
            'categories' => Category::has('products')->withCount('products')->get(),
            'brands' => Brand::has('products')->withCount('products')->get()
        ];
    }
}; ?>

<div id="catalog" class="w-full relative z-10 py-12 lg:py-16 bg-g3-dark" x-data="{ intersecting: false, sidebarOpen: false }" x-intersect.once="intersecting = true">
    
    {{-- Alerta Minimalista de Precios Mayoristas --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-8 transition-all duration-1000 transform" :class="intersecting ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'">
        <div class="bg-zinc-950 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-[0_8px_30px_rgb(0,0,0,0.3)] border border-zinc-800 relative overflow-hidden">
            <div class="absolute right-0 top-0 w-64 h-full bg-gradient-to-l from-g3-blue/10 to-transparent pointer-events-none"></div>
            <div class="flex items-center gap-4 relative z-10">
                <div class="flex-shrink-0 w-12 h-12 rounded-full bg-g3-blue/10 flex items-center justify-center text-g3-blue border border-g3-blue/20">
                    <svg class="w-6 h-6 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-white font-black text-sm tracking-wide">10% OFF EN EFECTIVO O TRANSFERENCIA</h3>
                    <p class="text-g3-silver text-xs mt-1 font-medium">Llevate un <strong class="text-white">10% de descuento</strong> sobre el total abonando en efectivo o por transferencia al finalizar tu compra.</p>
                </div>
            </div>
            <div class="hidden sm:block relative z-10">
                <span class="px-3 py-1 rounded-full bg-g3-blue text-white text-[10px] font-bold uppercase tracking-wider shadow-[0_0_10px_rgba(59,130,246,0.5)]">Activo</span>
            </div>
        </div>
    </div>



    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 transition-all duration-1000 delay-300 transform" :class="intersecting ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0'">
        
        {{-- Mobile Filter Trigger --}}
        <div class="lg:hidden flex justify-between items-center mb-6">
            <span class="text-white font-bold text-lg">Catálogo ({{ $products->total() }})</span>
            <button @click="sidebarOpen = true" class="flex items-center gap-2 px-4 py-2 bg-zinc-900 border border-zinc-800 shadow-sm rounded-xl text-gray-300 text-sm hover:bg-zinc-800 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                Filtros
            </button>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            
            {{-- SIDEBAR --}}
            <aside class="w-full lg:w-1/4 shrink-0 fixed lg:relative inset-y-0 left-0 z-50 lg:z-0 bg-g3-dark lg:bg-transparent border-r lg:border-none border-zinc-800 transform lg:transform-none transition-transform duration-300 overflow-y-auto lg:overflow-visible p-6 lg:p-0 shadow-2xl lg:shadow-none -translate-x-full lg:translate-x-0"
                   :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                   x-cloak>
                
                {{-- Close Button Mobile --}}
                <div class="flex justify-between items-center lg:hidden mb-8">
                    <h3 class="text-white font-bold text-xl tracking-wide">Filtros</h3>
                    <button @click="sidebarOpen = false" class="p-2 text-gray-400 hover:text-white rounded-full bg-zinc-800">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="space-y-10 lg:bg-g3-card lg:p-6 lg:rounded-2xl lg:border lg:border-zinc-800 lg:shadow-sm">
                    {{-- Search Input --}}
                    <div>
                        <div class="relative">
                            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar en la tienda..." class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-sm text-white focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] placeholder-gray-500 transition-all outline-none">
                            
                            {{-- Loading Spinner --}}
                            <div wire:loading.flex wire:target="search" class="absolute right-4 top-1/2 -translate-y-1/2 flex items-center pointer-events-none">
                                <svg class="animate-spin h-4 w-4 text-[var(--color-primary)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>

                            {{-- Clear Button or Search Icon --}}
                            <div wire:loading.remove wire:target="search" class="absolute right-4 top-1/2 -translate-y-1/2 flex items-center">
                                @if($search)
                                    <button wire:click="$set('search', '')" class="text-gray-500 hover:text-white transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                @else
                                    <svg class="w-4 h-4 text-gray-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Categories --}}
                    <div>
                        <h4 class="text-white font-bold text-sm tracking-widest uppercase mb-4 pb-4 border-b border-zinc-800">Categorías</h4>
                        <div x-data="{ expanded: false }">
                            <ul class="space-y-3">
                                <li>
                                    <button wire:click="setCategory(null)" class="w-full flex items-center justify-between text-sm transition-colors {{ $selectedCategory === null ? 'text-[var(--color-primary)] font-bold' : 'text-g3-silver hover:text-white' }}">
                                        <span>Todas</span>
                                        <span class="text-[10px] bg-zinc-800 text-gray-300 px-2 py-0.5 rounded-full font-bold">{{ \App\Models\Product::count() }}</span>
                                    </button>
                                </li>
                                @foreach($categories->take(5) as $category)
                                    <li>
                                        <button wire:click="setCategory({{ $category->id }})" class="w-full flex items-center justify-between text-sm transition-colors {{ $selectedCategory == $category->id ? 'text-[var(--color-primary)] font-bold' : 'text-g3-silver hover:text-white' }}">
                                            <span>{{ $category->name }}</span>
                                            <span class="text-[10px] bg-zinc-800 text-gray-300 px-2 py-0.5 rounded-full font-bold">{{ $category->products_count }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>

                            @if($categories->count() > 5)
                                <div x-show="expanded" x-collapse>
                                    <ul class="space-y-3 mt-3">
                                        @foreach($categories->skip(5) as $category)
                                            <li>
                                                <button wire:click="setCategory({{ $category->id }})" class="w-full flex items-center justify-between text-sm transition-colors {{ $selectedCategory == $category->id ? 'text-[var(--color-primary)] font-bold' : 'text-g3-silver hover:text-white' }}">
                                                    <span>{{ $category->name }}</span>
                                                    <span class="text-[10px] bg-zinc-800 text-gray-300 px-2 py-0.5 rounded-full font-bold">{{ $category->products_count }}</span>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                                <button @click="expanded = !expanded" class="w-full mt-4 flex items-center justify-center gap-1 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-[10px] font-black text-g3-silver uppercase tracking-widest hover:border-zinc-700 hover:text-white transition-all">
                                    <span x-text="expanded ? '- VER MENOS' : '+ VER TODAS ({{ $categories->count() }})'"></span>
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Brands --}}
                    @if(count($brands) > 0)
                    <div>
                        <h4 class="text-white font-bold text-sm tracking-widest uppercase mb-4 pb-4 border-b border-zinc-800">Marcas</h4>
                        <div x-data="{ expanded: false }">
                            <ul class="space-y-3">
                                <li>
                                    <button wire:click="setBrand(null)" class="w-full flex items-center justify-between text-sm transition-colors {{ $selectedBrand === null ? 'text-[var(--color-primary)] font-bold' : 'text-g3-silver hover:text-white' }}">
                                        <span>Todas</span>
                                    </button>
                                </li>
                                @foreach($brands->take(5) as $brand)
                                    <li>
                                        <button wire:click="setBrand({{ $brand->id }})" class="w-full flex items-center justify-between text-sm transition-colors {{ $selectedBrand == $brand->id ? 'text-[var(--color-primary)] font-bold' : 'text-g3-silver hover:text-white' }}">
                                            <span>{{ $brand->name }}</span>
                                            <span class="text-[10px] bg-zinc-800 text-gray-300 px-2 py-0.5 rounded-full font-bold">{{ $brand->products_count }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>

                            @if($brands->count() > 5)
                                <div x-show="expanded" x-collapse>
                                    <ul class="space-y-3 mt-3">
                                        @foreach($brands->skip(5) as $brand)
                                            <li>
                                                <button wire:click="setBrand({{ $brand->id }})" class="w-full flex items-center justify-between text-sm transition-colors {{ $selectedBrand == $brand->id ? 'text-[var(--color-primary)] font-bold' : 'text-g3-silver hover:text-white' }}">
                                                    <span>{{ $brand->name }}</span>
                                                    <span class="text-[10px] bg-zinc-800 text-gray-300 px-2 py-0.5 rounded-full font-bold">{{ $brand->products_count }}</span>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                                <button @click="expanded = !expanded" class="w-full mt-4 flex items-center justify-center gap-1 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-[10px] font-black text-g3-silver uppercase tracking-widest hover:border-zinc-700 hover:text-white transition-all">
                                    <span x-text="expanded ? '- VER MENOS' : '+ VER TODAS ({{ $brands->count() }})'"></span>
                                </button>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- Functional Price Range --}}
                    <div>
                        <h4 class="text-white font-bold text-sm tracking-widest uppercase mb-4 pb-4 border-b border-zinc-800">Rango de Precio</h4>
                        <div class="px-1" x-data="{ minPrice: $wire.entangle('minPrice').live, maxPrice: $wire.entangle('maxPrice').live }">
                            <div class="flex items-center gap-2 mb-4">
                                <div class="w-1/2 relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-bold">$</span>
                                    <input type="number" x-model.debounce.500ms="minPrice" placeholder="Min" class="w-full bg-zinc-900 border border-zinc-800 rounded-xl pl-7 pr-2 py-2 text-sm text-white focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] outline-none placeholder-gray-500">
                                </div>
                                <span class="text-gray-500 font-bold">-</span>
                                <div class="w-1/2 relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-bold">$</span>
                                    <input type="number" x-model.debounce.500ms="maxPrice" placeholder="Max" class="w-full bg-zinc-900 border border-zinc-800 rounded-xl pl-7 pr-2 py-2 text-sm text-white focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] outline-none placeholder-gray-500">
                                </div>
                            </div>
                        </div>
                    </div>



                </div>
            </aside>
            
            {{-- Mobile Sidebar Backdrop --}}
            <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-40 lg:hidden" x-transition.opacity></div>

            {{-- PRODUCTS GRID --}}
            <div class="w-full lg:w-3/4">
                
                {{-- Desktop Top Bar (Sorting/Results) --}}
                <div class="hidden lg:flex justify-between items-center mb-6">
                    <span class="text-gray-400 text-sm font-medium">Mostrando <strong class="text-white">{{ $products->count() }}</strong> de {{ $products->total() }} productos</span>
                    <select wire:model.live="sort" class="bg-zinc-900 border border-zinc-800 text-gray-300 font-semibold text-sm rounded-xl focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] block p-2.5 shadow-sm outline-none">
                        <option value="default">Relevancia</option>
                        <option value="price_asc">Precio: Menor a Mayor</option>
                        <option value="price_desc">Precio: Mayor a Menor</option>
                        <option value="recent">Novedades</option>
                    </select>
                </div>

                {{-- The Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
                    @forelse ($products as $index => $product)
                        <article wire:key="product-{{ $product->id }}"
                                 class="group relative flex flex-col bg-g3-card border border-zinc-800 rounded-lg overflow-hidden shadow-sm hover:shadow-[0_0_15px_rgba(59,130,246,0.2)] hover:-translate-y-0.5 transition-all duration-300">
                            
                            {{-- Contenedor de la Imagen (Más compacto) --}}
                            <a href="{{ route('product.detail', $product->slug) }}" wire:navigate class="relative block aspect-square bg-zinc-900 overflow-hidden p-3 border-b border-zinc-800 flex items-center justify-center">
                                @if($product->image_url)
                                    <img src="{{ asset('storage/' . $product->image_url) }}"
                                         alt="{{ $product->name }}"
                                         class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-105 drop-shadow-md mix-blend-screen"
                                         onerror="this.src='https://images.unsplash.com/photo-1591488320449-011701bb6704?q=80&w=400&auto=format&fit=crop'">
                                @else
                                    <svg class="h-10 w-10 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                @endif
                            </a>

                            {{-- Contenido de la Tarjeta (Más compacto) --}}
                            <div class="flex flex-col flex-grow p-3 bg-g3-card">
                                <div class="flex-grow">
                                    <div class="flex justify-between items-start mb-1 gap-2">
                                        @if($product->category)
                                            <span class="text-[9px] font-bold uppercase tracking-widest text-g3-silver block truncate">
                                                {{ $product->category->name }}
                                            </span>
                                        @else
                                            <span></span>
                                        @endif

                                        {{-- Stock Badge --}}
                                        @if($product->stock <= 0)
                                            <span class="shrink-0 text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-red-900/50 text-red-400 border border-red-800 shadow-sm whitespace-nowrap">
                                                Agotado
                                            </span>
                                        @endif
                                    </div>
                                    @if($product->sku)
                                        <span class="text-[9px] font-mono font-bold text-[var(--color-primary)] bg-[var(--color-primary)]/10 px-1 py-0.5 rounded inline-block mb-1.5 border border-[var(--color-primary)]/20">
                                            SKU: {{ $product->sku }}
                                        </span>
                                    @endif
                                    <a href="{{ route('product.detail', $product->slug) }}" wire:navigate>
                                        <h3 class="text-xs sm:text-sm font-bold text-white leading-tight hover:text-[var(--color-primary)] transition-colors line-clamp-2" title="{{ $product->name }}">
                                            {{ $product->name }}
                                        </h3>
                                    </a>
                                </div>

                                <div class="mt-2 pt-2 border-t border-zinc-800 flex flex-col gap-2">
                                    <div class="flex items-end justify-between">
                                        <div>
                                            @if(auth()->check() && auth()->user()->isWholesaleCustomer())
                                                <div class="flex items-center gap-1.5 mb-0.5">
                                                    <span class="inline-flex items-center text-[9px] font-black uppercase tracking-wider text-g3-green bg-g3-green/10 border border-g3-green/20 px-1.5 py-0.5 rounded shadow-sm">
                                                        🔥 Precio Especial
                                                    </span>
                                                </div>
                                                <p class="text-lg font-black text-g3-green leading-none">${{ number_format($product->wholesale_price, 0, ',', '.') }}</p>
                                            @else
                                                <p class="text-lg font-black text-[var(--color-primary)] leading-none">${{ number_format($product->retail_price, 0, ',', '.') }}</p>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Botón de Añadir al Carrito --}}
                                    <div class="w-full">
                                        <livewire:add-to-cart :product="$product" :compact="true" wire:key="add-cart-{{ $product->id }}" />
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="col-span-full py-24 text-center bg-g3-card rounded-2xl border border-zinc-800">
                            <svg class="mx-auto h-16 w-16 text-zinc-700 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <h3 class="text-lg font-bold text-white">No se encontraron productos</h3>
                            <p class="mt-1 text-g3-silver text-sm">Intenta con otra búsqueda o categoría.</p>
                        </div>
                    @endforelse
                </div>

                {{-- Pagination --}}
                <div class="mt-12">
                    {{ $products->links() }}
                </div>
            </div>
        </div>
        
    </div>
</div>
