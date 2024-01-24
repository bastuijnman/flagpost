<?php

namespace Bastuijnman\Flagpost;

use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class FlagpostServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Feature::macro('getDefaultScopeValue', function () {
            return $this->defaultScope();
        });
    }

}