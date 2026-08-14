<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Resources\Payment;

class MercadoPagoService
{
    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
        // SEC-03 FIX: TLS activo en prod (SERVER), desactivado solo en desarrollo (LOCAL).
        MercadoPagoConfig::setRuntimeEnviroment(
            app()->isProduction() ? MercadoPagoConfig::SERVER : MercadoPagoConfig::LOCAL
        );
    }

    /**
     * Crea una preferencia de pago en MercadoPago para una orden dada.
     * Devuelve la URL de pago (init_point) y el preference_id.
     *
     * @param  Order  $order  La orden ya persistida en DB.
     * @param  array  $cartItems  Array de [product_id => quantity].
     * @return array{preference_id: string, init_point: string, sandbox_init_point: string}
     */
    public function createPreference(Order $order, array $cartItems): array
    {
        $productIds = array_keys($cartItems);
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        // DRY-01: Usar PricingService para calcular el precio — misma regla que el carrito y checkout.
        $pricingService = app(\App\Services\PricingService::class);
        $user = $order->user; // Ya cargado por eager-load en checkout

        // Construir los items de la preferencia
        $items = [];
        foreach ($cartItems as $productId => $quantity) {
            if (! isset($products[$productId])) {
                continue;
            }

            $product = $products[$productId];
            // DRY-01 FIX: Calcular la cantidad total del carrito para aplicar descuentos por volumen.
            // Sin este argumento, un cliente con 10+ unidades en carrito pagaba precio de lista en MP.
            $cartTotalQuantity = array_sum($cartItems);
            $unitPrice = $pricingService->unitPrice($product, (int) $quantity, $user, $cartTotalQuantity);

            $items[] = [
                'id'          => (string) $product->id,
                'title'       => $product->name,
                'quantity'    => (int) $quantity,
                'unit_price'  => $unitPrice,
                'currency_id' => 'ARS',
            ];
        }

        // URLs de retorno
        $backUrls = [
            'success' => route('checkout.success', ['order' => $order->id]),
            'failure' => route('checkout.failure', ['order' => $order->id]),
            'pending' => route('checkout.pending', ['order' => $order->id]),
        ];

        // Datos del comprador
        $payer = [
            'name' => $order->user->name ?? '',
            'email' => $order->user->email ?? '',
        ];

        // Metadata para identificar la orden en el webhook
        $metadata = [
            'order_id' => $order->id,
        ];

        $requestData = [
            'items' => $items,
            'payer' => $payer,
            'back_urls' => $backUrls,
            'external_reference' => (string) $order->id,
            'metadata' => $metadata,
            'statement_descriptor' => config('app.name'),
        ];

        // auto_return y notification_url requieren URLs públicas
        // Se activan en producción O cuando hay un túnel activo (desarrollo con localtunnel/ngrok)
        if (app()->isProduction() || config('app.tunnel_active')) {
            $requestData['auto_return'] = 'approved';
            $requestData['notification_url'] = route('webhook.mercadopago');
        }

        try {
            $client = new PreferenceClient;
            $preference = $client->create($requestData);

            return [
                'preference_id' => $preference->id,
                'init_point' => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point,
            ];
        } catch (MPApiException $e) {
            $errorData = $e->getApiResponse()->getContent();
            Log::error('Error creating MercadoPago preference', [
                'error' => $errorData,
                'status' => $e->getApiResponse()->getStatusCode(),
                'request' => $requestData
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('General error creating MercadoPago preference: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Consulta un pago individual por su ID (útil en el webhook).
     *
     * @return Payment
     */
    public function getPayment(int|string $paymentId)
    {
        try {
            $client = new PaymentClient;

            return $client->get((int) $paymentId);
        } catch (MPApiException $e) {
            Log::error('MercadoPago API Error al consultar pago', [
                'payment_id' => $paymentId,
                'status' => $e->getApiResponse()->getStatusCode(),
                'content' => $e->getApiResponse()->getContent(),
            ]);
            throw $e;
        }
    }
}
