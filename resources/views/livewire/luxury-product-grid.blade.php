<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use App\Models\Product;
use App\Models\Category;

new class extends Component {
    use Livewire\WithPagination;

    #[Url(as: 'categoria')]
    public $selectedCategory = null;
    
    #[Url(as: 'q')]
    public $search = '';
    
    #[Url(as: 'min')]
    public $minPrice = null;
    
    #[Url(as: 'max')]
    public $maxPrice = null;

    #[Url(as: 'sort')]
    public $sort = 'default';

    public $categories = [];

    public function mount()
    {
        $this->categories = Category::has('products')->withCount('products')->get();
        
        // Si nos pasan un string en la URL en vez del ID, tratamos de buscar la categoría
        if ($this->selectedCategory && !is_numeric($this->selectedCategory)) {
            $cat = Category::where('name', 'like', '%' . $this->selectedCategory . '%')->first();
            if ($cat) {
                $this->selectedCategory = $cat->id;
            } else {
                $this->selectedCategory = null; // reset if not found
            }
        }
    }

    public function setCategory($categoryId)
    {
        $this->selectedCategory = $categoryId;
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
        
        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
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
        
        // Recently Viewed Products
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
            'products' => $query->paginate(12),
            'popularProducts' => \Illuminate\Support\Facades\Cache::remember('popularProducts', 3600, fn() => Product::latest()->take(3)->get()),
            'recentlyViewedProducts' => $recentlyViewedProducts,
            'categories' => Category::has('products')->withCount('products')->get(),
            'brands' => Brand::has('products')->withCount('products')->get()
        ];
    }
}; ?>

