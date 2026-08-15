<?php

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads, WithPagination;

    // WithPagination necesita que $products NO sea propiedad pública
    // La paginación se maneja automáticamente via computed / paginate()
    
    public $product_id = null;
    public $category_id = '';
    public $brand_ids = [];
    public $name = '';
    public $sku = '';
    public $description = '';
    public $cost_price = 0;
    public $profit_margin = 0;
    public $wholesale_discount = 0;
    public $wholesale_min_quantity = 10;
    public $retail_price = 0;
    public $wholesale_price = 0;
    public $stock = 0;
    public $min_stock = 2;
    public $image;
    public $current_image_url;
    public $delete_image = false;
    
    public $new_brand_name = '';
    
    public $showModal = false;
    public $showBrandListModal = false;
    public $showCategoryListModal = false;

    public $manageBrandId = null;
    public $manageBrandName = '';
    public $manageCategoryId = null;
    public $manageCategoryName = '';

    public $sortField = 'id';
    public $sortDirection = 'desc';
    public $search = '';
    public $filter_category_id = '';
    public $filter_brand_id = '';
    public $perPage = 50;

    // Selections and Visibility
    public $selectedProducts = [];
    public $selectAll = false;
    
    public $visibleColumns = [
        'image' => true,
        'name' => true,
        'category' => true,
        'brand' => true,
        'cost' => true,
        'retail' => true,
        'wholesale' => true,
        'stock' => true,
    ];

    #[Livewire\Attributes\Url(as: 'edit')]
    public $edit_id = null;

    public function mount()
    {
        $user = auth()->user();
        if ($user && $user->table_preferences) {
            $this->visibleColumns = array_merge($this->visibleColumns, $user->table_preferences);
        }

        if ($this->edit_id) {
            $this->edit($this->edit_id);
            $this->edit_id = null; // Clear it so it disappears from URL automatically
        }
    }

    public function updatedVisibleColumns()
    {
        $user = auth()->user();
        if ($user) {
            $user->table_preferences = $this->visibleColumns;
            $user->save();
        }
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            // Solo selecciona los de la página actual
            $this->selectedProducts = $this->with()['products']->pluck('id')->map(fn($id) => (string)$id)->toArray();
        } else {
            $this->selectedProducts = [];
        }
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage(); // PERF-02: vuelve a página 1 al reordenar
    }

    #[Livewire\Attributes\On('products-imported')]
    public function loadProducts()
    {
        $this->resetPage();
    }
    
    public function updatedFilterCategoryId()
    {
        $this->resetPage();
    }
    
    public function updatedFilterBrandId()
    {
        $this->resetPage();
    }

    public function with(): array
    {
        // PERF-01: Ordena desde SQL con JOIN en lugar de cargar todo en PHP.
        $query = Product::query()->with(['category', 'brands']);

        // Filtro de búsqueda por nombre
        if (!empty($this->search)) {
            $query->where('products.name', 'like', '%' . $this->search . '%');
        }
        
        // Filtro por categoría
        if (!empty($this->filter_category_id)) {
            $query->where('products.category_id', $this->filter_category_id);
        }

        // Filtro por marca
        if (!empty($this->filter_brand_id)) {
            $query->whereHas('brands', function($q) {
                $q->where('brands.id', $this->filter_brand_id);
            });
        }

        // Ordenamiento: category y brand usan LEFT JOIN para operar en SQL
        if ($this->sortField === 'category_name') {
            $query->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                  ->orderBy('categories.name', $this->sortDirection)
                  ->select('products.*');
        } elseif ($this->sortField === 'brand_name') {
            $query->orderBy(Brand::select('name')
                ->join('brand_product', 'brands.id', '=', 'brand_product.brand_id')
                ->whereColumn('brand_product.product_id', 'products.id')
                ->limit(1), $this->sortDirection)
                  ->select('products.*');
        } else {
            $query->orderBy('products.' . $this->sortField, $this->sortDirection);
        }

        // PERF-02: Paginación — solo carga $perPage registros a la vez
        return [
            'products' => $query->paginate($this->perPage),
            'categories' => Category::withCount('products')->orderBy('name')->get(),
            'brands' => Brand::withCount('products')->orderBy('name')->get(),
        ];
    }

    // --- CRUD DE PRODUCTOS ---
    public function create()
    {
        $this->resetFields();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $product = Product::find($id);
        $this->product_id = $product->id;
        $this->category_id = $product->category_id;
        $this->brand_ids = $product->brands->pluck('id')->map(fn($id) => (string)$id)->toArray();
        $this->name = $product->name;
        $this->sku = $product->sku;
        $this->description = $product->description;
        $this->cost_price = (float) $product->cost_price;
        $this->profit_margin = (int) $product->profit_margin;
        $this->wholesale_discount = (int) $product->wholesale_discount;
        $this->wholesale_min_quantity = $product->wholesale_min_quantity ?? 10;
        $this->retail_price = (float) $product->retail_price;
        $this->wholesale_price = (float) $product->wholesale_price;
        $this->stock = $product->stock;
        $this->min_stock = $product->min_stock ?? 2;
        $this->current_image_url = $product->image_url;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255|unique:products,sku,' . $this->product_id,
            'category_id' => 'required|exists:categories,id',
            'brand_ids' => 'nullable|array',
            'brand_ids.*' => 'exists:brands,id',
            'cost_price' => 'required|numeric|min:0',
            'profit_margin' => 'required|integer|min:0',
            'wholesale_discount' => 'required|integer|min:0|max:100',
            'wholesale_min_quantity' => 'required|integer|min:1',
            'retail_price' => 'required|numeric|min:0',
            'wholesale_price' => 'required|numeric|min:0|lte:retail_price',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'image' => 'nullable|image|max:2048',
        ]);

        $data = [
            'name' => $this->name,
            'sku' => empty($this->sku) ? null : $this->sku,
            'category_id' => $this->category_id,
            // brand_ids se manejan con sync()
            'description' => $this->description,
            'cost_price' => $this->cost_price,
            'profit_margin' => $this->profit_margin,
            'wholesale_discount' => $this->wholesale_discount,
            'wholesale_min_quantity' => $this->wholesale_min_quantity,
            'retail_price' => $this->retail_price,
            'wholesale_price' => $this->wholesale_price,
            'stock' => $this->stock,
            'min_stock' => $this->min_stock,
        ];

        if ($this->delete_image && $this->product_id) {
            $p = Product::find($this->product_id);
            if ($p && $p->image_url) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($p->image_url);
            }
            $data['image_url'] = null;
        }

        if ($this->image) {
            $data['image_url'] = $this->image->store('products', 'public');
        }

        $product = Product::updateOrCreate(['id' => $this->product_id], $data);
        $product->brands()->sync($this->brand_ids);

        $this->showModal = false;
        $this->loadProducts();
        $this->resetFields();
    }

    public function delete($id)
    {
        $product = Product::findOrFail($id);
        
        if (\App\Models\OrderItem::where('product_id', $product->id)->exists()) {
            $this->dispatch('notify', message: 'No se puede eliminar: el producto está en órdenes de compra.');
            return;
        }

        if ($product->image_url) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($product->image_url);
        }
        
        $product->delete();
        $this->loadProducts();
        $this->dispatch('notify', message: 'Producto eliminado correctamente.');
    }

    public function createBrandQuick($name)
    {
        if (empty(trim($name))) return;
        $name = trim($name);
        
        $brand = Brand::firstOrCreate(
            ['slug' => \Illuminate\Support\Str::slug($name)],
            ['name' => $name]
        );
        $this->brand_ids[] = (string)$brand->id;
        $this->dispatch('notify', message: 'Marca creada y seleccionada exitosamente.');
    }

    public function createCategoryQuick($name)
    {
        if (empty(trim($name))) return;
        $name = trim($name);
        
        $category = Category::firstOrCreate(
            ['slug' => \Illuminate\Support\Str::slug($name)],
            ['name' => $name]
        );
        $this->category_id = $category->id;
        $this->dispatch('notify', message: 'Categoría creada y seleccionada exitosamente.');
    }

    public function resetFields()
    {
        $this->product_id = null;
        $this->category_id = '';
        $this->brand_ids = [];
        $this->name = '';
        $this->sku = '';
        $this->description = '';
        $this->cost_price = 0;
        $this->profit_margin = 0;
        $this->wholesale_discount = 0;
        $this->wholesale_min_quantity = 10;
        $this->retail_price = 0;
        $this->wholesale_price = 0;
        $this->stock = 0;
        $this->min_stock = 2;
        $this->image = null;
        $this->current_image_url = null;
        $this->delete_image = false;
    }

    public function removeImage()
    {
        $this->current_image_url = null;
        $this->image = null;
        $this->delete_image = true;
    }

    public function updatedCostPrice() { $this->calculatePrices(); }
    public function updatedProfitMargin() { $this->calculatePrices(); }
    public function updatedWholesaleDiscount() { $this->calculatePrices(); }

    public function calculatePrices()
    {
        $cost = (float) $this->cost_price;
        $profit = (float) $this->profit_margin;
        $discount = (float) $this->wholesale_discount;

        if ($cost >= 0 && $profit >= 0) {
            $this->retail_price = round($cost * (1 + ($profit / 100)), 2);
            if ($discount >= 0) {
                $this->wholesale_price = round($this->retail_price * (1 - ($discount / 100)), 2);
            }
        }
    }

    public function updatedRetailPrice()
    {
        $cost = (float) $this->cost_price;
        $retail = (float) $this->retail_price;
        $discount = (float) $this->wholesale_discount;

        if ($cost > 0) {
            $this->profit_margin = (int) round((($retail / $cost) - 1) * 100);
        }
        
        if ($discount >= 0) {
            $this->wholesale_price = round($retail * (1 - ($discount / 100)), 2);
        }
    }

    public function updatedWholesalePrice()
    {
        $retail = (float) $this->retail_price;
        $wholesale = (float) $this->wholesale_price;

        if ($retail > 0) {
            $this->wholesale_discount = (int) round((1 - ($wholesale / $retail)) * 100);
        }
    }

    public function deleteSelected()
    {
        if (count($this->selectedProducts) > 0) {
            if (\App\Models\OrderItem::whereIn('product_id', $this->selectedProducts)->exists()) {
                $this->dispatch('notify', message: 'No se pueden eliminar productos que están en órdenes de compra.');
                return;
            }
            
            $products = Product::whereIn('id', $this->selectedProducts)->get();
            foreach($products as $product) {
                if ($product->image_url) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($product->image_url);
                }
            }
            
            Product::whereIn('id', $this->selectedProducts)->delete();
            $this->selectedProducts = [];
            $this->selectAll = false;
            $this->loadProducts();
            $this->dispatch('notify', message: 'Productos eliminados correctamente.');
        }
    }





    public function editBrand($id)
    {
        $brand = Brand::findOrFail($id);
        $this->manageBrandId = $brand->id;
        $this->manageBrandName = $brand->name;
    }

    public function saveBrand()
    {
        $this->validate(['manageBrandName' => 'required|string|max:255']);
        $slug = \Illuminate\Support\Str::slug($this->manageBrandName);
        
        $query = Brand::where('slug', $slug);
        if ($this->manageBrandId && $this->manageBrandId !== 'new') {
            $query->where('id', '!=', $this->manageBrandId);
        }
        if ($query->exists()) {
            $this->addError('manageBrandName', 'Ya existe una marca con este nombre.');
            return;
        }

        if ($this->manageBrandId && $this->manageBrandId !== 'new') {
            $brand = Brand::find($this->manageBrandId);
            $brand->update(['name' => $this->manageBrandName, 'slug' => $slug]);
            $this->dispatch('notify', message: 'Marca actualizada exitosamente.');
        } else {
            Brand::create(['name' => $this->manageBrandName, 'slug' => $slug]);
            $this->dispatch('notify', message: 'Marca creada exitosamente.');
        }
        $this->manageBrandId = null;
        $this->manageBrandName = '';
    }

    public function deleteBrand($id)
    {
        $brand = Brand::findOrFail($id);
        if ($brand->products()->count() > 0) {
            $this->dispatch('notify', message: 'No se puede eliminar la marca porque tiene productos.');
            return;
        }
        $brand->delete();
        $this->dispatch('notify', message: 'Marca eliminada exitosamente.');
    }

    public function editCategory($id)
    {
        $cat = Category::findOrFail($id);
        $this->manageCategoryId = $cat->id;
        $this->manageCategoryName = $cat->name;
    }

    public function saveCategory()
    {
        $this->validate(['manageCategoryName' => 'required|string|max:255']);
        $slug = \Illuminate\Support\Str::slug($this->manageCategoryName);
        
        $query = Category::where('slug', $slug);
        if ($this->manageCategoryId && $this->manageCategoryId !== 'new') {
            $query->where('id', '!=', $this->manageCategoryId);
        }
        if ($query->exists()) {
            $this->addError('manageCategoryName', 'Ya existe una categoría con este nombre.');
            return;
        }

        if ($this->manageCategoryId && $this->manageCategoryId !== 'new') {
            $cat = Category::find($this->manageCategoryId);
            $cat->update(['name' => $this->manageCategoryName, 'slug' => $slug]);
            $this->dispatch('notify', message: 'Categoría actualizada exitosamente.');
        } else {
            Category::create(['name' => $this->manageCategoryName, 'slug' => $slug]);
            $this->dispatch('notify', message: 'Categoría creada exitosamente.');
        }
        $this->manageCategoryId = null;
        $this->manageCategoryName = '';
    }

    public function deleteCategory($id)
    {
        $cat = Category::findOrFail($id);
        if ($cat->products()->count() > 0) {
            $this->dispatch('notify', message: 'No se puede eliminar la categoría porque tiene productos.');
            return;
        }
        $cat->delete();
        $this->dispatch('notify', message: 'Categoría eliminada exitosamente.');
    }



    // --- ACTUALIZADOR MASIVO DE PRECIOS ---
    public $showMassUpdateModal = false;
    public $massTarget = 'selected'; // selected, category, brand
    public $massCategoryId = '';
    public $massBrandId = '';
    public $massType = 'increase'; // increase, decrease
    public $massValueType = 'percent'; // percent, fixed
    public $massValue = 0;
    public $massField = 'cost_price'; // cost_price, retail_price, wholesale_price
    public $massOverride = false;

    public function openMassUpdate()
    {
        $this->showMassUpdateModal = true;
    }

    public function applyMassUpdate()
    {
        $this->validate([
            'massValue' => 'required|numeric|min:0',
        ]);

        $query = Product::query();

        if ($this->massTarget === 'selected') {
            if (empty($this->selectedProducts)) return;
            $query->whereIn('id', $this->selectedProducts);
        } elseif ($this->massTarget === 'category') {
            if (empty($this->massCategoryId)) return;
            $query->where('category_id', $this->massCategoryId);
        } elseif ($this->massTarget === 'brand') {
            if (empty($this->massBrandId)) return;
            $query->whereHas('brands', fn($q) => $q->where('brands.id', $this->massBrandId));
        }

        $products = $query->get();

        foreach ($products as $product) {
            $currentValue = $product->{$this->massField};
            $adjustment = $this->massValueType === 'percent' 
                ? ($currentValue * ($this->massValue / 100))
                : $this->massValue;

            $newValue = $this->massType === 'increase' 
                ? $currentValue + $adjustment 
                : $currentValue - $adjustment;
            
            $product->{$this->massField} = max(0, $newValue);

            $override = filter_var($this->massOverride, FILTER_VALIDATE_BOOLEAN);

            // Recalculate margins if updating cost and override is FALSE
            if ($this->massField === 'cost_price' && !$override) {
                $cost = (float) $product->cost_price;
                $profit = (float) $product->profit_margin;
                $discount = (float) $product->wholesale_discount;
                
                $product->retail_price = round($cost * (1 + ($profit / 100)), 2);
                $product->wholesale_price = round($product->retail_price * (1 - ($discount / 100)), 2);
            } else {
                // Otherwise, recalculate percentage fields based on new absolute prices to maintain DB integrity.
                if ($product->cost_price > 0) {
                    $product->profit_margin = (int) round((($product->retail_price / $product->cost_price) - 1) * 100);
                } else {
                    $product->profit_margin = 0;
                }

                if ($product->retail_price > 0) {
                    $product->wholesale_discount = (int) round((1 - ($product->wholesale_price / $product->retail_price)) * 100);
                } else {
                    $product->wholesale_discount = 0;
                }
            }

            $product->save();
        }

        $this->showMassUpdateModal = false;
        $this->selectedProducts = [];
        $this->selectAll = false;
        $this->loadProducts();
    }
    public function syncPrices()
    {
        \Illuminate\Support\Facades\Artisan::call('shop:sync-prices');
        $this->dispatch('notify', message: 'Precios sincronizados con éxito.');
    }
}; ?>

