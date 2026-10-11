<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'direction',
        'event_type',
        'order_id',
        'payload',
        'response_code',
        'response_body',
        'status',
        'error_message',
        'ip_address',
    ];

    protected $casts = [
        'response_code' => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Helper para registrar un log de forma rápida y segura.
     */
    public static function logEvent(
        string $direction,
        string $eventType,
        mixed $payload = null,
        ?int $responseCode = null,
        mixed $responseBody = null,
        string $status = 'success',
        ?string $errorMessage = null,
        ?int $orderId = null,
        ?string $ipAddress = null
    ): self {
        return static::create([
            'direction'     => $direction,
            'event_type'    => $eventType,
            'order_id'      => $orderId,
            'payload'       => is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'response_code' => $responseCode,
            'response_body' => is_string($responseBody) ? $responseBody : json_encode($responseBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'status'        => $status,
            'error_message' => $errorMessage,
            'ip_address'    => $ipAddress ?? request()?->ip(),
        ]);
    }
}
