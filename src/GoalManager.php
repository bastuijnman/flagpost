<?php

namespace Bastuijnman\Flagpost;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            ->update(['converted_at' => Carbon::now()]);
    }

    /**
     * Get the total number of sessions & conversions for a given feature. 
     */
    public function results($feature): Collection
    {
        $driver = Feature::getDefaultDriver();

        if (!Feature::store($this->store)->getDriver() instanceof DatabaseDriver) {
            throw new RuntimeException('Only DB Driver is supported');
        }

        // Grab total number of "sessions" started for feature
        $total = $this->db
            ->connection($this->config->get("pennant.stores.{$driver}.connection") ?? null)
            ->table($this->config->get("pennant.stores.{$driver}.table") ?? 'features')
            ->where('name', $feature)
            ->count();

        // Grab conversion count per value and prepend the total to the collection
        return $this->db
            ->connection($this->config->get("pennant.stores.{$driver}.connection") ?? null)
            ->table($this->config->get("pennant.stores.{$driver}.table") ?? 'features')
            ->select(DB::raw('value, count(*) as converted'))
            ->where('name', $feature)
            ->whereNotNull('converted_at')
            ->groupBy('value')
            ->get()
            ->prepend(['value' => 'total', 'converted' => $total]);
    }

    public function timeseries($feature, CarbonInterval $interval): Collection
    {
        $driver = Feature::getDefaultDriver();
        $start = floor(Carbon::now()->sub($interval)->timestamp / 300) * 300;
        $range = Carbon::createFromTimestamp($start)->range('now', 5, 'minutes');

        if (!Feature::store($this->store)->getDriver() instanceof DatabaseDriver) {
            throw new RuntimeException('Only DB Driver is supported');
        }

        // Define what macro to use based on database driver
        $conn = $this->config->get('database.default');
        $macro = match ($this->config->get("database.connections.{$conn}.driver")) {
            'sqlite' => '(unixepoch(converted_at) / 300) * 300',
            'mysql' => 'UNIX_TIMESTAMP(converted_at) DIV 300 * 300',
            default => throw new RuntimeException('Current database driver not supported for time series')
        };

        $times = iterator_to_array($range->map(fn ($date) => $date->timestamp));

        /*
         * Grab collection from database in timeseries by injecting the proper macro for the 
         * database driver.
         * 
         * TODO: When there are no sessions started for a particular value for the provided
         * time it will not show up in the results.
         */
        $collection = $this->db
            ->connection($this->config->get("pennant.stores.{$driver}.connection") ?? null)
            ->table($this->config->get("pennant.stores.{$driver}.table") ?? 'features')
            ->select(DB::raw("value, count(converted_at) as converted, {$macro} as time"))
            ->where('name', $feature)
            ->where('converted_at', '>=', Carbon::createFromTimestamp($start)->toDateTimeString())
            ->groupByRaw('value, time')
            ->orderBy('time')
            ->get()
            ->groupBy('value');

        /*
         * DB results might not include timestamps if they did not have converted values at that point, so
         * we add it if missing.
         * 
         * Not a great solution, but still better than to do this in DB
         */
        foreach ($times as $time) {
            $collection->each(function (Collection $timeValues, string $value) use ($time, $collection) {
                if (!$timeValues->first(fn ($timeValue) => data_get($timeValue, 'time') === $time)) {
                    $timeValues->add([ 'time' => $time, 'converted' => 0 ]);
                }
                $collection->put($value, $timeValues->sortBy('time')->values());
            });
        }

        return $collection;
    }

}