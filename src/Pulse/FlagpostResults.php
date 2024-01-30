<?php

namespace Bastuijnman\Flagpost\Pulse;

use Bastuijnman\Flagpost\Goal;
use Illuminate\Contracts\Support\Renderable;
use Laravel\Pulse\Livewire\Card;
class FlagpostResults extends Card
{

    public string $type = 'total';

    public string $feature;

    public function render(): Renderable
    {   
        $results = match ($this->type) {
            'total' => Goal::results($this->feature),
            'timeseries' => Goal::timeseries($this->feature, $this->periodAsInterval())
        };
        
        return view('flagpost::results', compact('results'));
    }
}
