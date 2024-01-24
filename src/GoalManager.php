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

    public function for($scope)
    {
        $this->scope = $scope;
        return $this;
    }

    public function store($store) {
        $this->scope = $store;
        return $this;
    }

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