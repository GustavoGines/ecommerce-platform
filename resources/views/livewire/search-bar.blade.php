<?php

use Livewire\Volt\Component;
use App\Models\Product;

new class extends Component {
    public $search = '';
    public $results = [];
    public $variant = 'navbar';

    public function updatedSearch()
    {
        if (strlen($this->search) >= 2) {
            $this->results = Product::where('name', 'like', '%' . $this->search . '%')
                                    ->orWhere('description', 'like', '%' . $this->search . '%')
                                    ->with('category') // Fix N+1 queries
                                    ->take(5)
                                    ->get();
        } else {
            $this->results = [];
        }
    }

    public function clearSearch()
    {
        $this->search = '';
        $this->results = [];
    }

    public function getThemeProperty()
    {
        $settings = \App\Models\StoreSetting::getSettings();
        return $settings ? ($settings->theme_name ?? 'modern-light') : 'modern-light';
    }
}; ?>

<div class="relative flex-1 {{ $this->variant === 'hero' ? 'max-w-xl mx-auto lg:mx-0' : 'max-w-lg mx-auto' }}" x-data="{ 
    open: false,
    history: JSON.parse(localStorage.getItem('searchHistory') || '[]'),
    addHistory(term) {
        if(!term || term.trim().length < 2) return;
        term = term.trim();
        let arr = this.history.filter(i => i.toLowerCase() !== term.toLowerCase());
        arr.unshift(term);
        if(arr.length > 5) arr.pop();
        this.history = arr;
        localStorage.setItem('searchHistory', JSON.stringify(arr));
    },
    removeHistory(term, e) {
        if(e) e.stopPropagation();
        this.history = this.history.filter(i => i !== term);
        localStorage.setItem('searchHistory', JSON.stringify(this.history));
    }
}" @click.away="open = false; $wire.clearSearch()">
    <!-- Search Input -->
    @if($this->variant === 'hero')
        <form action="{{ route('shop') }}" method="GET" class="relative flex items-center group" @submit="addHistory($wire.search)">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-transform group-focus-within:scale-110 group-focus-within:text-[var(--color-primary)]">
                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input type="text" name="q" wire:model.live.debounce.300ms="search" @focus="open = true" autocomplete="off" placeholder="Ej: Control Samsung, Noblex, Aire Surrey..." 
                   class="block w-full pl-11 pr-32 py-4 border border-gray-300 rounded-full text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent text-lg shadow-sm transition-all duration-300 hover:shadow-md focus:shadow-[0_0_20px_rgba(220,38,38,0.2)] bg-white/90 backdrop-blur-sm">
            <button type="submit" 
                    class="absolute right-2 top-2 bottom-2 px-6 rounded-full text-white font-bold transition-all duration-300 active:scale-95 hover:shadow-lg hover:shadow-red-500/30"
                    style="background-color: var(--color-primary);">
                Buscar
            </button>
            
            <!-- Loading Indicator -->
            <div wire:loading.flex wire:target="search" class="absolute right-[110px] top-1/2 -translate-y-1/2 flex items-center pointer-events-none">
                <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </form>
    @else
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input 
                wire:model.live.debounce.300ms="search" 
                @focus="open = true"
                @keydown.enter.prevent="if($wire.search.length >= 2) { addHistory($wire.search); window.location.href = '{{ route('shop') }}?q=' + encodeURIComponent($wire.search) }"
                type="text" 
                placeholder="Buscar productos..." 
                class="block w-full pl-10 pr-3 py-2 rounded-full leading-5 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all sm:text-sm shadow-sm {{ $this->theme === 'tech-dark' ? 'bg-[#0a0f1c] border border-white/10 text-white placeholder-gray-500' : 'border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-500' }}"
            >
            
            <!-- Loading Indicator -->
            <div wire:loading.flex wire:target="search" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <svg class="animate-spin h-4 w-4 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>
    @endif

    <!-- Dropdown Results -->
    <div x-show="open && ($wire.search.length >= 2 || history.length > 0)" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-1"
         class="absolute z-50 mt-2 w-full rounded-2xl shadow-xl dark:shadow-[0_10px_40px_-10px_rgba(0,0,0,0.5)] overflow-hidden backdrop-blur-md {{ $this->theme === 'tech-dark' ? 'bg-[#0a0f1c]/95 border border-white/10' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/50' }}"
         style="display: none;">
        
        @if(strlen($search) >= 2)
            @if(count($results) > 0)
                <ul class="max-h-96 overflow-y-auto divide-y {{ $this->theme === 'tech-dark' ? 'divide-white/5' : 'divide-gray-100 dark:divide-gray-700/50' }}">
                    @foreach($results as $product)
                        <li>
                            <a href="{{ route('product.detail', $product->slug) }}" wire:navigate @click="addHistory('{{ $search }}')" class="flex items-center px-4 py-3 transition-colors group {{ $this->theme === 'tech-dark' ? 'hover:bg-white/5' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50' }}">
                                <div class="flex-shrink-0 h-10 w-10 rounded flex items-center justify-center overflow-hidden {{ $this->theme === 'tech-dark' ? 'bg-[#030712] border border-white/5' : 'bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700' }}">
                                    @if($product->image_url)
                                        <img src="{{ asset('storage/' . $product->image_url) }}" alt="" class="h-full object-contain">
                                    @endif
                                </div>
                                <div class="ml-3 flex-1 overflow-hidden">
                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate group-hover:text-[var(--color-primary)] transition-colors">{{ $product->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $product->category ? $product->category->name : 'Sin Categoría' }}
                                    </p>
                                </div>
                                <div class="ml-2 text-right">
                                    <span class="text-sm font-black text-gray-900 dark:text-white">${{ number_format($product->retail_price, 2) }}</span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="px-4 py-8 text-center">
                    <svg class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No encontramos resultados para "<span class="font-bold text-gray-900 dark:text-white">{{ $search }}</span>"</p>
                </div>
            @endif
        @else
            {{-- Historial de Búsquedas --}}
            <template x-if="history.length > 0">
                <div class="py-2">
                    <div class="px-4 py-2 flex items-center justify-between">
                        <h4 class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Búsquedas Recientes
                        </h4>
                        <button type="button" @click="history = []; localStorage.removeItem('searchHistory')" class="text-[10px] text-gray-400 hover:text-red-500 hover:underline">Borrar todas</button>
                    </div>
                    <ul class="divide-y {{ $this->theme === 'tech-dark' ? 'divide-white/5' : 'divide-gray-100 dark:divide-gray-700/50' }}">
                        <template x-for="item in history" :key="item">
                            <li>
                                <button type="button" @click="$wire.set('search', item); open = true" class="w-full flex items-center px-4 py-2.5 transition-colors group {{ $this->theme === 'tech-dark' ? 'hover:bg-white/5' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50' }}">
                                    <svg class="h-4 w-4 text-gray-400 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <span class="flex-1 text-sm font-medium text-gray-700 dark:text-gray-300 group-hover:text-[var(--color-primary)] text-left truncate" x-text="item"></span>
                                    <span @click="removeHistory(item, $event)" class="text-gray-400 hover:text-red-500 p-1.5 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
        @endif
    </div>
</div>
