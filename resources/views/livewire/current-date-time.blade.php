{{-- 打刻状態と現在日時を表示する小コンポーネント。1秒ごとに再描画。 --}}
<div class="attendance-container" wire:poll.1s>
    <p class="status-badge">{{ $status }}</p>
    <p class="stamp-date">{{ $now->locale('ja')->isoFormat('Y年MM月DD日(ddd)') }}</p>
    <div class="stamp-time">{{ $now->format('H:i') }}</div>
</div>
