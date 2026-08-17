<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    // Status constants to avoid hardcoded strings across the codebase
    const STATUS_PENDING   = 'pendiente';
    const STATUS_PAID      = 'pagado';
    const STATUS_COMPLETED = 'completado';
    const STATUS_CANCELLED = 'cancelado';

    const ALL_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'user_id',
        'status',
        'total',
        'phone',
        'address_street',
        'address_number',
        'city',
        'state',
        'zip_code',
        'delivery_method',   // FIX BUG-01: was missing, caused all orders to save null
        'delivery_address',
        'payment_method',
        'role_applied',
        'mp_preference_id',
        'mp_payment_id',
        'updated_by',
        'status_updated_at',
    ];

    protected $casts = [
        'status_updated_at' => 'datetime',
        'total'             => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault([
            'name'  => 'Usuario eliminado',
            'email' => '',
        ]);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    // ─── Query Scopes ─────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeRevenue($query)
    {
        return $query->whereIn('status', [self::STATUS_PAID, self::STATUS_COMPLETED]);
    }

    // ─── Helpers ──────────────────────────────────────────────────

    /**
     * Returns a human-readable label for the delivery method.
     */
    public function getDeliveryLabelAttribute(): string
    {
        return $this->delivery_method === 'envio' ? 'Envío a domicilio' : 'Retiro en Local';
    }

    /**
     * Returns a human-readable label for the order status.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => 'Pendiente',
            self::STATUS_PAID      => 'Pagado',
            self::STATUS_COMPLETED => 'Completado',
            self::STATUS_CANCELLED => 'Cancelado',
            default                => ucfirst($this->status),
        };
    }
}
