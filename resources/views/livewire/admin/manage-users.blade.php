<?php

use App\Models\User;
use App\Enums\UserRole;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $roleFilter = '';

    // Semi-CRUD properties
    public $showEditModal = false;
    public $editingUserId = null;
    public $editingName = '';
    public $editingPhone = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedRoleFilter()
    {
        $this->resetPage();
    }

    public function updateRole($userId, $newRole)
    {
        $user = User::findOrFail($userId);

        // Prevent changing own role
        if ($user->id === auth()->id()) {
            session()->flash('error', 'No puedes cambiar tu propio rol.');
            return;
        }

        $user->role = UserRole::from($newRole);
        $user->save();

        // BUG-06 FIX: Invalidar caché de precios mayoristas del usuario.
        // Sin esto, el usuario seguía viendo precios del rol anterior hasta
        // que el caché expirara (TTL de 1 hora), aunque el rol ya había cambiado.
        \Illuminate\Support\Facades\Cache::forget("user.{$userId}.wholesale");

        session()->flash('message', 'Rol actualizado exitosamente.');
    }

    public function toggleBan($userId)
    {
        $user = User::findOrFail($userId);
        
        if ($user->id === auth()->id()) {
            session()->flash('error', 'No puedes bloquearte a ti mismo.');
            return;
        }

        $user->is_banned = !$user->is_banned;
        $user->save();

        $status = $user->is_banned ? 'bloqueado' : 'desbloqueado';
        session()->flash('message', "Usuario {$status} exitosamente.");
    }

    public function editUser($userId)
    {
        $user = User::findOrFail($userId);
        $this->editingUserId = $user->id;
        $this->editingName = $user->name;
        $this->editingPhone = $user->phone ?? '';
        $this->showEditModal = true;
    }

    public function saveUser()
    {
        $this->validate([
            'editingName' => 'required|string|max:255',
            'editingPhone' => 'nullable|string|max:50',
        ]);

        $user = User::findOrFail($this->editingUserId);
        $user->name = $this->editingName;
        $user->phone = $this->editingPhone;
        $user->save();

        $this->showEditModal = false;
        session()->flash('message', 'Usuario actualizado exitosamente.');
    }

    public function with(): array
    {
        $query = User::query()->withCount('orders');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->roleFilter) {
            $query->where('role', $this->roleFilter);
        }

        return [
            'users' => $query->latest()->paginate(20),
            'roles' => UserRole::cases(),
        ];
    }
}
?>

