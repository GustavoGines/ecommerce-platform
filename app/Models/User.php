<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

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
        if ($this->role === \App\Enums\UserRole::Mayorista) {
            return true;
        }

        // Fallback: verificar si el total histórico de unidades compradas y pagadas
        // supera el umbral global mayorista. La caché evita la consulta en cada request.
        $tenantId = tenant('id') ?? 'global';
        return \Illuminate\Support\Facades\Cache::remember(
            "user.{$tenantId}.{$this->id}.wholesale",
            300,
            fn() => \Illuminate\Support\Facades\DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.user_id', $this->id)
                ->whereIn('orders.status', [\App\Models\Order::STATUS_PAID, \App\Models\Order::STATUS_COMPLETED])
                ->sum('order_items.quantity') >= \App\Services\PricingService::GLOBAL_WHOLESALE_MIN
        );
    }

    /**
     * Determine if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === \App\Enums\UserRole::Admin;
    }
}
