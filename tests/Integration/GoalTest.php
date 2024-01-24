<?php

namespace Tests\Integration;

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

}