<?php

namespace Bastuijnman\Flagpost;

use Illuminate\Support\Facades\Facade;

class Goal extends Facade
{

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return GoalManager::class;
    }

}