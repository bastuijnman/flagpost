<?php

namespace Tests\Integration;

use Bastuijnman\Flagpost\Goal;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Tests\TestCase;

class GoalTest extends TestCase
{

    use WithWorkbench, RefreshDatabase;

    public function test_it_should_have_goal_field()
    {
        Feature::define('my-test-feature', function () {
            return 'value';
        });

        $value = Feature::value('my-test-feature');

        $this->assertEquals('value', $value);
        $this->assertDatabaseHas('features', [
            'name' => 'my-test-feature',
            'converted_at' => null
        ]);
    }

    public function test_it_should_be_able_to_reach_goal()
    {
        $this->travelTo('2024-01-25 00:00:00');

        Feature::define('my-test-feature', function () {
            return 'value';
        });

        $value = Feature::value('my-test-feature');

        $this->assertEquals('value', $value);
        $this->assertDatabaseHas('features', [
            'name' => 'my-test-feature',
            'converted_at' => null
        ]);

        Goal::reached('my-test-feature');
        $this->assertDatabaseHas('features', [
            'name' => 'my-test-feature',
            'converted_at' => '2024-01-25 00:00:00'
        ]);
    }

    public function test_it_should_be_albe_to_reach_goal_with_custom_scope()
    {
        $this->travelTo('2024-01-25 00:00:00');

        Feature::define('my-test-feature', function () {
            return 'value';
        });

        Feature::for('custom-scope-1')->value('my-test-feature');
        Feature::for('custom-scope-2')->value('my-test-feature');

        $this->assertDatabaseCount('features', 2);

        Goal::for('custom-scope-2')->reached('my-test-feature');

        // Scope 2 should have been reached
        $this->assertDatabaseHas('features', [
            'name' => 'my-test-feature',
            'scope' => Feature::serializeScope('custom-scope-2'),
            'converted_at' => '2024-01-25 00:00:00'
        ]);

        // Scope 1 should not have been set to be reached
        $this->assertDatabaseHas('features', [
            'name' => 'my-test-feature',
            'scope' => Feature::serializeScope('custom-scope-1'),
            'converted_at' => null
        ]);
    }

    public function test_it_should_be_able_to_get_timeseries_results()
    {
        $this->travelTo('2024-01-25 16:00:00');

        // Insert 10 converted "pink" values
        Db::table('features')->insert(array_map(fn ($item) => ['name' => 'my-test-feature', 'scope' => Feature::serializeScope("part1-{$item}"), 'value' => 'pink', 'converted_at' => Carbon::now()], range(1, 10)));

        // Insert 5 converted "green" values
        Db::table('features')->insert(array_map(fn ($item) => ['name' => 'my-test-feature', 'scope' => Feature::serializeScope("part2-{$item}"), 'value' => 'green', 'converted_at' => Carbon::now()], range(1, 5)));

        $this->travelTo('2024-01-25 16:15:00');

        // Insert 8 converted "blue" values
        Db::table('features')->insert(array_map(fn ($item) => ['name' => 'my-test-feature', 'scope' => Feature::serializeScope("part3-{$item}"), 'value' => 'blue', 'converted_at' => Carbon::now()], range(1, 8)));

        $this->travelTo('2024-01-25 16:30:00');
        $collection = Goal::timeseries('my-test-feature', CarbonInterval::hour());

        $this->assertTrue($collection->has('pink'));
        $this->assertTrue($collection->has('green'));
        $this->assertTrue($collection->has('blue'));

        $pinkValues = $collection->get('pink')->first(fn ($item) => data_get($item, 'time') === Carbon::parse('2024-01-25 16:00:00')->timestamp);
        $this->assertNotNull($pinkValues);
        $this->assertEquals(10, $pinkValues->converted);

        $greenValues = $collection->get('green')->first(fn ($item) => data_get($item, 'time') === Carbon::parse('2024-01-25 16:00:00')->timestamp);
        $this->assertNotNull($greenValues);
        $this->assertEquals(5, $greenValues->converted);

        $blueValues = $collection->get('blue')->first(fn ($item) => data_get($item, 'time') === Carbon::parse('2024-01-25 16:15:00')->timestamp);
        $this->assertNotNull($blueValues);
        $this->assertEquals(8, $blueValues->converted);
    }

}