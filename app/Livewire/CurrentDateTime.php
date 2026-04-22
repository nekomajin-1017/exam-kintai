<?php

namespace App\Livewire;

use Livewire\Component;

class CurrentDateTime extends Component
{
    public string $status = '';

    public function mount(string $status): void
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
