<?php

namespace App\Livewire;

use Illuminate\Support\Collection;
use Livewire\Component;

class AttendanceRecordsScreen extends Component
{
    // 見出し（一般/管理者で差し替え可能）。
    public string $title = '勤怠一覧';

    /** @var \Illuminate\Support\Collection<int, mixed> */
    // 一覧表示対象の勤怠データ。
    public Collection $attendances;

    // 月移動UIと表示ラベル。
    public string $previousUrl = '';
    public string $nextUrl = '';
    public string $currentLabel = '';
    public string $previousLabel = '';
    public string $nextLabel = '';

    // テーブル描画オプション。
    public string $firstColumnType = 'date';
    public string $detailRouteName = 'attendance.detail';
    public bool $allowMissingDetail = false;

    // 管理者画面でのみ利用するCSVダウンロードURL。
    public ?string $csvDownloadUrl = null;

    public function mount(
        string $title = '勤怠一覧',
        mixed $attendances = null,
        string $previousUrl = '',
        string $nextUrl = '',
        string $currentLabel = '',
        string $previousLabel = '',
        string $nextLabel = '',
        string $firstColumnType = 'date',
        string $detailRouteName = 'attendance.detail',
        bool $allowMissingDetail = false,
        ?string $csvDownloadUrl = null,
    ): void {
        // mount 入力を描画しやすい形へ整形して保持する。
        $this->title = $title;
        $this->attendances = $attendances instanceof Collection ? $attendances : collect($attendances ?? []);
        $this->previousUrl = $previousUrl;
        $this->nextUrl = $nextUrl;
        $this->currentLabel = $currentLabel;
        $this->previousLabel = $previousLabel;
        $this->nextLabel = $nextLabel;
        $this->firstColumnType = $firstColumnType;
        $this->detailRouteName = $detailRouteName;
        $this->allowMissingDetail = $allowMissingDetail;
        $this->csvDownloadUrl = $csvDownloadUrl;
    }

    public function render()
    {
        // 一覧画面のBladeへ委譲する。
        return view('livewire.attendance-records-screen');
    }
}
