<?php

namespace Bastuijnman\Flagpost;

use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Laravel\Pennant\Drivers\DatabaseDriver;
use Laravel\Pennant\Feature;
use Laravel\Pennant\FeatureManager;
use RuntimeException;

class GoalManager
{

    protected mixed $scope = null;

    protected mixed $store = null;

    public function __construct(
        protected FeatureManager $manager,
        protected DatabaseManager $db,
        protected Repository $config
    )
    {
    }

    /**
     * Sets the scope for the goal. 
     */
    public function for($scope): GoalManager
    {
        $this->scope = $scope;
        return $this;
    }

    /**
     * Sets the store for the goal
     */
    public function store($store): GoalManager 
    {
        $this->scope = $store;
        return $this;
    }

    /**
     * Indicates goal has been reached for a feature.
     */
    public function reached($feature)
    {    
        $driver = Feature::getDefaultDriver();
        $scope = $this->scope ?? Feature::getDefaultScopeValue();

        if (!Feature::store($this->store)->getDriver() instanceof DatabaseDriver) {
            throw new RuntimeException('Only DB Driver is supported');
        }
       
        $this->db
            ->connection($this->config->get("pennant.stores.{$driver}.connection") ?? null)
            ->table($this->config->get("pennant.stores.{$driver}.table") ?? 'features')
            ->where('name', $feature)
            ->where('scope', Feature::serializeScope($scope))
            ->update(['goal_reached' => true]);
    }

}