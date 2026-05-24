<?php

use App\Providers\AppServiceProvider;
use App\Providers\RepositoryServiceProvider;
use App\Providers\ServiceServiceProvider;

return [
    // 順序: 依存関係を意識して Repository → Service → App の順で登録
    // (Service は Repository に依存、Auth は両方に依存)
    RepositoryServiceProvider::class,
    ServiceServiceProvider::class,
    AppServiceProvider::class,
];
