<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class G3ProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categoriesData = [
            'Xiaomi', 'Televisores Smart', 'Tablets', 'Samsung', 'Stanley', 'Periféricos', 'Placas de Video', 'Procesadores'
        ];

        $categories = [];
        foreach ($categoriesData as $catName) {
            $categories[$catName] = Category::firstOrCreate([
                'name' => $catName,
                'slug' => Str::slug($catName),
            ]);
        }

        $productsData = [
            // Xiaomi
            ['name' => 'XIAOMI 15T PRO NFC DUAL SIM 5G - 12GB/1TB', 'category' => 'Xiaomi', 'price' => 1530000],
            ['name' => 'XIAOMI POCO F6 PRO 5G - 12/512GB', 'category' => 'Xiaomi', 'price' => 641000],
            ['name' => 'XIAOMI REDMI NOTE 14 PRO 5G - 8/256 GB', 'category' => 'Xiaomi', 'price' => 490000],
            ['name' => 'XIAOMI TV BOX S 3ND GENERATION 4K ULTRA HD', 'category' => 'Xiaomi', 'price' => 145000],

            // Televisores Smart
            ['name' => 'Smart TV Qled 43 PULGADAS Xiaomi A Pro 4K', 'category' => 'Televisores Smart', 'price' => 535000],
            ['name' => 'TV SMART 50 PULGADAS MARCA JVC UHD 4K', 'category' => 'Televisores Smart', 'price' => 525000],
            ['name' => 'TV SMART 65 PULGADAS MARCA LG NANO80ASA 4K', 'category' => 'Televisores Smart', 'price' => 1263000],
            ['name' => 'TV SMART 75 PULGADAS MARCA SAMSUNG UHD CRYSTAL', 'category' => 'Televisores Smart', 'price' => 1395000],

            // Tablets
            ['name' => 'Tablet Samsung Galaxy Tab A11+ 128GB', 'category' => 'Tablets', 'price' => 432000],
            ['name' => 'Tablet Xiaomi Pad 7 Pro 256GB/8GB', 'category' => 'Tablets', 'price' => 847000],
            ['name' => 'Tablet Lenovo Idea Tab TB336ZU 11 PULGADAS', 'category' => 'Tablets', 'price' => 554000],
            ['name' => 'Tablet Blackview Tab 60 Pro 4G 128GB', 'category' => 'Tablets', 'price' => 239000],

            // Samsung
            ['name' => 'SAMSUNG S25 ULTRA 5G - 12/256GB', 'category' => 'Samsung', 'price' => 1659000],
            ['name' => 'SAMSUNG A56 5G - 8/128GB', 'category' => 'Samsung', 'price' => 651000],
            ['name' => 'Monitor Samsung Gaming CURVE 27 PULGADAS Odyssey G5', 'category' => 'Samsung', 'price' => 371000],
            ['name' => 'SAMSUNG A16 4G - 4/128GB', 'category' => 'Samsung', 'price' => 265000],

            // Stanley
            ['name' => 'TERMO Stanley 1.4 LITRO Classic Legendary', 'category' => 'Stanley', 'price' => 155000],
            ['name' => 'TRAVEL QUENCHER 1.2 LITROS BLANCO CON FLORES', 'category' => 'Stanley', 'price' => 126000],
            ['name' => 'Termo STANLEY Mate System 1.2 litros Negro', 'category' => 'Stanley', 'price' => 165000],
            ['name' => 'MATERO STANLEY - MESSI THE GOAT - 236 ML', 'category' => 'Stanley', 'price' => 78000],

            // Placas de Video
            ['name' => 'Placa de Video ASUS ROG Strix GeForce RTX 4090 24GB', 'category' => 'Placas de Video', 'price' => 2500000],
            ['name' => 'Placa de Video MSI Dual GeForce RTX 4070 12GB', 'category' => 'Placas de Video', 'price' => 1100000],
            ['name' => 'Placa de Video Gigabyte GeForce RTX 4060 Ti 8GB', 'category' => 'Placas de Video', 'price' => 750000],

            // Procesadores
            ['name' => 'Procesador AMD Ryzen 9 7950X3D 5.7GHz', 'category' => 'Procesadores', 'price' => 950000],
            ['name' => 'Procesador Intel Core i9-14900K 6.0GHz', 'category' => 'Procesadores', 'price' => 980000],
            ['name' => 'Procesador AMD Ryzen 7 7800X3D 5.0GHz', 'category' => 'Procesadores', 'price' => 650000],

            // Periféricos
            ['name' => 'Teclado Mecánico Logitech G Pro X TKL', 'category' => 'Periféricos', 'price' => 250000],
            ['name' => 'Mouse Gamer Razer Pro X Superlight 2', 'category' => 'Periféricos', 'price' => 180000],
            ['name' => 'Auriculares Inalámbricos HyperX Cloud III Wireless', 'category' => 'Periféricos', 'price' => 220000],
        ];

        foreach ($productsData as $data) {
            $catId = $categories[$data['category']]->id;

            Product::firstOrCreate(
                ['sku' => 'SKU-' . strtoupper(Str::random(6))],
                [
                    'name' => $data['name'],
                    'slug' => Str::slug($data['name']),
                    'description' => 'Producto oficial con garantía. Excelente calidad y rendimiento.',
                    'retail_price' => $data['price'],
                    'wholesale_price' => $data['price'] * 0.85, // 15% discount for wholesale
                    'cost_price' => $data['price'] * 0.70, // 30% profit margin
                    'profit_margin' => 30.00,
                    'stock' => rand(10, 100),
                    'category_id' => $catId,
                    'wholesale_min_quantity' => 10,
                ]
            );
        }
    }
}
