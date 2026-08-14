<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderPaid;
use Illuminate\Support\Facades\Cache;

class UpdateOrderOnPayment implements ShouldQueue
{
    use InteractsWithQueue;
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentApproved $event): void
    {
        $payment = $event->payment;
        $dataId  = $event->dataId;
        $order   = null;

        // BUG-02 FIX: La transacción solo hace trabajo de DB.
        // El email se envía FUERA del closure para evitar que un fallo del mail
        // haga rollback del estado de la orden (dejándola sin marcar como pagada).
        DB::transaction(function () use ($payment, $dataId, &$order) {
            $orderId = $payment->external_reference ?? null;
            if (! $orderId) {
                Log::warning('Webhook MP: pago sin external_reference', ['payment_id' => $dataId]);
                return;
            }

            $order = Order::find($orderId);
            if (! $order) {
                Log::warning('Webhook MP: orden no encontrada', ['order_id' => $orderId]);
                return;
            }

            // Idempotencia: ignorar si la orden ya fue procesada.
            // $order = null es la señal para NO enviar el email fuera de la transacción.
            if (in_array($order->status, ['pagado', 'completado'])) {
                Log::info('Webhook MP: orden ya procesada, ignorando webhook', ['order_id' => $orderId]);
                $order = null;
                return;
            }

            $order->mp_payment_id = $dataId;

            $order->status = match ($payment->status) {
                'approved'              => 'pagado',
                'pending', 'in_process' => 'pendiente',
                'rejected', 'cancelled' => 'cancelado',
                default                 => $order->status,
            };

            $order->save();

            // Invalidar caché de mayorista cuando se confirma un pago
            if ($order->user_id) {
                Cache::forget("user.{$order->user_id}.wholesale");
            }

            Log::info('Evento: orden actualizada', [
                'order_id'   => $orderId,
                'payment_id' => $dataId,
                'new_status' => $order->status,
            ]);
        });

        // BUG-02 FIX: Email fuera de la transacción.
        // BUG-15 FIX: Eager load de relaciones antes de pasar la orden al Mailable.
        if ($order && $order->user?->email) {
            try {
                Mail::to($order->user->email)->send(
                    new OrderPaid($order->load('items.product', 'user'))
                );
            } catch (\Exception $e) {
                Log::error('No se pudo enviar email de OrderPaid', ['error' => $e->getMessage()]);
            }
        }
    }
}
