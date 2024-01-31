<?php

namespace Bastuijnman\Flagpost;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Drivers\DatabaseDriver;
use Laravel\Pennant\Feature;
use Laravel\Pennant\FeatureManager;
use RuntimeException;

class GoalManager
{

    protected mixed $scope = null;

    protected ?string $store = null;

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
    public function for(mixed $scope): GoalManager
    {
        $this->scope = $scope;
        return $this;
    }

    /**
     * Sets the store for the goal
     */
    public function store(string $store): GoalManager 
    {
        $this->store = $store;
        return $this;
    }

    /**
     * Indicates goal has been reached for a feature.
     */
    public function reached(string $feature)
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
    public function results(string $feature): Collection
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

    public function timeseries(string $feature, CarbonInterval $period, ?int $interval = null): Collection
    {

        /* 
         * Calculate timeseries intervals based on the period given. Calculates some 
         * defaults based on the periods that can be set in Laravel Pulse
         */
        $interval = $interval ?? match ($period->total('hours')) {
            1 => 300,
            6 => 1800,
            24 => 7200,
            168 => 86400,
            default => 300
        };

        $driver = Feature::getDefaultDriver();
        $start = floor(Carbon::now()->sub($period)->timestamp / $interval) * $interval;
        $range = Carbon::createFromTimestamp($start)->range('now', $interval, 'seconds');

        if (!Feature::store($this->store)->getDriver() instanceof DatabaseDriver) {
            throw new RuntimeException('Only DB Driver is supported');
        }

        // Define what macro to use based on database driver
        $conn = $this->config->get('database.default');
        $macro = match ($this->config->get("database.connections.{$conn}.driver")) {
            'sqlite' => "(unixepoch(converted_at) / {$interval}) * {$interval}",
            'mysql' => "UNIX_TIMESTAMP(converted_at) DIV {$interval} * {$interval}",
            'mariadb' => "UNIX_TIMESTAMP(converted_at) DIV {$interval} * {$interval}",
            'pgsql' => "cast(extract(epoch from converted_at)/({$interval}) as integer)*{$interval}",
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