<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPricesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'g3:sync-prices {--url= : URL pública del CSV de Google Sheets}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza los precios desde un CSV público de Google Sheets, inflando un 10%';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Obtener URL (de argumento o del .env)
        $url = $this->option('url') ?: env('GOOGLE_SHEETS_CSV_URL');

        if (!$url) {
            $this->error('No se ha configurado la URL del Google Sheet. Usa --url o configura GOOGLE_SHEETS_CSV_URL en tu .env');
            return Command::FAILURE;
        }

        $this->info("Descargando archivo CSV desde: {$url}");

        try {
            // 2. Descargar CSV
            $response = Http::get($url);
            
            if (!$response->successful()) {
                $this->error("Error HTTP al descargar el CSV: " . $response->status());
                return Command::FAILURE;
            }

            $csvData = $response->body();
            $lines = explode("\n", $csvData);
            
            $updatedCount = 0;
            $notFoundCount = 0;

            $this->info("Procesando " . count($lines) . " líneas...");

            // 2.5 "Borrón y cuenta nueva": Poner todo en stock 0 (Agotado)
            // Esto asegura que lo que el proveedor haya borrado del Excel ya no se pueda comprar.
            Product::query()->update(['stock' => 0]);
            $this->info("Stock de todos los productos reseteado a 0.");

            // 3. Procesar Líneas
            foreach ($lines as $index => $line) {
                // Saltar la primera línea si es la cabecera (Categoria, Producto, etc)
                if ($index === 0) continue;

                $row = str_getcsv($line);

                // Estructura del nuevo Excel: 
                // Col 0: Categoria
                // Col 1: Producto
                // Col 6: Precio Pesos G3 (Contado)
                // Col 7: Precio Costo G3 (Costo)
                
                if (count($row) < 8) continue;

                $productName = trim($row[1]);
                $rawCashPrice = $row[6];
                $rawCostPrice = $row[7];

                // Función auxiliar para limpiar precios argentinos
                $cleanPriceFunc = function($rawPrice) {
                    $priceStr = str_replace('.', '', $rawPrice);
                    $priceStr = str_replace(',', '.', $priceStr);
                    return (float) preg_replace('/[^0-9.]/', '', $priceStr);
                };

                $cashPrice = $cleanPriceFunc($rawCashPrice);
                $costPrice = $cleanPriceFunc($rawCostPrice);

                if (empty($productName) || $cashPrice <= 0) continue;

                // 4. Lógica de Negocio: Sumar 10% para precio web (Precio de Lista)
                $webPrice = $cashPrice * 1.10;

                // 5. Buscar y Actualizar
                $product = Product::where('name', $productName)->first();

                if ($product) {
                    $product->update([
                        'retail_price' => $webPrice,
                        'wholesale_price' => $cashPrice,
                        'cost_price' => $costPrice,
                        // Al encontrar el producto, le damos stock infinito (o suficiente) para el modelo a pedido
                        'stock' => 999
                    ]);
                    $updatedCount++;
                } else {
                    $notFoundCount++;
                }
            }

            $this->info("✅ Sincronización completada.");
            $this->info("- Productos actualizados: {$updatedCount}");
            $this->info("- Productos del Excel no encontrados en la web: {$notFoundCount}");
            
            Log::info("Sincronización automática completada: {$updatedCount} actualizados.");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error crítico durante la sincronización: " . $e->getMessage());
            Log::error("Error en g3:sync-prices: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
