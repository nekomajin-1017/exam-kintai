@php
    $isAdmin = $isAdmin ?? false;
    $emptyColspan = $isAdmin ? 6 : 5;
    $statusLabels = [
        'pending' => '承認待ち',
        'approved' => '承認済み',
    ];
@endphp

<x-list>
    <x-slot name="header">
        <th class="list-table-header-cell list-table-cell-nowrap">状態</th>
        @if ($isAdmin)
            <th class="list-table-header-cell">名前</th>
        @endif
        <th class="list-table-header-cell">対象日</th>
        <th class="list-table-header-cell">申請理由</th>
        <th class="list-table-header-cell">申請日時</th>
        <th class="list-table-header-cell">詳細</th>
    </x-slot>

    <x-slot name="body">
        @forelse ($applications as $application)
            <tr class="list-table-row">
                <td class="list-table-cell list-table-cell-nowrap">{{ $statusLabels[$application->approval_status_code] ?? '未設定' }}</td>
                @if ($isAdmin)
                    <td class="list-table-cell">{{ $application->requestUser->name ?? 'ー' }}</td>
                @endif
                <td class="list-table-cell">{{ \Carbon\Carbon::parse($application->attendance->work_date)->locale('ja')->isoFormat('Y年MM月DD日') }}</td>
                <td class="list-table-cell">{{ $application->reason ?? 'ー' }}</td>
                <td class="list-table-cell">{{ $application->created_at?->locale('ja')->isoFormat('Y年MM月DD日') }}</td>
                <td class="list-table-cell"><a class="detail-button" href="{{ route($detailRouteName, $application) }}">詳細</a></td>
            </tr>
        @empty
            <tr class="list-table-row">
                <td class="list-table-cell" colspan="{{ $emptyColspan }}">申請データがありません。</td>
            </tr>
        @endforelse
    </x-slot>
</x-list>
