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

    public function test_it_should_be_albe_to_reach_goal_with_custom_scope()
    {
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
            'goal_reached' => true
        ]);

        // Scope 1 should not have been set to be reached
        $this->assertDatabaseMissing('features', [
            'name' => 'my-test-feature',
            'scope' => Feature::serializeScope('custom-scope-1'),
            'goal_reached' => true
        ]);
    }

}