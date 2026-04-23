<?php

namespace App\Livewire;

use Livewire\Component;

class AttendanceDetailScreen extends Component
{
    // 入力欄定義（ラベル/値/エラー表示など）。
    public array $detailFields = [];

    // 表示/操作モード設定。
    public bool $readonly = false;
    public bool $plainReadonly = false;
    public ?string $formAction = null;
    public string $formMethod = 'PUT';
    public ?string $submitLabel = null;
    public bool $submitDisabled = false;
    public ?string $statusMessage = null;
    public string $statusMessageClass = 'progress-message';
    public string $title = '勤怠詳細';

    public function mount(
        array $detailFields = [],
        bool $readonly = false,
        bool $plainReadonly = false,
        ?string $formAction = null,
        string $formMethod = 'PUT',
        ?string $submitLabel = null,
        bool $submitDisabled = false,
        ?string $statusMessage = null,
        string $statusMessageClass = 'progress-message',
        string $title = '勤怠詳細',
    ): void {
        // コントローラから渡された表示データを保持し、Blade 側で描画する。
        $this->detailFields = $detailFields;
        $this->readonly = $readonly;
        $this->plainReadonly = $plainReadonly;
        $this->formAction = $formAction;
        $this->formMethod = $formMethod;
        $this->submitLabel = $submitLabel;
        $this->submitDisabled = $submitDisabled;
        $this->statusMessage = $statusMessage;
        $this->statusMessageClass = $statusMessageClass;
        $this->title = $title;
    }

    public function render()
    {
        // 詳細ページの見た目は partial へ委譲する。
        return view('livewire.attendance-detail-screen');
    }
}
