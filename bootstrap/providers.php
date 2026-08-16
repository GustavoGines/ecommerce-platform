<?php

use App\Providers\AppServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    VoltServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
];
