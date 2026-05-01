<?php

namespace App\Livewire;

use Livewire\Component;

class CurrentDateTime extends Component
{
    public $status = '';

    public function mount($status)
    {
        $this->status = $status;
    }

    public function render()
    {
        return view('livewire.current-date-time', [
            'now' => now(),
        ]);
    }
}
