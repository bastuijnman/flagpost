<?php

namespace Bastuijnman\Flagpost;

use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class FlagpostServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        /*
         * Define a macro to get the default scope, this feels incredibly nasty
         * but it looks like it's the only way to enforce getting the default scope
         * if nothing gets passed to flagpost (other than re-recreating the entire
         * setup of pennant).
         */
        Feature::macro('getDefaultScopeValue', function () {

            /** @var \Laravel\Pennant\Drivers\Decorator $this */
            return $this->defaultScope();
        });
    }

}