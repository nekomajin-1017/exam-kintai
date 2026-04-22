@php
$tabRoute = $tabRoute ?? 'stamp_correction_requests.list';
$activeTab = $tab ?? 'pending';
@endphp

<nav class="tabs">
    <a class="tab {{ $activeTab === 'pending' ? 'is-active' : '' }}" href="{{ route($tabRoute, ['tab' => 'pending']) }}">承認待ち</a>
    <a class="tab {{ $activeTab === 'approved' ? 'is-active' : '' }}" href="{{ route($tabRoute, ['tab' => 'approved']) }}">承認済み</a>
</nav>
