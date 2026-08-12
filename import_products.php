<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

DB::statement('SET FOREIGN_KEY_CHECKS=0;');
DB::table('order_items')->truncate();
DB::table('orders')->truncate();
DB::table('products')->truncate();
DB::table('categories')->truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1;');

$lines = array_slice(file('productos.csv'), 4); // skip headers

$catCache = [];

foreach($lines as $l) {
    $cols = str_getcsv($l);
    if(count($cols) < 5) continue;
    
    $catName = trim($cols[0]);
    $prodName = trim($cols[2]);
    $priceStr = trim($cols[4]);
    
    // Skip empty or invalid lines
    if(empty($catName) || empty($prodName) || empty($priceStr) || str_contains($catName, 'ACTUALIZAC')) {
        continue;
    }
    
    // Parse price
    $priceStr = str_replace(['$', '.', ' '], '', $priceStr);
    $price = (float) $priceStr;
    if($price <= 0) continue;
    
    // Get or Create Category
    if(!isset($catCache[$catName])) {
        $cat = Category::create([
            'name' => $catName,
            'slug' => Str::slug($catName)
        ]);
        $catCache[$catName] = $cat->id;
    }
    
    $catId = $catCache[$catName];
    
    // Create Product
    Product::create([
        'name' => $prodName,
        'slug' => Str::slug($prodName) . '-' . Str::random(5),
        'description' => $prodName,
        'retail_price' => $price,
        'wholesale_price' => $price, // Assuming same for now, can be adjusted later
        'stock' => 10, // Default stock
        'category_id' => $catId,
        'is_featured' => false
    ]);
}

echo "Import complete. Inserted " . count($catCache) . " categories and " . Product::count() . " products.\n";