<div x-data="{ 
    modalOpen: @entangle('showModal').live, 
    catListOpen: @entangle('showCategoryListModal').live, 
    brandListOpen: @entangle('showBrandListModal').live,
    massUpdateOpen: @entangle('showMassUpdateModal').live,
    colDropdownOpen: false,
    previewImageOpen: false,
    previewImageUrl: '',
    isProductLoading: false
}">


    <div class="w-full mx-auto py-10 px-4 sm:px-6 lg:px-8">
        <!-- Bulk Actions Bar -->
        @if(count($selectedProducts) > 0)
        <div class="bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700/50 rounded-2xl mb-6 p-4 flex items-center justify-between shadow-sm animate-pulse-once">
            <div class="flex items-center text-indigo-800 dark:text-indigo-300 font-medium">
                <span class="bg-indigo-100 dark:bg-indigo-800 px-3 py-1 rounded-full text-indigo-700 dark:text-indigo-300 text-sm font-bold mr-3">{{ count($selectedProducts) }}</span>
                Productos seleccionados
            </div>
            <div class="flex space-x-3">
                <button wire:click="deleteSelected" wire:confirm="¿Estás seguro de eliminar los productos seleccionados?" class="text-xs font-bold bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors shadow-sm">
                    🗑️ Eliminar Seleccionados
                </button>
            </div>
        </div>
        @endif

        <div class="bg-white/80 dark:bg-gray-800/40 backdrop-blur-md border border-gray-200 dark:border-gray-700/50 shadow-xl dark:shadow-2xl dark:[box-shadow:0_10px_30px_-10px_var(--color-primary-glow)] overflow-hidden sm:rounded-3xl p-6 transition-colors duration-300">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div class="flex flex-col lg:flex-row gap-3 w-full sm:w-auto lg:items-center">
                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-3 w-full lg:w-auto">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight shrink-0">Catálogo</h3>
                        {{-- Buscador PERF-02: debounce 400ms para no disparar requests en cada tecla --}}
                        <div class="relative flex-1 sm:flex-none">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                            <input wire:model.live.debounce.400ms="search"
                                   type="text"
                                   placeholder="Buscar producto..."
                                   id="admin-product-search"
                                   class="pl-9 pr-9 py-2 text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-full text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all w-full sm:w-52">
                            
                            <div wire:loading.flex wire:target="search" class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center">
                                <svg class="animate-spin w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </div>
                            
                            <div wire:loading.remove wire:target="search" class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center">
                                @if($search)
                                    <button wire:click="$set('search', '')" class="text-gray-400 hover:text-gray-600 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    {{-- Filtros Rápidos (Categoría y Marca) --}}
                    <div class="flex items-center gap-2 w-full lg:w-auto">
                        <select wire:model.live="filter_category_id" class="flex-1 sm:w-40 py-1.5 px-3 text-xs font-medium bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[var(--color-primary)] transition-colors">
                            <option value="">Todas las Categorías</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="filter_brand_id" class="flex-1 sm:w-40 py-1.5 px-3 text-xs font-medium bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[var(--color-primary)] transition-colors">
                            <option value="">Todas las Marcas</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                            @endforeach
                        </select>
                        
                        @if(tenant('id') === 'g3' && cache('last_price_sync_at'))
                            <div class="hidden sm:flex items-center gap-1.5 text-[10px] text-gray-500 font-medium whitespace-nowrap bg-white/50 dark:bg-gray-800/50 px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm" title="Última actualización desde Google Sheets">
                                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Sync: {{ is_numeric(cache('last_price_sync_at')) ? \Carbon\Carbon::createFromTimestamp(cache('last_price_sync_at'))->timezone('America/Argentina/Buenos_Aires')->format('d/m H:i') : 'Actualizado' }}
                            </div>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 items-center">
                    
                    <button type="button" @click="massUpdateOpen = true" class="group flex items-center justify-center p-2.5 rounded-full transition-all hover:bg-gray-100 dark:hover:bg-gray-800 border border-slate-200 dark:border-gray-700 shadow-sm text-slate-700 dark:text-slate-200 font-bold">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <div class="grid grid-cols-[0fr] group-hover:grid-cols-[1fr] transition-all duration-300 ease-in-out">
                            <span class="overflow-hidden whitespace-nowrap text-sm"><span class="pl-2">Precios</span></span>
                        </div>
                    </button>

                    <button type="button" @click="brandListOpen = true" class="group flex items-center justify-center p-2.5 rounded-full transition-all hover:bg-gray-100 dark:hover:bg-gray-800 border border-slate-200 dark:border-gray-700 shadow-sm text-slate-700 dark:text-slate-200 font-bold">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                        <div class="grid grid-cols-[0fr] group-hover:grid-cols-[1fr] transition-all duration-300 ease-in-out">
                            <span class="overflow-hidden whitespace-nowrap text-sm"><span class="pl-2">Marcas</span></span>
                        </div>
                    </button>
                    
                    <button type="button" @click="catListOpen = true" class="group flex items-center justify-center p-2.5 rounded-full transition-all hover:bg-gray-100 dark:hover:bg-gray-800 border border-slate-200 dark:border-gray-700 shadow-sm text-slate-700 dark:text-slate-200 font-bold">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        <div class="grid grid-cols-[0fr] group-hover:grid-cols-[1fr] transition-all duration-300 ease-in-out">
                            <span class="overflow-hidden whitespace-nowrap text-sm"><span class="pl-2">Categorías</span></span>
                        </div>
                    </button>
                    
                    <button type="button" @click="isProductLoading = true; modalOpen = true; $wire.create().then(() => isProductLoading = false)" class="group flex items-center justify-center p-2.5 rounded-full transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5 text-white font-bold" style="background-color: var(--color-primary); box-shadow: 0 4px 14px 0 var(--color-primary-glow);">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        <div class="grid grid-cols-[0fr] group-hover:grid-cols-[1fr] transition-all duration-300 ease-in-out">
                            <span class="overflow-hidden whitespace-nowrap text-sm"><span class="pl-2 pr-1">Nuevo Producto</span></span>
                        </div>
                    </button>
                    
                    @if(tenant('id') === 'g3')
                    <!-- Botón de Sincronización Google Sheets -->
                    <button type="button" wire:click="syncPrices" class="group flex items-center justify-center p-2.5 rounded-full transition-all hover:bg-emerald-100 dark:hover:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700/50 shadow-sm text-emerald-700 dark:text-emerald-400 font-bold bg-emerald-50 dark:bg-emerald-900/10">
                        <svg wire:loading.remove wire:target="syncPrices" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <svg wire:loading wire:target="syncPrices" class="animate-spin w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <div class="grid grid-cols-[0fr] group-hover:grid-cols-[1fr] transition-all duration-300 ease-in-out">
                            <span class="overflow-hidden whitespace-nowrap text-sm"><span class="pl-2">Sincronizar Sheets</span></span>
                        </div>
                    </button>
                    @endif

                    <!-- Importar Excel Component -->
                    <livewire:admin.product-import />

                    <!-- Columnas Dropdown (Icon only) -->
                    <div class="relative hidden md:block">
                        <button @click="colDropdownOpen = !colDropdownOpen" @click.away="colDropdownOpen = false" class="group flex items-center justify-center p-2.5 rounded-full transition-all hover:bg-gray-100 dark:hover:bg-gray-800 border border-slate-200 dark:border-gray-700 shadow-sm text-slate-700 dark:text-slate-200 font-bold">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            <div class="grid grid-cols-[0fr] group-hover:grid-cols-[1fr] transition-all duration-300 ease-in-out">
                                <span class="overflow-hidden whitespace-nowrap text-sm"><span class="pl-2">Columnas</span></span>
                            </div>
                        </button>
                        <div x-show="colDropdownOpen" style="display: none;" class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 z-50 p-3">
                            <h4 class="text-xs font-bold text-gray-400 mb-2 uppercase tracking-wider">Mostrar/Ocultar</h4>
                            @foreach($visibleColumns as $key => $val)
                                <label class="flex items-center space-x-2 text-sm text-gray-700 dark:text-gray-300 mb-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 p-1 rounded">
                                    <input type="checkbox" wire:model.live="visibleColumns.{{ $key }}" class="rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                    <span class="capitalize">{{ $key === 'retail' ? 'Precio Lista' : ($key === 'wholesale' ? 'Mayorista' : ($key === 'cost' ? 'Costo' : $key)) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-gray-200 dark:border-gray-700/50 shadow-sm dark:shadow-none transition-colors hidden md:block">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700/50 text-left">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 transition-colors">
                        <tr>
                            <th class="px-6 py-4">
                                <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                            </th>
                            @if($visibleColumns['image'])
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Imagen</th>
                            @endif
                            
                            @if($visibleColumns['name'])
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none" wire:click="sortBy('name')">
                                Nombre @if($sortField === 'name') <span class="inline-block ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </th>
                            @endif

                            @if($visibleColumns['category'])
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none" wire:click="sortBy('category_name')">
                                Categoría @if($sortField === 'category_name') <span class="inline-block ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </th>
                            @endif

                            @if($visibleColumns['brand'])
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none" wire:click="sortBy('brand_name')">
                                Marca @if($sortField === 'brand_name') <span class="inline-block ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </th>
                            @endif

                            @if($visibleColumns['cost'])
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none" wire:click="sortBy('cost_price')">
                                Costo @if($sortField === 'cost_price') <span class="inline-block ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </th>
                            @endif

                            @if($visibleColumns['retail'])
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none" wire:click="sortBy('retail_price')">
                                Precio Lista @if($sortField === 'retail_price') <span class="inline-block ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </th>
                            @endif

                            @if($visibleColumns['wholesale'])
                            <th class="px-6 py-4 text-xs font-bold text-[var(--color-primary)] uppercase tracking-wider cursor-pointer select-none" wire:click="sortBy('wholesale_price')">
                                Mayorista @if($sortField === 'wholesale_price') <span class="inline-block ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </th>
                            @endif

                            @if($visibleColumns['stock'])
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none" wire:click="sortBy('stock')">
                                Stock @if($sortField === 'stock') <span class="inline-block ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </th>
                            @endif

                            <th class="px-6 py-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-transparent divide-y divide-gray-200 dark:divide-gray-700/50 transition-colors">
                        @foreach($products as $product)
                        <tr @click="if(!isProductLoading) { isProductLoading = true; modalOpen = true; $wire.edit({{ $product->id }}).then(() => isProductLoading = false) }" class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors cursor-pointer {{ in_array($product->id, $selectedProducts) ? 'bg-indigo-50/50 dark:bg-indigo-900/10' : '' }}">
                            <td class="px-6 py-4 whitespace-nowrap" @click.stop>
                                <input type="checkbox" value="{{ $product->id }}" wire:model.live="selectedProducts" class="rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                            </td>
                            
                            @if($visibleColumns['image'])
                            <td class="px-6 py-4 whitespace-nowrap" @click.stop>
                                @if($product->image_url)
                                    <img @click="previewImageUrl = '{{ asset('storage/' . $product->image_url) }}'; previewImageOpen = true" src="{{ asset('storage/' . $product->image_url) }}" class="h-12 w-12 rounded-lg object-cover border border-gray-200 dark:border-gray-600 shadow-sm cursor-pointer hover:opacity-80 transition-opacity" title="Haz clic para agrandar">
                                @else
                                    <div class="h-12 w-12 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-gray-400 dark:text-gray-500 text-[10px] uppercase font-bold tracking-tighter">Sin img</div>
                                @endif
                            </td>
                            @endif

                            @if($visibleColumns['name'])
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-gray-200">{{ $product->name }}</td>
                            @endif

                            @if($visibleColumns['category'])
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                    {{ $product->category ? $product->category->name : 'Sin categoría' }}
                                </span>
                            </td>
                            @endif

                            @if($visibleColumns['brand'])
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                    {{ $product->brands->pluck('name')->join(', ') ?: 'N/A' }}
                                </span>
                            </td>
                            @endif

                            @if($visibleColumns['cost'])
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${{ number_format($product->cost_price, 2) }}</td>
                            @endif

                            @if($visibleColumns['retail'])
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${{ number_format($product->retail_price, 2) }}</td>
                            @endif

                            @if($visibleColumns['wholesale'])
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-[var(--color-primary)]">${{ number_format($product->wholesale_price, 2) }}</td>
                            @endif

                            @if($visibleColumns['stock'])
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($product->stock > 0)
                                    <span class="bg-green-100 dark:bg-green-500/20 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-500/30 px-3 py-1 rounded-full text-xs font-bold shadow-sm dark:shadow-none">{{ $product->stock }} un.</span>
                                @else
                                    <span class="bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-500/30 px-3 py-1 rounded-full text-xs font-bold shadow-sm dark:shadow-none">Agotado</span>
                                @endif
                            </td>
                            @endif

                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right space-x-2" @click.stop>
                                <button @click="if(!isProductLoading) { isProductLoading = true; modalOpen = true; $wire.edit({{ $product->id }}).then(() => isProductLoading = false) }" type="button" title="Editar" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors p-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>
                                <button wire:click="delete({{ $product->id }})" wire:confirm="¿Estás seguro de que deseas eliminar este producto?" title="Eliminar" class="text-red-600 dark:text-red-500 hover:text-red-800 dark:hover:text-red-400 transition-colors p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/30">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    </tbody>
                </table>
            </div>

            <!-- Vista Móvil para Productos (Tarjetas) -->
            <div class="block md:hidden space-y-2.5">
                @foreach($products as $product)
                <div 
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-2.5 shadow-sm relative cursor-pointer active:bg-gray-50 dark:active:bg-gray-700/50 transition-colors {{ in_array($product->id, $selectedProducts) ? 'ring-2 ring-[var(--color-primary)]' : '' }}"
                    @click="if(!isProductLoading) { isProductLoading = true; modalOpen = true; $wire.edit({{ $product->id }}).then(() => isProductLoading = false) }"
                >
                    <div class="flex items-center gap-2.5">
                        <!-- Izquierda: Checkbox + Imagen -->
                        <div class="flex items-center gap-2 shrink-0" @click.stop>
                            <input type="checkbox" value="{{ $product->id }}" wire:model.live="selectedProducts" class="rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)] w-4 h-4">
                            @if($product->image_url)
                                <img src="{{ asset('storage/' . $product->image_url) }}" class="h-10 w-10 rounded-lg object-cover border border-gray-200 dark:border-gray-600 cursor-pointer" @click.stop="previewImageUrl = '{{ asset('storage/' . $product->image_url) }}'; previewImageOpen = true">
                            @else
                                <div class="h-10 w-10 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-gray-400 dark:text-gray-500 text-[8px] uppercase font-bold tracking-tighter shrink-0">Sin img</div>
                            @endif
                        </div>
                        
                        <!-- Derecha: Contenido principal en 2 renglones -->
                        <div class="flex-1 min-w-0 flex flex-col justify-center">
                            <!-- Renglón 1: Título (+ Cat/Marca) y Stock -->
                            <div class="flex justify-between items-start mb-0.5">
                                <h4 class="text-xs font-bold text-gray-900 dark:text-white truncate pr-2">
                                    {{ $product->name }} 
                                    @if($product->category || $product->brands->count() > 0)
                                        <span class="text-[10px] font-normal text-gray-500 dark:text-gray-400 ml-0.5">
                                            ({{ $product->category ? $product->category->name : 'Sin cat.' }}{{ $product->brands->count() > 0 ? ' - ' . $product->brands->pluck('name')->join(', ') : '' }})
                                        </span>
                                    @endif
                                </h4>
                                <div class="text-[10px] font-bold shrink-0 mt-0.5 {{ $product->stock > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $product->stock > 0 ? $product->stock . ' un.' : 'Agotado' }}
                                </div>
                            </div>
                            
                            <!-- Renglón 2: Precio y Botón eliminar -->
                            <div class="flex justify-between items-center">
                                <div class="flex items-baseline gap-1.5">
                                    <div class="text-[10px] font-medium text-gray-400">${{ number_format($product->retail_price, 2) }}</div>
                                    <div class="text-sm font-black text-[var(--color-primary)]">${{ number_format($product->wholesale_price, 2) }}</div>
                                </div>
                                <div @click.stop>
                                    <button wire:click="delete({{ $product->id }})" wire:confirm="¿Seguro de eliminar?" title="Eliminar" class="p-1 text-gray-300 dark:text-gray-600 hover:text-red-500 dark:hover:text-red-400 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            
            {{-- Paginación PERF-02 --}}
            @if($products->hasPages())
            <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4 sm:gap-0">
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center sm:text-left">
                    Mostrando {{ $products->firstItem() }}–{{ $products->lastItem() }}
                    de <span class="font-bold">{{ $products->total() }}</span> productos
                </p>
                <div class="flex items-center gap-1">
                    {{-- Anterior --}}
                    @if($products->onFirstPage())
                        <span class="px-3 py-1.5 rounded-lg text-sm text-gray-300 dark:text-gray-600 border border-gray-200 dark:border-gray-700 cursor-not-allowed whitespace-nowrap">‹ Ant.</span>
                    @else
                        <button wire:click="previousPage" class="px-3 py-1.5 rounded-lg text-sm text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors whitespace-nowrap">‹ Ant.</button>
                    @endif

                    {{-- Página actual --}}
                    <span class="px-3 py-1.5 rounded-lg text-sm font-bold text-white whitespace-nowrap" style="background-color: var(--color-primary);">
                        {{ $products->currentPage() }} / {{ $products->lastPage() }}
                    </span>

                    {{-- Siguiente --}}
                    @if($products->hasMorePages())
                        <button wire:click="nextPage" class="px-3 py-1.5 rounded-lg text-sm text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors whitespace-nowrap">Sig. ›</button>
                    @else
                        <span class="px-3 py-1.5 rounded-lg text-sm text-gray-300 dark:text-gray-600 border border-gray-200 dark:border-gray-700 cursor-not-allowed whitespace-nowrap">Sig. ›</span>
                    @endif
                </div>
            </div>
            @else
            <p class="mt-4 text-xs text-gray-400 dark:text-gray-600 text-right">
                {{ $products->total() }} {{ $products->total() === 1 ? 'producto' : 'productos' }} en total
            </p>
            @endif
        </div>
    </div>

    <!-- Modal with Alpine Transitions -->
    <div x-show="modalOpen" class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-cloak>
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
            <!-- Overlay -->
            <div x-show="modalOpen" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 bg-gray-900/40 dark:bg-[#0b0f19]/80 backdrop-blur-sm transition-opacity" aria-hidden="true" wire:click="$set('showModal', false)"></div>
            
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <!-- Modal Content -->
            <div x-show="modalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-3xl text-left overflow-visible shadow-2xl dark:[box-shadow:0_25px_50px_-12px_var(--color-primary-glow)] transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full relative">
                
                <!-- Overlay de carga premium -->
                <div x-show="isProductLoading" x-transition.opacity.duration.200ms class="absolute inset-0 z-50 flex items-center justify-center bg-white/40 dark:bg-gray-900/40 backdrop-blur-sm rounded-3xl" style="display: none;">
                    <div class="bg-white dark:bg-gray-800 px-6 py-5 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 flex flex-col items-center transform transition-all animate-pulse-once">
                        <div class="relative w-10 h-10 mb-3">
                            <div class="absolute inset-0 rounded-full border-t-2 border-[var(--color-primary)] animate-spin"></div>
                            <div class="absolute inset-2 rounded-full border-r-2 border-[var(--color-primary)] opacity-50 animate-spin" style="animation-direction: reverse; animation-duration: 1.5s;"></div>
                        </div>
                        <span class="text-sm font-bold bg-clip-text text-transparent bg-gradient-to-r from-[var(--color-primary)] to-blue-600 tracking-wide uppercase text-[10px]">Actualizando...</span>
                    </div>
                </div>

                <div class="px-4 sm:px-8 pt-6 sm:pt-8 pb-4">
                    <h3 class="text-xl sm:text-2xl leading-6 font-bold text-gray-900 dark:text-white mb-6 sm:mb-8 tracking-tight">
                        {{ $product_id ? 'Editar Producto' : 'Nuevo Producto' }}
                    </h3>
                    <form wire:submit="save">
                        <!-- Primera Fila: Nombre y SKU -->
                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 sm:gap-5 mb-4">
                            <div class="col-span-2 sm:col-span-3">
                                <label class="block text-gray-700 dark:text-gray-400 text-[10px] sm:text-xs font-bold mb-1.5 uppercase tracking-wider">Nombre</label>
                                <input wire:model="name" type="text" class="w-full py-2 px-3 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-colors">
                                @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-span-1">
                                <label class="block text-gray-700 dark:text-gray-400 text-[10px] sm:text-xs font-bold mb-1.5 uppercase tracking-wider truncate">SKU / Cód.</label>
                                <input wire:model="sku" type="text" class="w-full py-2 px-2 sm:px-3 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-colors" placeholder="RM-304">
                                @error('sku') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Segunda Fila: Categoría, Marca y Stock -->
                        <div class="grid grid-cols-3 gap-3 sm:gap-5 mb-5">
                            <!-- Categoría -->
                            <div class="col-span-1">
                                <label class="flex justify-between items-center text-gray-700 dark:text-gray-400 text-[10px] sm:text-xs font-bold mb-1.5 uppercase tracking-wider">
                                    <span>Categoría</span>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="catListOpen = true" class="text-gray-400 hover:text-[var(--color-primary)] transition-colors" title="Gestionar Categorías">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        </button>
                                        <div x-data="{ openCatPrompt: false, newCatName: '' }" @click.outside="openCatPrompt = false" class="relative">
                                            <button type="button" @click="openCatPrompt = !openCatPrompt; if(openCatPrompt) $nextTick(() => $refs.catInput.focus())" class="text-[var(--color-primary)] hover:text-blue-600 transition-colors" title="Crear nueva categoría">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                            </button>
                                            <div x-show="openCatPrompt" style="display:none;" x-transition class="absolute z-50 left-0 sm:left-auto sm:right-0 top-full mt-2 w-72 max-w-[calc(100vw-2rem)] bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-3">
                                                <span class="block text-xs text-gray-500 dark:text-gray-400 mb-2 normal-case font-normal">Nombre de la nueva categoría:</span>
                                                <div class="flex gap-2">
                                                    <input x-ref="catInput" x-model="newCatName" @keydown.enter.prevent="$wire.createCategoryQuick(newCatName); openCatPrompt = false; newCatName = ''" type="text" class="w-full text-sm py-1.5 px-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)]">
                                                    <button type="button" @click="if(newCatName) { $wire.createCategoryQuick(newCatName); openCatPrompt = false; newCatName = ''; }" class="bg-[var(--color-primary)] text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:opacity-90">Crear</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                                <select wire:model="category_id" class="w-full py-2 px-1 sm:px-3 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-colors h-[38px]">
                                    <option value="">Categoría</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id') <span class="text-red-500 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Marca -->
                            <div class="col-span-2 sm:col-span-1">
                                <label class="flex justify-between items-center text-gray-700 dark:text-gray-400 text-[10px] sm:text-xs font-bold mb-1.5 uppercase tracking-wider">
                                    <span>Marca</span>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="brandListOpen = true" class="text-gray-400 hover:text-[var(--color-primary)] transition-colors" title="Gestionar Marcas">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        </button>
                                        <div x-data="{ openBrandPrompt: false, newBrandName: '' }" @click.outside="openBrandPrompt = false" class="relative">
                                            <button type="button" @click="openBrandPrompt = !openBrandPrompt; if(openBrandPrompt) $nextTick(() => $refs.brandInput.focus())" class="text-[var(--color-primary)] hover:text-blue-600 transition-colors" title="Crear nueva marca">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                            </button>
                                            <div x-show="openBrandPrompt" style="display:none;" x-transition class="absolute z-50 left-0 sm:left-auto sm:right-0 top-full mt-2 w-72 max-w-[calc(100vw-2rem)] bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-3">
                                                <span class="block text-xs text-gray-500 dark:text-gray-400 mb-2 normal-case font-normal">Nombre de la nueva marca:</span>
                                                <div class="flex gap-2">
                                                    <input x-ref="brandInput" x-model="newBrandName" @keydown.enter.prevent="$wire.createBrandQuick(newBrandName); openBrandPrompt = false; newBrandName = ''" type="text" class="w-full text-sm py-1.5 px-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)]">
                                                    <button type="button" @click="if(newBrandName) { $wire.createBrandQuick(newBrandName); openBrandPrompt = false; newBrandName = ''; }" class="bg-[var(--color-primary)] text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:opacity-90">Crear</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                                
                                <div class="relative" x-data="{
                                    open: false,
                                    search: '',
                                    options: [
                                        @foreach($brands as $brand)
                                            { id: '{{ $brand->id }}', name: '{{ addslashes($brand->name) }}' },
                                        @endforeach
                                    ],
                                    selected: @entangle('brand_ids').live,
                                    get filteredOptions() {
                                        if (this.search === '') return this.options;
                                        return this.options.filter(i => i.name.toLowerCase().includes(this.search.toLowerCase()));
                                    },
                                    toggle(id) {
                                        id = String(id);
                                        let idx = this.selected.findIndex(i => String(i) === id);
                                        if (idx !== -1) {
                                            this.selected.splice(idx, 1);
                                        } else {
                                            this.selected.push(id);
                                        }
                                    },
                                    getSelectedNames() {
                                        if (!this.selected || this.selected.length === 0) return [];
                                        return this.selected.map(id => {
                                            let opt = this.options.find(o => String(o.id) === String(id));
                                            return opt ? opt.name : '';
                                        }).filter(Boolean);
                                    }
                                }" @click.outside="open = false; search = ''">
                                    <div @click="open = !open" class="w-full min-h-[38px] py-1 px-2 sm:px-3 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg cursor-pointer flex flex-wrap items-center gap-1.5 transition-colors focus-within:ring-2 focus-within:ring-[var(--color-primary)] focus-within:border-transparent">
                                        <template x-if="!selected || selected.length === 0">
                                            <span class="text-gray-500 py-1 text-sm">Marcas...</span>
                                        </template>
                                        <template x-for="name in getSelectedNames()" :key="name">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700">
                                                <span x-text="name"></span>
                                            </span>
                                        </template>
                                        <div class="ml-auto">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                        </div>
                                    </div>
                                    
                                    <div x-show="open" style="display:none;" x-transition class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg flex flex-col max-h-60">
                                        <div class="p-2 border-b border-gray-100 dark:border-gray-800">
                                            <input type="text" x-model="search" placeholder="Buscar marca..." class="w-full py-1.5 px-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]" @click.stop>
                                        </div>
                                        <ul class="py-1 overflow-y-auto flex-1">
                                            <template x-if="filteredOptions.length === 0">
                                                <li class="px-4 py-3 text-sm text-gray-500 text-center">No se encontraron marcas.</li>
                                            </template>
                                            <template x-for="option in filteredOptions" :key="option.id">
                                                <li>
                                                    <label class="flex items-center px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                                                        <input type="checkbox" class="rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                                                               :checked="selected.map(String).includes(String(option.id))"
                                                               @change="toggle(option.id)">
                                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300" x-text="option.name"></span>
                                                    </label>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>
                                @error('brand_ids') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Stock -->
                            <div class="col-span-3 sm:col-span-1 grid grid-cols-2 gap-3 mt-2 sm:mt-0">
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-400 text-[10px] font-bold mb-1.5 uppercase tracking-wider">Stock Actual</label>
                                    <input wire:model="stock" type="number" class="w-full py-2 px-3 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-colors h-[38px]" placeholder="0">
                                    @error('stock') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-400 text-[10px] font-bold mb-1.5 uppercase tracking-wider">Stock Mínimo</label>
                                    <input wire:model="min_stock" type="number" class="w-full py-2 px-3 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-colors h-[38px]" placeholder="2">
                                    @error('min_stock') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-3 sm:p-5 mb-4 sm:mb-5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700/60 shadow-sm">
                            <h4 class="text-[10px] sm:text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3 border-b border-gray-200 dark:border-gray-700 pb-2">Calculadora Inteligente de Precios</h4>
                            
                            <div class="grid grid-cols-3 gap-2 sm:gap-5 mb-2">
                                <!-- Costo -->
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-400 text-[9px] sm:text-xs font-bold mb-1 uppercase tracking-wider truncate" title="Costo">Costo</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-1.5 sm:pl-2.5 flex items-center pointer-events-none">
                                            <span class="text-gray-500 font-bold text-xs sm:text-sm">$</span>
                                        </div>
                                        <input wire:model.live.debounce.300ms="cost_price" type="number" step="0.01" class="w-full py-2 pl-4 sm:pl-6 pr-1 sm:pr-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-xs sm:text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)]">
                                    </div>
                                    @error('cost_price') <span class="text-red-500 text-[9px] sm:text-[10px]">{{ $message }}</span> @enderror
                                </div>
                                <!-- Margen -->
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-400 text-[9px] sm:text-xs font-bold mb-1 uppercase tracking-wider truncate" title="Margen">Margen</label>
                                    <div class="relative">
                                        <input wire:model.live.debounce.300ms="profit_margin" type="number" class="w-full py-2 pl-2 sm:pl-3 pr-4 sm:pr-6 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-xs sm:text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)]">
                                        <div class="absolute inset-y-0 right-0 pr-1.5 sm:pr-2 flex items-center pointer-events-none">
                                            <span class="text-gray-500 font-bold text-xs sm:text-sm">%</span>
                                        </div>
                                    </div>
                                    @error('profit_margin') <span class="text-red-500 text-[9px] sm:text-[10px]">{{ $message }}</span> @enderror
                                </div>
                                
                                <!-- Precio Lista -->
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-400 text-[9px] sm:text-xs font-bold mb-1 uppercase tracking-wider truncate" title="Precio Final Lista">P. Lista</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-1.5 sm:pl-2.5 flex items-center pointer-events-none">
                                            <span class="text-gray-500 font-bold text-xs sm:text-sm">$</span>
                                        </div>
                                        <input wire:model.live.debounce.500ms="retail_price" type="number" step="0.01" class="w-full py-2 pl-4 sm:pl-6 pr-1 sm:pr-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-xs sm:text-sm text-gray-900 dark:text-white font-bold focus:ring-2 focus:ring-[var(--color-primary)]">
                                    </div>
                                    @error('retail_price') <span class="text-red-500 text-[9px] sm:text-[10px]">{{ $message }}</span> @enderror
                                </div>

                                <!-- Llevando -->
                                <div>
                                    <label class="block text-[var(--color-primary)] text-[9px] sm:text-xs font-bold mb-1 uppercase tracking-wider truncate" title="Llevando (Cant.)">Cant. M.</label>
                                    <input wire:model="wholesale_min_quantity" type="number" min="1" class="w-full py-2 px-2 sm:px-3 bg-white dark:bg-gray-900 border border-[var(--color-primary)]/40 rounded-lg text-xs sm:text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)]">
                                    @error('wholesale_min_quantity') <span class="text-red-500 text-[9px] sm:text-[10px]">{{ $message }}</span> @enderror
                                </div>
                                <!-- Desc. Mayorista -->
                                <div>
                                    <label class="block text-[var(--color-primary)] text-[9px] sm:text-xs font-bold mb-1 uppercase tracking-wider truncate" title="Desc. Mayorista">Desc. M.</label>
                                    <div class="relative">
                                        <input wire:model.live.debounce.300ms="wholesale_discount" type="number" class="w-full py-2 pl-2 sm:pl-3 pr-4 sm:pr-6 bg-white dark:bg-gray-900 border border-[var(--color-primary)]/40 rounded-lg text-xs sm:text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)]">
                                        <div class="absolute inset-y-0 right-0 pr-1.5 sm:pr-2 flex items-center pointer-events-none">
                                            <span class="text-[var(--color-primary)] opacity-70 font-bold text-xs sm:text-sm">%</span>
                                        </div>
                                    </div>
                                    @error('wholesale_discount') <span class="text-red-500 text-[9px] sm:text-[10px]">{{ $message }}</span> @enderror
                                </div>

                                <!-- Precio Mayorista -->
                                <div>
                                    <label class="block text-[var(--color-primary)] text-[9px] sm:text-xs font-bold mb-1 uppercase tracking-wider truncate" title="Precio Final Mayorista">P. Mayorista</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-1.5 sm:pl-2.5 flex items-center pointer-events-none">
                                            <span class="text-[var(--color-primary)] opacity-70 font-bold text-xs sm:text-sm">$</span>
                                        </div>
                                        <input wire:model.live.debounce.500ms="wholesale_price" type="number" step="0.01" class="w-full py-2 pl-4 sm:pl-6 pr-1 sm:pr-2 bg-blue-50/50 dark:bg-gray-900 border border-[var(--color-primary)]/40 rounded-lg text-xs sm:text-sm text-[var(--color-primary)] font-bold focus:ring-2 focus:ring-[var(--color-primary)]">
                                    </div>
                                    @error('wholesale_price') <span class="text-red-500 text-[9px] sm:text-[10px]">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>


                        <div class="mb-4" x-data="{ expandedImage: false }">
                            <label class="block text-gray-700 dark:text-gray-400 text-[10px] sm:text-xs font-bold mb-2 uppercase tracking-wider">Imagen del Producto</label>
                            
                            <div class="flex flex-col sm:flex-row gap-3">
                                @if($current_image_url)
                                    <div class="w-full sm:w-auto p-2 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700 transition-all duration-300 shrink-0">
                                        <!-- Imagen expandida (al hacer click) -->
                                        <div x-show="expandedImage" x-transition class="mb-2 cursor-pointer" @click="expandedImage = false" title="Clic para reducir">
                                            <img src="{{ asset('storage/' . $current_image_url) }}" alt="Imagen actual ampliada" class="w-full max-h-64 object-contain rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 shadow-md">
                                            <p class="text-center text-[10px] text-gray-400 mt-1">Tocá para reducir</p>
                                        </div>

                                        <!-- Fila normal con miniatura + borrar -->
                                        <div x-show="!expandedImage" x-transition class="flex items-center space-x-3">
                                            <div class="relative group cursor-pointer shrink-0" @click="expandedImage = true" title="Clic para agrandar">
                                                <img src="{{ asset('storage/' . $current_image_url) }}" alt="Imagen actual" class="h-14 w-14 sm:h-16 sm:w-16 object-cover rounded-lg border border-gray-200 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-900 hover:opacity-80 transition-opacity">
                                                <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 group-active:opacity-100 transition-opacity bg-black/10 rounded-lg">
                                                    <svg class="w-5 h-5 text-white drop-shadow-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                                                </div>
                                            </div>
                                            <div class="flex flex-col flex-1">
                                                <h5 class="text-[10px] sm:text-xs font-bold text-gray-900 dark:text-white mb-0.5">Imagen Actual</h5>
                                                <button type="button" wire:click="removeImage" class="inline-flex items-center text-[10px] font-bold text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-2 py-1 rounded transition-colors w-max mt-1">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    Borrar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="relative group flex-1 w-full" x-data="{ isDragging: false }" @dragover.prevent="isDragging = true" @dragleave.prevent="isDragging = false" @drop.prevent="isDragging = false; if($event.dataTransfer.files.length) $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }))">
                                    <div class="grid grid-cols-2 gap-2 sm:block h-[76px] sm:h-[84px]">
                                        <!-- Galería / Drop Zone (Desktop) -->
                                        <label class="flex flex-col items-center justify-center w-full h-full px-2 transition-all bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 sm:border-2 sm:border-dashed rounded-xl cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/80 hover:border-[var(--color-primary)]"
                                               :class="isDragging ? 'border-[var(--color-primary)] bg-blue-50/50 dark:bg-blue-900/10' : ''">
                                            
                                            <div class="flex flex-col items-center justify-center text-center">
                                                <div wire:loading.remove wire:target="image" class="text-[var(--color-primary)] mb-1">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                </div>
                                                <div wire:loading wire:target="image" class="mb-1 text-[var(--color-primary)]">
                                                    <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                </div>
                                                <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 font-medium leading-tight">
                                                    <span class="text-[var(--color-primary)] font-bold sm:hidden">Galería</span>
                                                    <span class="hidden sm:inline"><span class="text-[var(--color-primary)] font-bold">Subir</span> o soltar img.</span>
                                                </p>
                                            </div>
                                            <input x-ref="fileInput" wire:model="image" type="file" accept="image/*" class="hidden" />
                                        </label>
                                        
                                        <!-- Cámara Absolute Button (Desktop Only) -->
                                        <label class="hidden sm:flex absolute bottom-1 right-1 cursor-pointer shrink-0 items-center justify-center p-1.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-all shadow-sm" title="Cámara">
                                            <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                            <input wire:model="image" type="file" accept="image/*" capture="environment" class="hidden">
                                        </label>

                                        <!-- Cámara Full Button (Mobile Only) -->
                                        <label class="sm:hidden flex flex-col items-center justify-center w-full h-full px-2 transition-all bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/80 hover:border-[var(--color-primary)]">
                                            <div class="flex flex-col items-center justify-center text-center">
                                                <div wire:loading.remove wire:target="image" class="text-gray-600 dark:text-gray-400 mb-1">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                </div>
                                                <div wire:loading wire:target="image" class="mb-1 text-gray-600 dark:text-gray-400">
                                                    <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                </div>
                                                <p class="text-[10px] text-gray-600 dark:text-gray-400 font-bold leading-tight">Cámara</p>
                                            </div>
                                            <input wire:model="image" type="file" accept="image/*" capture="environment" class="hidden">
                                        </label>
                                    </div>
                                </div>
                            </div>
                            @error('image') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-row items-center justify-center gap-2 sm:gap-3 bg-gray-50 dark:bg-gray-900/50 -mx-4 sm:-mx-8 -mb-4 px-4 sm:px-8 py-3 sm:py-4 border-t border-gray-200 dark:border-gray-800">
                            <button type="button" wire:click="$set('showModal', false)" class="flex-1 sm:w-auto sm:flex-none text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-bold py-2 sm:py-2.5 px-3 sm:px-5 rounded-lg sm:rounded-full transition-colors border border-gray-200 bg-white sm:border-transparent text-sm">Cancelar</button>
                            <button type="submit" class="flex-1 sm:w-auto sm:flex-none text-white font-bold py-2 sm:py-2.5 px-3 sm:px-8 rounded-lg sm:rounded-full shadow-md hover:shadow-lg transition-all duration-300 text-sm" style="background-color: var(--color-primary);">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    


    <!-- Modal Actualización Masiva de Precios -->
    <div x-show="massUpdateOpen" class="fixed z-50 inset-0 overflow-y-auto" style="z-index: 60;" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-cloak>
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
            <div x-show="massUpdateOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-900/60 dark:bg-[#0b0f19]/90 backdrop-blur-md transition-opacity" @click="massUpdateOpen = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div x-show="massUpdateOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full">
                <div class="px-4 sm:px-8 pt-6 sm:pt-8 pb-4">
                    <h3 class="text-xl sm:text-2xl leading-6 font-bold text-gray-900 dark:text-white mb-6 tracking-tight flex items-center">
                        💸 Actualización Masiva
                    </h3>
                    
                    <form wire:submit="applyMassUpdate">
                        
                        <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 p-4 rounded-xl mb-6">
                            <label class="block text-indigo-800 dark:text-indigo-300 text-xs font-bold mb-2 uppercase">Aplicar a:</label>
                            <select wire:model.live="massTarget" class="w-full py-2.5 px-3 bg-white dark:bg-gray-800 border border-indigo-200 dark:border-indigo-700 rounded-lg text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 mb-3">
                                <option value="selected">Productos Seleccionados ({{ count($selectedProducts) }})</option>
                                <option value="category">Todos los productos de una Categoría</option>
                                <option value="brand">Todos los productos de una Marca</option>
                            </select>

                            @if($massTarget === 'category')
                                <select wire:model="massCategoryId" class="w-full py-2.5 px-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                                    <option value="">Seleccione Categoría...</option>
                                    @foreach($categories as $cat) <option value="{{ $cat->id }}">{{ $cat->name }}</option> @endforeach
                                </select>
                            @endif

                            @if($massTarget === 'brand')
                                <select wire:model="massBrandId" class="w-full py-2.5 px-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                                    <option value="">Seleccione Marca...</option>
                                    @foreach($brands as $brand) <option value="{{ $brand->id }}">{{ $brand->name }}</option> @endforeach
                                </select>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-gray-700 dark:text-gray-400 text-xs font-bold mb-2 uppercase">Acción</label>
                                <select wire:model="massType" class="w-full py-3 px-3 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white">
                                    <option value="increase">Aumento (+)</option>
                                    <option value="decrease">Descuento (-)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-700 dark:text-gray-400 text-xs font-bold mb-2 uppercase">Tipo de valor</label>
                                <select wire:model="massValueType" class="w-full py-3 px-3 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white">
                                    <option value="percent">Porcentaje (%)</option>
                                    <option value="fixed">Monto Fijo ($)</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-[var(--color-primary)] text-xs font-bold mb-2 uppercase">Valor (Número)</label>
                                <input wire:model="massValue" type="number" step="0.01" min="0" class="w-full py-3 px-4 bg-blue-50/50 dark:bg-gray-900 border border-[var(--color-primary)]/40 rounded-xl text-gray-900 dark:text-white font-bold focus:ring-2 focus:ring-[var(--color-primary)]">
                                @error('massValue') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 dark:text-gray-400 text-xs font-bold mb-2 uppercase">Campo a Modificar</label>
                                <select wire:model="massField" class="w-full py-3 px-3 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white font-bold">
                                    <option value="cost_price">Precio de Costo</option>
                                    <option value="retail_price">Precio Lista</option>
                                    <option value="wholesale_price">Precio Mayorista</option>
                                </select>
                            </div>
                        </div>

                        <div class="bg-gray-100 dark:bg-gray-800 p-4 rounded-xl mb-6 border border-gray-200 dark:border-gray-700">
                            <label class="flex items-start space-x-3 cursor-pointer">
                                <input type="checkbox" wire:model="massOverride" class="mt-1 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                <div>
                                    <span class="block text-sm font-bold text-gray-900 dark:text-gray-100">Forzar actualización absoluta</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400 mt-1">Si se desmarca (por defecto), al aumentar el "Costo", los precios de venta se recalcularán automáticamente para mantener los márgenes de ganancia. Si se marca, NO se recalcularán y solo se afectará el campo seleccionado.</span>
                                </div>
                            </label>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center justify-end gap-3 bg-gray-50 dark:bg-gray-900/50 -mx-4 sm:-mx-8 -mb-4 px-4 sm:px-8 py-4 sm:py-5 border-t border-gray-200 dark:border-gray-800">
                            <button type="button" @click="massUpdateOpen = false" class="w-full sm:w-auto text-gray-600 dark:text-gray-400 font-bold py-2.5 px-5 border border-gray-200 sm:border-transparent rounded-full hover:text-gray-900 dark:hover:text-white transition-colors">Cancelar</button>
                            <button type="submit" class="w-full sm:w-auto text-white font-bold py-2.5 px-8 rounded-full shadow-lg bg-indigo-600 hover:bg-indigo-700 transition-all">🚀 Aplicar Actualización</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Brand Management Modal -->
    <div x-show="brandListOpen" class="fixed z-50 inset-0 overflow-y-auto" style="z-index: 70;" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-cloak>
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
            <div x-show="brandListOpen" 
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 bg-gray-900/40 dark:bg-[#0b0f19]/80 backdrop-blur-sm transition-opacity" 
                 @click="brandListOpen = false" aria-hidden="true"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="brandListOpen" 
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 class="inline-block align-bottom bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full relative">
                
                <div class="px-4 sm:px-8 pt-6 sm:pt-8 pb-6 sm:pb-8">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center gap-4">
                            <h3 class="text-xl sm:text-2xl leading-6 font-bold text-gray-900 dark:text-white tracking-tight">Gestión de Marcas</h3>
                            <button wire:click="$set('manageBrandId', 'new'); $set('manageBrandName', '')" class="bg-[var(--color-primary)]/10 text-[var(--color-primary)] hover:bg-[var(--color-primary)]/20 px-3 py-1.5 rounded-lg text-sm font-bold transition-colors">➕ Nueva Marca</button>
                        </div>
                        <button @click="brandListOpen = false" class="text-gray-400 hover:text-gray-500 transition-colors focus:outline-none">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="overflow-x-auto bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 max-h-[60vh] overflow-y-auto mb-4">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-900 sticky top-0 z-10">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nombre</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Productos</th>
                                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                @if($manageBrandId === 'new')
                                <tr class="bg-green-50/50 dark:bg-green-900/10">
                                    <td class="px-6 py-3" colspan="2">
                                        <input wire:model="manageBrandName" type="text" class="w-full py-2 px-3 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[var(--color-primary)] dark:bg-gray-900 dark:text-white" @keydown.enter="$wire.saveBrand()" placeholder="Nombre de la nueva marca">
                                        @error('manageBrandName') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <button wire:click="saveBrand" class="text-white bg-green-500 hover:bg-green-600 font-bold py-1.5 px-3 rounded text-xs mr-2 transition-colors">Guardar</button>
                                        <button wire:click="$set('manageBrandId', null)" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 text-xs font-bold transition-colors">Cancelar</button>
                                    </td>
                                </tr>
                                @endif
                                @foreach($brands as $brand)
                                    @if($manageBrandId === $brand->id)
                                    <tr class="bg-blue-50/50 dark:bg-blue-900/10">
                                        <td class="px-6 py-3" colspan="2">
                                            <input wire:model="manageBrandName" type="text" class="w-full py-2 px-3 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[var(--color-primary)] dark:bg-gray-900 dark:text-white" @keydown.enter="$wire.saveBrand()" placeholder="Nombre de la marca">
                                            @error('manageBrandName') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <button wire:click="saveBrand" class="text-white bg-green-500 hover:bg-green-600 font-bold py-1.5 px-3 rounded text-xs mr-2 transition-colors">Guardar</button>
                                            <button wire:click="$set('manageBrandId', null)" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 text-xs font-bold transition-colors">Cancelar</button>
                                        </td>
                                    </tr>
                                    @else
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white">{{ $brand->name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-bold text-gray-500 dark:text-gray-400">
                                            <span class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-full">{{ $brand->products()->count() }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex justify-end items-center gap-2 sm:gap-4">
                                                <button wire:click="editBrand({{ $brand->id }})" title="Editar" class="text-[var(--color-primary)] hover:bg-[var(--color-primary)]/10 p-1.5 sm:p-0 rounded-lg font-bold transition-colors flex items-center">
                                                    <span class="hidden sm:inline">Editar</span>
                                                    <svg class="w-5 h-5 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                </button>
                                                <button wire:click="deleteBrand({{ $brand->id }})" wire:confirm="¿Eliminar la marca {{ $brand->name }}? Esta acción no se puede deshacer." title="Eliminar" class="text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 p-1.5 sm:p-0 rounded-lg font-bold transition-colors flex items-center">
                                                    <span class="hidden sm:inline">Eliminar</span>
                                                    <svg class="w-5 h-5 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endif
                                @endforeach
                                @if(count($brands) === 0)
                                    <tr>
                                        <td colspan="3" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">No hay marcas creadas aún. Usá el botón + en el formulario para crear una.</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Management Modal -->
    <div x-show="catListOpen" class="fixed z-50 inset-0 overflow-y-auto" style="z-index: 70;" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-cloak>
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
            <div x-show="catListOpen" 
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 bg-gray-900/40 dark:bg-[#0b0f19]/80 backdrop-blur-sm transition-opacity" 
                 @click="catListOpen = false" aria-hidden="true"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="catListOpen" 
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 class="inline-block align-bottom bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full relative">
                
                <div class="px-4 sm:px-8 pt-6 sm:pt-8 pb-6 sm:pb-8">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center gap-4">
                            <h3 class="text-xl sm:text-2xl leading-6 font-bold text-gray-900 dark:text-white tracking-tight">Gestión Categorías</h3>
                            <button wire:click="$set('manageCategoryId', 'new'); $set('manageCategoryName', '')" class="bg-[var(--color-primary)]/10 text-[var(--color-primary)] hover:bg-[var(--color-primary)]/20 px-3 py-1.5 rounded-lg text-sm font-bold transition-colors">➕ Nueva Categoría</button>
                        </div>
                        <button @click="catListOpen = false" class="text-gray-400 hover:text-gray-500 transition-colors focus:outline-none">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="overflow-x-auto bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 max-h-[60vh] overflow-y-auto mb-4">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-900 sticky top-0 z-10">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nombre</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Productos</th>
                                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                @if($manageCategoryId === 'new')
                                <tr class="bg-green-50/50 dark:bg-green-900/10">
                                    <td class="px-6 py-3" colspan="2">
                                        <input wire:model="manageCategoryName" type="text" class="w-full py-2 px-3 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[var(--color-primary)] dark:bg-gray-900 dark:text-white" @keydown.enter="$wire.saveCategory()" placeholder="Nombre de la nueva categoría">
                                        @error('manageCategoryName') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <button wire:click="saveCategory" class="text-white bg-green-500 hover:bg-green-600 font-bold py-1.5 px-3 rounded text-xs mr-2 transition-colors">Guardar</button>
                                        <button wire:click="$set('manageCategoryId', null)" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 text-xs font-bold transition-colors">Cancelar</button>
                                    </td>
                                </tr>
                                @endif
                                @foreach($categories as $category)
                                    @if($manageCategoryId === $category->id)
                                    <tr class="bg-blue-50/50 dark:bg-blue-900/10">
                                        <td class="px-6 py-3" colspan="2">
                                            <input wire:model="manageCategoryName" type="text" class="w-full py-2 px-3 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[var(--color-primary)] dark:bg-gray-900 dark:text-white" @keydown.enter="$wire.saveCategory()" placeholder="Nombre de la categoría">
                                            @error('manageCategoryName') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <button wire:click="saveCategory" class="text-white bg-green-500 hover:bg-green-600 font-bold py-1.5 px-3 rounded text-xs mr-2 transition-colors">Guardar</button>
                                            <button wire:click="$set('manageCategoryId', null)" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 text-xs font-bold transition-colors">Cancelar</button>
                                        </td>
                                    </tr>
                                    @else
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white">{{ $category->name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-bold text-gray-500 dark:text-gray-400">
                                            <span class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-full">{{ $category->products()->count() }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex justify-end items-center gap-2 sm:gap-4">
                                                <button wire:click="editCategory({{ $category->id }})" title="Editar" class="text-[var(--color-primary)] hover:bg-[var(--color-primary)]/10 p-1.5 sm:p-0 rounded-lg font-bold transition-colors flex items-center">
                                                    <span class="hidden sm:inline">Editar</span>
                                                    <svg class="w-5 h-5 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                </button>
                                                <button wire:click="deleteCategory({{ $category->id }})" wire:confirm="¿Eliminar la categoría {{ $category->name }}? Esta acción no se puede deshacer." title="Eliminar" class="text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 p-1.5 sm:p-0 rounded-lg font-bold transition-colors flex items-center">
                                                    <span class="hidden sm:inline">Eliminar</span>
                                                    <svg class="w-5 h-5 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endif
                                @endforeach
                                @if(count($categories) === 0)
                                    <tr>
                                        <td colspan="3" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">No hay categorías creadas aún. Usá el botón + en el formulario para crear una.</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
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
