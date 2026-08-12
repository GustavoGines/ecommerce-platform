<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\StoreSetting;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        StoreSetting::create([
            'store_name' => 'G3 Tecnología',
            'store_tagline' => 'El mayor catálogo de controles remotos y electrónica. Ventas por mayor y menor.',
            'logo_url' => null,
            'favicon_url' => null,
            'theme_name' => 'modern-light',
            'social_links' => [
                'tiktok' => 'https://www.tiktok.com/@jcg.electronica',
                'facebook' => 'https://www.facebook.com/profile.php?id=61558661411698',
                'whatsapp' => '3704022685',
                'instagram' => 'https://www.instagram.com/jcgelectronica.fsa/'
            ],
        ]);

        // Se eliminaron los productos de prueba. La base de datos iniciará limpia.
        $this->call([
            G3ProductsSeeder::class,
        ]);
    }
}
