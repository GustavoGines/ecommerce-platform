<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'table_preferences', 'google_id', 'phone', 'is_banned'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'table_preferences' => 'array',
            'role' => \App\Enums\UserRole::class,
            'is_banned' => 'boolean',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function isWholesaleCustomer(): bool
    {
        // First check if the user has been manually assigned the Mayorista role
        if ((is_string($this->role) && $this->role === 'mayorista') || 
            ($this->role instanceof \App\Enums\UserRole && $this->role->value === 'mayorista')) {
            return true;
        }

        // Fallback: buscar en historial de compras si alguna orden pagada suma >= GLOBAL_WHOLESALE_MIN unidades
        return \Illuminate\Support\Facades\Cache::remember(
            "user.{$this->id}.wholesale", 
            300, 
            fn() => $this->orders()
                ->whereIn('status', ['pagado', 'completado'])
                ->where(function ($query) {
                    $query->selectRaw('COALESCE(SUM(quantity), 0)')
                          ->from('order_items')
                          ->whereColumn('order_items.order_id', 'orders.id');
                }, '>=', \App\Services\PricingService::GLOBAL_WHOLESALE_MIN)
                ->exists()
        );
    }

    /**
     * Determine if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return (is_string($this->role) && $this->role === 'admin') 
            || ($this->role instanceof \App\Enums\UserRole && $this->role->value === 'admin');
    }
}
