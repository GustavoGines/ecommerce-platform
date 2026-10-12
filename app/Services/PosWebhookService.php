<?php

namespace App\Services;

use App\Jobs\SendPosOrderWebhookJob;
use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PosWebhookService
{
    /**
     * Construye y encola el webhook de una orden hacia el POS físico.
     */
    public function dispatchOrder(Order $order, string $eventType = 'order.created', bool $sync = false): void
    {
        $settings = StoreSetting::getSettings();

        if (! $settings->isPosSyncEnabled()) {
            return;
        }

        $configuredTrigger = $settings->pos_sync_on_event ?? 'order.created';

        // Si la tienda está configurada para sincronizar únicamente cuando la orden se paga,
        // no despachar el evento de creación inicial
        if (! $sync && $configuredTrigger === 'order.paid' && $eventType === 'order.created') {
            Log::info("POS Webhook: Tienda configurada para sincronizar en 'order.paid'. Omitiendo 'order.created' para orden #{$order->id}.");
            return;
        }

        // Si ya fue sincronizada previamente y llega otro evento (created o paid), no duplicar despacho
        if (! $sync && $order->pos_sync_status === 'synced') {
            Log::info("POS Webhook: Orden #{$order->id} ya se encuentra sincronizada con el POS. Omitiendo duplicado ({$eventType}).");
            return;
        }

        $order->loadMissing('items.product');

        $timestamp = time();
        $eventId = 'evt_ord' . $order->id . '_' . $timestamp . '_' . Str::lower(Str::random(6));

        $items = $order->items->map(function ($item) {
            return [
                'external_product_id' => (string) ($item->product_id ?? ''),
                'external_variant_id' => null,
                'sku'                 => $item->product?->sku ?? "PROD-{$item->product_id}",
                'name'                => $item->product?->name ?? "Producto #{$item->product_id}",
                'quantity'            => (int) $item->quantity,
                'price'               => (float) $item->price,
            ];
        })->values()->toArray();

        $payload = [
            'event_type'   => $eventType,
            'event_id'     => $eventId,
            'order_id'     => "ORD-{$order->id}",
            'order_number' => (int) $order->id,
            'timestamp'    => $timestamp,
            'items'        => $items,
        ];

        $tenantId = tenant('id');

        if ($sync) {
            SendPosOrderWebhookJob::dispatchSync($order->id, $payload, $tenantId);
        } else {
            SendPosOrderWebhookJob::dispatch($order->id, $payload, $tenantId);
        }

        Log::info("POS Webhook: Encolado job para orden #{$order->id} ({$eventType})", [
            'tenant'   => $tenantId,
            'event_id' => $eventId,
        ]);
    }
}