<div id="catalog" class="w-full relative z-10 py-16 lg:py-24 bg-[#030712]" x-data="{ intersecting: false, sidebarOpen: false }" x-intersect.once="intersecting = true">
    
    {{-- Header Banner Estilo Etonal (Mini-Hero para el Shop) --}}
    <div class="max-w-7xl mx-auto px-6 lg:px-8 mb-16 transition-all duration-1000 transform" :class="intersecting ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0'">
        <div class="relative w-full h-64 md:h-80 rounded-[2rem] overflow-hidden bg-[#030712] border border-white/10 flex items-center group shadow-2xl">
            
            {{-- Ambient Glow --}}
            <div class="absolute top-[-50%] left-[-10%] w-[60%] h-[200%] rounded-full bg-[var(--color-primary)] opacity-20 filter blur-[100px] group-hover:opacity-30 transition-opacity duration-1000 mix-blend-screen pointer-events-none"></div>
            <div class="absolute bottom-[-50%] right-[-10%] w-[50%] h-[200%] rounded-full bg-purple-600 opacity-20 filter blur-[100px] group-hover:opacity-30 transition-opacity duration-1000 mix-blend-screen pointer-events-none"></div>
            
            {{-- Technical Grid overlay --}}
            <div class="absolute inset-0 opacity-10 pointer-events-none" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 32px 32px;"></div>
            
            {{-- Gradient mask to ensure text is readable --}}
            <div class="absolute inset-0 bg-gradient-to-r from-[#030712] via-[#030712]/90 to-transparent z-10 pointer-events-none"></div>
            
            {{-- Floating Product Render --}}
            <div class="absolute right-0 top-1/2 -translate-y-1/2 w-full md:w-1/2 h-full z-20 flex justify-end items-center pe-0 md:pe-10 opacity-30 md:opacity-100 transition-transform duration-700 group-hover:scale-105 pointer-events-none">
                <img src="{{ asset('storage/banners/cpu_banner.png') }}" onerror="this.src='https://images.unsplash.com/photo-1591488320449-011701bb6704?q=80&w=800&auto=format&fit=crop'; this.classList.add('mix-blend-lighten');" alt="Premium Hardware" class="w-full max-w-[400px] object-contain drop-shadow-[0_0_30px_rgba(37,99,235,0.3)] animate-float-slow">
            </div>
            
            {{-- Text Content --}}
            <div class="relative z-30 px-8 md:px-16 w-full max-w-2xl">
                {{-- Elite Badge --}}
                <div class="mb-5 inline-flex items-center gap-2.5 px-3 py-1 rounded-full border border-[var(--color-primary)]/30 bg-[var(--color-primary)]/10 backdrop-blur-md">
                    <span class="w-1.5 h-1.5 rounded-full bg-[var(--color-primary)] animate-pulse shadow-[0_0_10px_var(--color-primary)]"></span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-white">Hardware Elite</span>
                </div>
                
                <h2 class="text-4xl md:text-6xl font-black text-white tracking-tighter mb-4 leading-none">
                    Catálogo <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-white via-gray-300 to-gray-600">Premium</span>
                </h2>
                <p class="text-gray-400 text-sm md:text-base font-light max-w-[400px] leading-relaxed">
                    Equípate con componentes de alto rendimiento. Diseñados para quienes no aceptan compromisos.
                </p>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 lg:px-8 transition-all duration-1000 delay-300 transform" :class="intersecting ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0'">
        
        {{-- Mobile Filter Trigger --}}
        <div class="lg:hidden flex justify-between items-center mb-6">
            <span class="text-white font-bold text-lg">Hardware ({{ $products->total() }})</span>
            <button @click="sidebarOpen = true" class="flex items-center gap-2 px-4 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm hover:bg-white/10 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                Filtros
            </button>
        </div>

        <div class="flex flex-col lg:flex-row gap-10">
            
            {{-- SIDEBAR (Left Column 25%) --}}
            <aside class="w-full lg:w-1/4 shrink-0 fixed lg:relative inset-y-0 left-0 z-50 lg:z-0 bg-[#0a0f1c] lg:bg-transparent border-r lg:border-none border-white/5 transform lg:transform-none transition-transform duration-300 overflow-y-auto lg:overflow-visible p-6 lg:p-0"
                   :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
                
                {{-- Close Button Mobile --}}
                <div class="flex justify-between items-center lg:hidden mb-8">
                    <h3 class="text-white font-bold text-xl tracking-wide">Filtros</h3>
                    <button @click="sidebarOpen = false" class="p-2 text-gray-400 hover:text-white rounded-full bg-white/5">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="space-y-10">
                    {{-- Search Input --}}
                    <div>
                        <div class="relative">
                            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar hardware..." class="w-full bg-[#0a0f1c] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:ring-[var(--color-primary)] focus:border-white/30 placeholder-gray-500 transition-all outline-none">
                            @if($search)
                                <button wire:click="$set('search', '')" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            @else
                                <svg class="w-4 h-4 text-gray-500 absolute right-4 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            @endif
                        </div>
                    </div>

                    {{-- Categories --}}
                    <div>
                        <h4 class="text-white font-bold text-sm tracking-widest uppercase mb-4 pb-4 border-b border-white/10">Categorías</h4>
                        <ul class="space-y-3">
                            <li>
                                <button wire:click="setCategory(null)" class="w-full flex items-center justify-between text-[13px] font-medium transition-colors group {{ $selectedCategory === null ? 'text-white' : 'text-gray-400 hover:text-white' }}">
                                    <div class="flex items-center gap-3">
                                        <div class="w-1.5 h-1.5 rounded-full transition-colors {{ $selectedCategory === null ? 'bg-[var(--color-primary)] shadow-[0_0_8px_var(--color-primary)]' : 'bg-transparent group-hover:bg-gray-600' }}"></div>
                                        <span>Todas</span>
                                    </div>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full transition-colors {{ $selectedCategory === null ? 'bg-[var(--color-primary)]/20 text-[var(--color-primary)]' : 'bg-white/5 text-gray-500' }}">{{ \App\Models\Product::count() }}</span>
                                </button>
                            </li>
                            @foreach($categories as $category)
                                <li>
                                        <span class="text-[10px] bg-white/5 px-2 py-0.5 rounded-full">{{ $category->products_count }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Functional Price Range --}}
                    <div>
                        <h4 class="text-white font-bold text-sm tracking-widest uppercase mb-4 pb-4 border-b border-white/10">Rango de Precio</h4>
                        <div class="px-2" x-data="{ minPrice: $wire.entangle('minPrice').live, maxPrice: $wire.entangle('maxPrice').live }">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="w-1/2 relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                                    <input type="number" x-model.debounce.500ms="minPrice" placeholder="Min" class="w-full bg-[#0a0f1c] border border-white/10 rounded-xl pl-7 pr-3 py-2 text-sm text-white focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] placeholder-gray-600 outline-none">
                                </div>
                                <span class="text-gray-500 font-bold">-</span>
                                <div class="w-1/2 relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                                    <input type="number" x-model.debounce.500ms="maxPrice" placeholder="Max" class="w-full bg-[#0a0f1c] border border-white/10 rounded-xl pl-7 pr-3 py-2 text-sm text-white focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] placeholder-gray-600 outline-none">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Popular Tags --}}
                    <div>
                        <h4 class="text-white font-bold text-sm tracking-widest uppercase mb-4 pb-4 border-b border-white/10">Etiquetas Populares</h4>
                        <div class="flex flex-wrap gap-2">
                            <span wire:click="setTag('Gamer')" class="px-3 py-1 bg-white/5 border border-white/10 rounded-lg text-xs text-gray-400 hover:text-white hover:border-white/30 cursor-pointer transition-colors {{ $search === 'Gamer' ? 'bg-white/20 text-white border-white/40' : '' }}">Gamer</span>
                            <span wire:click="setTag('High-End')" class="px-3 py-1 bg-white/5 border border-white/10 rounded-lg text-xs text-gray-400 hover:text-white hover:border-white/30 cursor-pointer transition-colors {{ $search === 'High-End' ? 'bg-white/20 text-white border-white/40' : '' }}">High-End</span>
                            <span wire:click="setTag('4K Ready')" class="px-3 py-1 bg-white/5 border border-white/10 rounded-lg text-xs text-gray-400 hover:text-white hover:border-white/30 cursor-pointer transition-colors {{ $search === '4K Ready' ? 'bg-white/20 text-white border-white/40' : '' }}">4K Ready</span>
                            <span wire:click="setTag('RGB')" class="px-3 py-1 bg-white/5 border border-white/10 rounded-lg text-xs text-gray-400 hover:text-white hover:border-white/30 cursor-pointer transition-colors {{ $search === 'RGB' ? 'bg-white/20 text-white border-white/40' : '' }}">RGB</span>
                        </div>
                    </div>

                    {{-- Popular Products Widget --}}
                    <div>
                        <h4 class="text-white font-bold text-sm tracking-widest uppercase mb-4 pb-4 border-b border-white/10">Destacados</h4>
                        <div class="space-y-4">
                            @foreach($popularProducts as $pop)
                                <a href="{{ route('product.detail', $pop->slug) }}" wire:navigate class="flex items-center gap-3 group">
                                    <div class="w-12 h-12 rounded-lg bg-white/5 border border-white/10 overflow-hidden flex items-center justify-center shrink-0 p-2">
                                        @if($pop->image_url)
                                            <img src="{{ asset('storage/' . $pop->image_url) }}" alt="{{ $pop->name }}" class="w-full h-full object-contain drop-shadow-md group-hover:scale-110 transition-transform mix-blend-lighten">
                                        @endif
                                    </div>
                                    <div>
                                        <h5 class="text-xs font-bold text-gray-300 group-hover:text-white transition-colors line-clamp-2 leading-tight">{{ $pop->name }}</h5>
                                        <span class="text-[10px] text-[var(--color-primary)] font-black mt-1 block">${{ number_format($pop->retail_price ?? 0, 0, ',', '.') }}</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </aside>
            
            {{-- Mobile Sidebar Backdrop --}}
            <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden" x-transition.opacity></div>

            {{-- PRODUCTS GRID (Right Column 75% - VITRINE EFFECT) --}}
            <div class="w-full lg:w-3/4">
                
                {{-- Desktop Top Bar (Sorting/Results) --}}
                <div class="hidden lg:flex justify-between items-center mb-6">
                    <span class="text-gray-400 text-sm">Mostrando <strong class="text-white">{{ $products->count() }}</strong> de {{ $products->total() }} productos</span>
                    <select wire:model.live="sort" class="bg-[#0a0f1c] border border-white/10 text-white text-sm rounded-lg focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] block p-2.5">
                        <option value="default">Ordenar por defecto</option>
                        <option value="price_asc">Precio: Menor a Mayor</option>
                        <option value="price_desc">Precio: Mayor a Menor</option>
                        <option value="recent">Más recientes</option>
                    </select>
                </div>

                {{-- The Vitrine Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 border-l border-t border-white/5 bg-[#030712]">
                    @forelse ($products as $index => $product)
                        <article wire:key="product-{{ $product->id }}"
                                 class="group relative flex flex-col bg-[#0a0f1c]/50 hover:bg-[#0a0f1c] border-r border-b border-white/5 transition-all duration-500 hover:shadow-[inset_0_0_40px_rgba(37,99,235,0.05)]">
                            
                            {{-- Image Area --}}
                            <div class="relative aspect-square overflow-hidden p-8 flex items-center justify-center">
                                @if($product->image_url)
                                    <img src="{{ asset('storage/' . $product->image_url) }}"
                                         alt="{{ $product->name }}"
                                         class="w-full h-full object-contain transition-transform duration-1000 group-hover:scale-110 drop-shadow-xl"
                                         onerror="this.src='https://images.unsplash.com/photo-1591488320449-011701bb6704?q=80&w=600&auto=format&fit=crop'; this.classList.add('mix-blend-lighten')">
                                @else
                                    <svg class="h-16 w-16 text-gray-800" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                @endif

                                {{-- Quick Actions Hover (Desktop) --}}
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 hidden md:flex items-center justify-center gap-3 backdrop-blur-[2px]">
                                    <a href="{{ route('product.detail', $product->slug) }}" wire:navigate class="w-12 h-12 rounded-full bg-white/10 hover:bg-white/30 backdrop-blur-md flex items-center justify-center text-white transition-all transform translate-y-4 group-hover:translate-y-0 duration-300">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <div class="transform translate-y-4 group-hover:translate-y-0 duration-300 delay-75">
                                        <livewire:add-to-cart :product="$product" :compact="true" wire:key="add-cart-desk-{{ $product->id }}" />
                                    </div>
                                </div>
                            </div>

                            {{-- Content Area --}}
                            <div class="p-6 pt-0 flex flex-col flex-grow relative z-20">
                                <div class="flex justify-between items-start mb-2 gap-2">
                                    @if($product->category)
                                        <span class="text-[9px] font-bold uppercase tracking-widest text-gray-500 block truncate">
                                            {{ $product->category->name }}
                                        </span>
                                    @else
                                        <span></span>
                                    @endif

                                    {{-- Stock Badge --}}
                                    @if($product->stock <= 0)
                                        <span class="shrink-0 text-[9px] font-bold uppercase tracking-widest px-2 py-0.5 bg-red-500/10 text-red-400 border border-red-500/20 whitespace-nowrap">
                                            Agotado
                                        </span>
                                    @elseif($product->stock <= ($product->min_stock ?? 5))
                                        <span class="shrink-0 text-[9px] font-bold uppercase tracking-widest px-2 py-0.5 bg-orange-500/10 text-orange-400 border border-orange-500/20 whitespace-nowrap" title="Stock Disponible">
                                            ¡Quedan {{ $product->stock }}!
                                        </span>
                                    @else
                                        <span class="shrink-0 text-[9px] font-bold uppercase tracking-widest px-2 py-0.5 bg-[#1a1f2c]/40 text-gray-300 border border-white/5 whitespace-nowrap" title="Stock Disponible">
                                            Stock: {{ $product->stock }}
                                        </span>
                                    @endif
                                </div>

                                <a href="{{ route('product.detail', $product->slug) }}" wire:navigate class="group/link flex justify-between items-start gap-4">
                                    <h3 class="text-base font-bold text-gray-200 leading-snug line-clamp-2 transition-colors group-hover:text-white">
                                        {{ $product->name }}
                                    </h3>
                                </a>
                                
                                <div class="mt-auto pt-4 flex items-end justify-between">
                                    <div>
                                        <p class="text-xl font-black text-white">${{ number_format($product->retail_price, 0, ',', '.') }}</p>
                                    </div>
                                    
                                    {{-- Mobile Add to Cart (Always visible on mobile, hidden on desktop) --}}
                                    <div class="md:hidden">
                                        <livewire:add-to-cart :product="$product" :compact="true" wire:key="add-cart-mob-{{ $product->id }}" />
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="col-span-full py-32 text-center border-r border-b border-white/5 bg-[#0a0f1c]/30">
                            <svg class="mx-auto h-12 w-12 text-gray-700 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <h3 class="text-xl font-bold text-gray-400 tracking-tight mb-2">Colección Vacía</h3>
                            <p class="text-gray-600 font-light text-sm">Aún no hemos agregado piezas exclusivas a esta categoría.</p>
                        </div>
                    @endforelse
                </div>

                {{-- Pagination --}}
                <div class="mt-12">
                    {{ $products->links() }}
                </div>
            </div>
        </div>
        
        {{-- Recently Viewed Products (Catalog Footer) --}}
        @if($recentlyViewedProducts->count() > 0)
        <div class="mt-24 pt-20 border-t border-white/5">
            <h3 class="text-2xl font-black text-white tracking-tight mb-10 text-center">Recientemente Vistos</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                @foreach ($recentlyViewedProducts as $viewed)
                    <a href="{{ route('product.detail', $viewed->slug) }}" wire:navigate class="group relative flex flex-col bg-white/5 backdrop-blur-xl rounded-2xl border border-white/5 overflow-hidden transition-all duration-500 hover:border-[var(--color-primary)]/50 hover:shadow-[0_20px_40px_-15px_var(--color-primary-glow)] hover:-translate-y-2">
                        <div class="relative aspect-[4/3] bg-[#0a0f1c] p-6 flex items-center justify-center border-b border-white/5 overflow-hidden">
                            @if($viewed->image_url)
                                <img src="{{ asset('storage/' . $viewed->image_url) }}" class="object-contain w-full h-full transform group-hover:scale-110 transition-transform duration-700 drop-shadow-xl" onerror="this.src='https://images.unsplash.com/photo-1587202372775-e229f172b9d7?q=80&w=400&auto=format&fit=crop'; this.classList.add('mix-blend-lighten')">
                            @endif
                            <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                <span class="text-xs font-bold text-white uppercase tracking-widest bg-white/10 backdrop-blur-md px-4 py-2 rounded-full">Ver Detalles</span>
                            </div>
                        </div>
                        <div class="p-6">
                            <h4 class="font-bold text-white truncate transition-colors">{{ $viewed->name }}</h4>
                            <div class="mt-2 text-gray-400 font-bold">
                                ${{ number_format($viewed->retail_price, 0, ',', '.') }}
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
        @endif
        
    </div>
</div>
