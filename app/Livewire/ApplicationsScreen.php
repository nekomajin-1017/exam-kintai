<?php

namespace App\Livewire;

use Illuminate\Support\Collection;
use Livewire\Component;

class ApplicationsScreen extends Component
{
    // タブ状態（承認待ち / 承認済み）を保持する。
    public string $tab = 'pending';

    /** @var \Illuminate\Support\Collection<int, mixed> */
    // 申請一覧データ（コントローラから渡される）。
    public Collection $applications;

    // 画面表示モード（管理者/一般）とルーティング設定。
    public bool $isAdmin = false;
    public ?string $tabRoute = null;
    public string $detailRouteName = 'stamp_correction_request.detail';

    public function mount(
        string $tab = 'pending',
        mixed $applications = null,
        bool $isAdmin = false,
        ?string $tabRoute = null,
        string $detailRouteName = 'stamp_correction_request.detail',
    ): void {
        // mount は初期表示時の入力を受け取り、描画用プロパティに正規化して保持する。
        $this->tab = $tab;
        $this->applications = $applications instanceof Collection ? $applications : collect($applications ?? []);
        $this->isAdmin = $isAdmin;
        $this->tabRoute = $tabRoute;
        $this->detailRouteName = $detailRouteName;
    }

    public function render()
    {
        // 表示テンプレートのみを返し、業務ロジックは持たない。
        return view('livewire.applications-screen');
    }
}
