<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public $count = 0;
    public $theme = 'modern-light';

    public function mount()
    {
        $settings = \App\Models\StoreSetting::getSettings();
        if ($settings) {
            $this->theme = $settings->theme_name ?? 'modern-light';
        }
        $this->updateCount();
    }

    public function updateCount($count = null)
    {
        if ($count !== null) {
            $this->count = $count;
        } else {
            $cartService = app(\App\Services\CartService::class);
            $cart = $cartService->getCartItemsArray();
            $this->count = array_sum($cart);
        }
    }
}; ?>

<button onclick="POS.openCart()"
        x-data="{ count: {{ $count }}, pending: 0, pendingTimeout: null }"
        @cart-added-local.window="
            count += $event.detail.qty; 
            pending++; 
            clearTimeout(pendingTimeout);
            pendingTimeout = setTimeout(() => { pending = 0; }, 5000);
        "
        @cart-cleared-local.window="count = 0"
        @cart-item-deleted-local.window="count -= $event.detail.qty"
        @cart-badge-updated.window="
            if(pending > 0) pending--;
            if(pending === 0) {
                if($event.detail[0] && $event.detail[0].count !== undefined) count = $event.detail[0].count; 
                else if($event.detail && $event.detail.count !== undefined) count = $event.detail.count;
            }
        "
               class="relative p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-white
               transition-all focus:outline-none
               flex items-center justify-center"
        aria-label="Abrir carrito">
    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
    </svg>
    <span x-show="count > 0" x-text="count" x-cloak class="absolute -top-1 -right-1 flex items-center justify-center min-w-[20px] h-5 px-1
                 text-[10px] font-bold text-white rounded-full shadow-lg transition-transform duration-300"
          style="background-color: var(--color-primary); box-shadow: 0 2px 8px var(--color-primary-glow);">
    </span>
</button>
