<?php

namespace Bastuijnman\Flagpost;

use Illuminate\Support\Facades\Facade;

/**
 * @method static Bastuijnman\Flagpost\GoalManager for(mixed $scope)
 * @method static Bastuijnman\Flagpost\GoalManager store(string $store)
 * @method static void reached(mixed $feature)
 */
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