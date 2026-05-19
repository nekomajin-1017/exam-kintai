<div class="attendance-container" wire:poll.1s>
    <p class="status-badge">{{ $status }}</p>
    <p class="stamp-date">{{ $now->locale('ja')->isoFormat('Y年M月D日(ddd)') }}</p>
    <div class="stamp-time">{{ $now->format('H:i') }}</div>
</div>
