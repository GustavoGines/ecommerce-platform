<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Sheets CSV URL
    |--------------------------------------------------------------------------
    |
    | URL pública del CSV exportado desde Google Sheets, usado para sincronizar
    | precios y stock mediante el comando shop:sync-prices.
    | Usar config('shop.google_sheets_csv_url') en lugar de env() directamente
    | para que funcione correctamente cuando el config está cacheado en producción.
    |
    */

    'google_sheets_csv_url' => env('GOOGLE_SHEETS_CSV_URL'),

];