<div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">Gestión de Usuarios</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Administra los clientes y sus roles en la plataforma.</p>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            {{ session('error') }}
        </div>
    @endif

    <div class="mb-6 flex flex-col sm:flex-row gap-4">
        <div class="flex-1 relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar por nombre o email..." class="w-full py-2.5 pl-10 pr-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)]">
            
            <div wire:loading.flex wire:target="search" class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center pointer-events-none">
                <svg class="animate-spin w-4 h-4 text-[var(--color-primary)]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </div>
            
            <div wire:loading.remove wire:target="search" class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center">
                @if($search)
                    <button wire:click="$set('search', '')" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                @endif
            </div>
        </div>
        <div class="w-full sm:w-64">
            <select wire:model.live="roleFilter" class="w-full py-2.5 px-4 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)]">
                <option value="">Todos los Roles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->value }}">{{ $role->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-700">
        <div class="overflow-x-auto hidden md:block">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-full">Usuario</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Registro</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Órdenes</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Rol y Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($users as $user)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <div>
                                    <div class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                        {{ $user->name }}
                                        @if($user->is_banned)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 border border-red-200 dark:border-red-800/50">
                                                Bloqueado
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
                                    @if($user->phone)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                            {{ $user->phone }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 align-middle">
                            {{ $user->created_at->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-bold text-gray-700 dark:text-gray-300 align-middle">
                            {{ $user->orders_count }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap align-middle">
                            <div class="flex items-center gap-3">
                                <select wire:change="updateRole({{ $user->id }}, $event.target.value)" 
                                        class="py-1.5 pl-3 pr-8 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 focus:ring-[var(--color-primary)] {{ $user->id === auth()->id() ? 'opacity-50 cursor-not-allowed' : '' }}"
                                        @if($user->id === auth()->id()) disabled @endif>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->value }}" @if(optional($user->role)->value === $role->value) selected @endif>
                                            {{ $role->label() }}
                                        </option>
                                    @endforeach
                                </select>

                                <div class="flex items-center gap-1">
                                    <button wire:click="editUser({{ $user->id }})" title="Editar Usuario" class="text-gray-400 hover:text-[var(--color-primary)] transition-colors p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>

                                    @if($user->id !== auth()->id())
                                        <button wire:click="toggleBan({{ $user->id }})" wire:confirm="¿Seguro que quieres {{ $user->is_banned ? 'desbloquear' : 'bloquear' }} a este usuario?" title="{{ $user->is_banned ? 'Desbloquear' : 'Bloquear (Lista Negra)' }}" class="{{ $user->is_banned ? 'text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 dark:bg-red-900/20 dark:hover:bg-red-900/40' : 'text-gray-400 hover:text-red-500 hover:bg-gray-100 dark:hover:bg-gray-700' }} transition-colors p-1.5 rounded-lg">
                                            @if($user->is_banned)
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                                            @else
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                            @endif
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No se encontraron usuarios.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Vista Móvil para Usuarios (Tarjetas) -->
        <div class="block md:hidden divide-y divide-gray-100 dark:divide-gray-700/50">
            @forelse($users as $user)
            <div class="p-4 flex flex-col gap-3">
                <div class="flex justify-between items-start">
                    <div class="flex flex-col">
                        <div class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            {{ $user->name }}
                            @if($user->is_banned)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 border border-red-200 dark:border-red-800/50">
                                    Bloqueado
                                </span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
                        @if($user->phone)
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                {{ $user->phone }}
                            </div>
                        @endif
                    </div>
                    <div class="text-[10px] font-bold text-gray-400 bg-gray-50 dark:bg-gray-800 px-2 py-1 rounded-md">
                        {{ $user->orders_count }} órdenes
                    </div>
                </div>

                <div class="flex justify-between items-center mt-2 pt-3 border-t border-gray-100 dark:border-gray-700/50">
                    <select wire:change="updateRole({{ $user->id }}, $event.target.value)" 
                            class="py-1.5 pl-3 pr-8 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-xs font-medium text-gray-700 dark:text-gray-300 focus:ring-[var(--color-primary)] {{ $user->id === auth()->id() ? 'opacity-50 cursor-not-allowed' : '' }}"
                            @if($user->id === auth()->id()) disabled @endif>
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}" @if(optional($user->role)->value === $role->value) selected @endif>
                                {{ $role->label() }}
                            </option>
                        @endforeach
                    </select>

                    <div class="flex items-center gap-1">
                        <button wire:click="editUser({{ $user->id }})" class="text-gray-400 hover:text-[var(--color-primary)] p-2 rounded-lg bg-gray-50 dark:bg-gray-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        </button>

                        @if($user->id !== auth()->id())
                            <button wire:click="toggleBan({{ $user->id }})" wire:confirm="¿Seguro que quieres {{ $user->is_banned ? 'desbloquear' : 'bloquear' }} a este usuario?" class="{{ $user->is_banned ? 'text-red-600 bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-900/50' : 'text-gray-400 bg-gray-50 dark:bg-gray-800 border border-transparent' }} p-2 rounded-lg">
                                @if($user->is_banned)
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                                @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                @endif
                            </button>
                        @endif
                    </div>
                </div>
            </div>
            @empty
                <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">No se encontraron usuarios.</div>
            @endforelse
        </div>

        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
            {{ $users->links() }}
        </div>
    </div>

    <!-- Edit User Modal -->
    @if($showEditModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity" aria-hidden="true" wire:click="$set('showEditModal', false)"></div>
            
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-lg w-full max-h-[90vh] flex flex-col z-10">
                <form wire:submit.prevent="saveUser" class="flex flex-col overflow-hidden">
                    <div class="overflow-y-auto p-6">
                        <h3 class="text-lg leading-6 font-black text-gray-900 dark:text-white mb-4" id="modal-title">
                            Editar Usuario
                        </h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label for="editingName" class="block text-sm font-bold text-gray-700 dark:text-gray-300">Nombre</label>
                                <input type="text" wire:model="editingName" id="editingName" class="mt-1 w-full py-2 px-3 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)]">
                                @error('editingName') <span class="text-xs text-red-500 font-bold mt-1">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label for="editingPhone" class="block text-sm font-bold text-gray-700 dark:text-gray-300">Teléfono (Opcional)</label>
                                <input type="text" wire:model="editingPhone" id="editingPhone" class="mt-1 w-full py-2 px-3 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--color-primary)]">
                                @error('editingPhone') <span class="text-xs text-red-500 font-bold mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-900/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-200 dark:border-gray-700">
                        <button type="submit" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-[var(--color-primary)] text-base font-bold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                            Guardar
                        </button>
                        <button type="button" wire:click="$set('showEditModal', false)" class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-bold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--color-primary)] sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
