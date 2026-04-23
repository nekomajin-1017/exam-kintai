<?php

namespace App\Livewire;

use Livewire\Component;

class CurrentDateTime extends Component
{
    // 打刻状態ラベル（例: 勤務中 / 休憩中）。
    public string $status = '';

    public function mount(string $status): void
    {
        // 初期表示時に状態文字列を受け取る。
        $this->status = $status;
    }

    public function render()
    {
        // 現在時刻は毎秒ポーリングで再描画される。
        return view('livewire.current-date-time', [
            'now' => now(),
        ]);
    }
}
