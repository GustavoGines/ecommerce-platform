<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'slug',
        'description',
        'technical_specs',
        'cost_price',
        'profit_margin',
        'wholesale_discount',
        'wholesale_min_quantity',
        'retail_price',
        'wholesale_price',
        'stock',
        'image_url',
        'images',
        'category_id',
    ];

    protected $casts = [
        'technical_specs' => 'array',
        'images' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->name);
            }
        });

        static::updating(function ($product) {
            // FIX BUG-11: Always regenerate slug when name changes,
            // not only when slug is empty (which was always false after first save)
            if ($product->isDirty('name')) {
                $product->slug = static::generateUniqueSlug($product->name, $product->id);
            }
        });
    }

    /**
     * Generate a unique slug for the product.
     * Appends a numeric suffix if the base slug already exists.
     */
    private static function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = \Illuminate\Support\Str::slug($name);
        $slug = $base;
        $counter = 1;

        $query = static::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->clone()->exists()) {
            $slug  = $base . '-' . $counter;
            $query = static::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            $counter++;
        }

        return $slug;
    }


    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brands()
    {
        return $this->belongsToMany(Brand::class);
    }
}
