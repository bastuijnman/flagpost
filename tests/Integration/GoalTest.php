<?php

namespace Tests\Integration;

use Bastuijnman\Flagpost\Goal;
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
            'goal_reached' => false
        ]);

    }

    public function test_it_should_be_able_to_reach_goal()
    {
        Feature::define('my-test-feature', function () {
            return 'value';
        });

        $value = Feature::value('my-test-feature');

        $this->assertEquals('value', $value);
        $this->assertDatabaseHas('features', [
            'name' => 'my-test-feature',
            'goal_reached' => false
        ]);

        Goal::reached('my-test-feature');
        $this->assertDatabaseHas('features', [
            'name' => 'my-test-feature',
            'goal_reached' => true
        ]);
    }

}