<?php

namespace Bastuijnman\Flagpost;

use Illuminate\Support\ServiceProvider;

class FlagpostServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

}