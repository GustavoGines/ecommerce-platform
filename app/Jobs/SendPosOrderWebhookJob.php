<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\PosSyncLog;
use App\Models\StoreSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendPosOrderWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de reintentos antes de marcar como fallido.
     */
    public int $tries = 5;

    /**
     * Tiempos de espera con backoff exponencial (30 segundos, 2 minutos, 10 minutos, 1 hora).
     */
    public array $backoff = [30, 120, 600, 3600];

    public function __construct(
        public int $orderId,
        public array $payload,
        public ?string $tenantId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Asegurar contexto del Tenant si corre en worker asíncrono
        if ($this->tenantId && ! tenancy()->initialized) {
            tenancy()->initialize($this->tenantId);
        }

        $settings = StoreSetting::getSettings();

        if (! $settings->isPosSyncEnabled()) {
            Log::info("POS Job: Sincronización desactivada para orden #{$this->orderId}. Omitiendo.");
            return;
        }

        $url = $settings->pos_webhook_url;
        $secret = $settings->pos_webhook_secret;

        if (empty($url) || empty($secret)) {
            Log::warning("POS Job: URL o Secret no configurados para orden #{$this->orderId}.");
            return;
        }

        $currentTimestamp = time();
        $this->payload['timestamp'] = $currentTimestamp;
        $rawBody = json_encode($this->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $rawBody, $secret);
        $timestamp = (string) $currentTimestamp;

        $headers = [
            'Content-Type' => 'application/json',
            'X-Signature'  => $signature,
            'X-Timestamp'  => $timestamp,
        ];

        try {
            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->withBody($rawBody, 'application/json')
                ->post($url);

            $order = Order::find($this->orderId);

            if ($response->successful()) {
                Log::info("POS Webhook: Éxito despachando orden #{$this->orderId} al POS ({$response->status()})");

                if ($order) {
                    $order->update([
                        'pos_synced_at'   => now(),
                        'pos_sync_status' => 'synced',
                    ]);
                }

                PosSyncLog::logEvent(
                    direction: 'outbound',
                    eventType: $this->payload['event_type'] ?? 'order.created',
                    payload: $this->payload,
                    responseCode: $response->status(),
                    responseBody: $response->json() ?: $response->body(),
                    status: 'success',
                    orderId: $this->orderId
                );

                return;
            }

            // Si el POS rechazó o dio error 5xx / 4xx
            $errorMsg = "Respuesta fallida del POS (HTTP {$response->status()}): " . $response->body();
            Log::warning("POS Webhook: Error para orden #{$this->orderId}. {$errorMsg}");

            if ($order) {
                $order->update([
                    'pos_sync_status' => 'failed',
                ]);
            }

            PosSyncLog::logEvent(
                direction: 'outbound',
                eventType: $this->payload['event_type'] ?? 'order.created',
                payload: $this->payload,
                responseCode: $response->status(),
                responseBody: $response->body(),
                status: $this->attempts() >= $this->tries ? 'failed' : 'retrying',
                errorMessage: $errorMsg,
                orderId: $this->orderId
            );

            // Lanzar excepción para que Laravel reintente con backoff exponencial
            throw new \Exception($errorMsg);

        } catch (\Throwable $e) {
            // Error de conexión, timeout o red
            Log::error("POS Webhook: Excepción de conexión para orden #{$this->orderId} (Intento {$this->attempts()}/{$this->tries}): " . $e->getMessage());

            $order = Order::find($this->orderId);
            if ($order) {
                $order->update([
                    'pos_sync_status' => 'failed',
                ]);
            }

            PosSyncLog::logEvent(
                direction: 'outbound',
                eventType: $this->payload['event_type'] ?? 'order.created',
                payload: $this->payload,
                responseCode: null,
                responseBody: null,
                status: $this->attempts() >= $this->tries ? 'failed' : 'retrying',
                errorMessage: $e->getMessage(),
                orderId: $this->orderId
            );

            throw $e;
        }
    }
}
