<?php

namespace Tests\Feature;

use App\Jobs\SendPosOrderWebhookJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\PosWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PosWebhookDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected string $secret = 'sec_pos_test_secret_123';
    protected string $webhookUrl = 'https://pos-store.test/api/webhooks/ecommerce/ecommerce_platform';

    protected function setUp(): void
    {
        parent::setUp();

        $setting = StoreSetting::first() ?? new StoreSetting();
        $setting->store_name = 'G3 Tecnología';
        $setting->pos_sync_enabled = true;
        $setting->pos_api_token = 'token_test_123';
        $setting->pos_webhook_url = $this->webhookUrl;
        $setting->pos_webhook_secret = $this->secret;
        $setting->save();
    }

    public function test_webhook_job_is_dispatched_with_canonical_payload()
    {
        Queue::fake();

        $product = Product::factory()->create([
            'name'         => 'Monitor Gamer 24 pulg 144Hz',
            'sku'          => 'MON-24-144',
            'retail_price' => 120000.00,
        ]);

        $order = Order::factory()->create([
            'status' => 'pendiente',
            'total'  => 240000.00,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity'   => 2,
            'price'      => 120000.00,
        ]);

        app(PosWebhookService::class)->dispatchOrder($order, 'order.created');

        Queue::assertPushed(SendPosOrderWebhookJob::class, function ($job) use ($order, $product) {
            $payload = $job->payload;

            $this->assertEquals($order->id, $job->orderId);
            $this->assertEquals('order.created', $payload['event_type']);
            $this->assertEquals("ORD-{$order->id}", $payload['order_id']);
            $this->assertEquals($order->id, $payload['order_number']);
            $this->assertStringStartsWith('evt_ord', $payload['event_id']);
            $this->assertCount(1, $payload['items']);

            $item = $payload['items'][0];
            $this->assertEquals((string)$product->id, $item['external_product_id']);
            $this->assertEquals('MON-24-144', $item['sku']);
            $this->assertEquals('Monitor Gamer 24 pulg 144Hz', $item['name']);
            $this->assertEquals(2, $item['quantity']);
            $this->assertEquals(120000.00, $item['price']);

            return true;
        });
    }

    public function test_job_sends_http_request_with_valid_hmac_sha256_signature()
    {
        Http::fake([
            $this->webhookUrl => Http::response(['status' => 'ok', 'received' => true], 200),
        ]);

        $product = Product::factory()->create([
            'name'         => 'Teclado Mecánico',
            'sku'          => 'TEC-MEC-01',
            'retail_price' => 25000.00,
        ]);

        $order = Order::factory()->create([
            'status' => 'pagado',
            'total'  => 25000.00,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
            'price'      => 25000.00,
        ]);

        $timestamp = time();
        $payload = [
            'event_type'   => 'order.paid',
            'event_id'     => 'evt_test_9999',
            'order_id'     => "ORD-{$order->id}",
            'order_number' => $order->id,
            'timestamp'    => $timestamp,
            'items'        => [
                [
                    'external_product_id' => (string) $product->id,
                    'sku'                 => 'TEC-MEC-01',
                    'name'                => 'Teclado Mecánico',
                    'quantity'            => 1,
                    'price'               => 25000.00,
                ],
            ],
        ];

        $job = new SendPosOrderWebhookJob($order->id, $payload, null);
        $job->handle();

        // Verificar que Http hizo la llamada correcta y con cabeceras HMAC
        Http::assertSent(function ($request) use ($payload) {
            $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $expectedSignature = hash_hmac('sha256', $rawBody, $this->secret);

            $this->assertEquals($this->webhookUrl, $request->url());
            $this->assertEquals($expectedSignature, $request->header('X-Signature')[0]);
            $this->assertEquals((string)$payload['timestamp'], $request->header('X-Timestamp')[0]);
            $this->assertEquals('application/json', $request->header('Content-Type')[0]);

            return true;
        });

        // Verificar que la orden quedó registrada como sincronizada
        $this->assertEquals('synced', $order->fresh()->pos_sync_status);
        $this->assertNotNull($order->fresh()->pos_synced_at);

        $this->assertDatabaseHas('pos_sync_logs', [
            'direction'  => 'outbound',
            'event_type' => 'order.paid',
            'status'     => 'success',
            'order_id'   => $order->id,
        ]);
    }

    public function test_anti_echo_invariant_stock_update_does_not_dispatch_webhook()
    {
        Queue::fake();

        $product = Product::factory()->create([
            'stock' => 10,
            'sku'   => 'ANTI-ECHO-1',
        ]);

        // Simular llamada POS ➔ Web
        $response = $this->withHeaders([
            'Authorization' => 'Bearer token_test_123',
            'X-Tenant-ID'   => 'g3',
        ])->postJson('/api/v1/inventory/update', [
            'product_id' => 'ANTI-ECHO-1',
            'quantity'   => 20,
        ]);

        $response->assertStatus(200);

        // Invariante Anti-Eco: no debe haberse encolado NINGÚN webhook saliente hacia el POS
        Queue::assertNothingPushed();
    }
}
