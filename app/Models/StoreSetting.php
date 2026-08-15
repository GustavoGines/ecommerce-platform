<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class StoreSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_name',
        'store_tagline',
        'theme_name',
        'offers_cash_discount',
        'logo_url',
        'favicon_url',
        'social_links',
        'mp_access_token',
        'mp_public_key',
    ];

    protected $casts = [
        'social_links' => 'array',
    ];

    public static function getSettings()
    {
        // BUG-04 FIX: Cachear los settings para evitar consultas en cada request.
        // IMPORTANTE: cacheamos los atributos como array plano (NO el objeto Eloquent),
        // porque el cache serializa/deserializa con unserialize() y si la clase no está
        // cargada aún en ese punto, lanza "incomplete object". Con un array, ese problema
        // no existe. Reconstruimos la instancia después de leer el caché.
        $tenantId = tenant('id') ?? 'global';
        $cacheKey = 'store_settings_' . $tenantId;

        $attributes = Cache::remember($cacheKey, 3600, function () {
            try {
                if (! Schema::hasTable('store_settings')) {
                    return [];
                }
                return static::first()?->getAttributes() ?? [];
            } catch (\Exception $e) {
                return [];
            }
        });

        if (empty($attributes)) {
            return new static;
        }

        $instance = new static;
        $instance->setRawAttributes($attributes);
        $instance->exists = true;
        return $instance;
    }



}
