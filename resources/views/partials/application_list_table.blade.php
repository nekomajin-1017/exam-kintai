@php
    $isAdmin = auth()->user()?->can('admin') ?? false;
    $emptyColspan = $isAdmin ? 6 : 5;
    $statusLabels = [
        'pending' => '承認待ち',
        'approved' => '承認済み',
    ];
@endphp

<div class="list-table-container">
    <table class="list-table">
        <thead class="list-table-header">
            <tr class="list-table-header-row">
                <th class="list-table-header-cell list-table-cell-nowrap">状態</th>
                @if ($isAdmin)
                    <th class="list-table-header-cell">名前</th>
                @endif
                <th class="list-table-header-cell">対象日</th>
                <th class="list-table-header-cell">申請理由</th>
                <th class="list-table-header-cell">申請日時</th>
                <th class="list-table-header-cell">詳細</th>
            </tr>
        </thead>
        <tbody class="list-table-body">
            @forelse ($correctionRequests as $application)
                <tr class="list-table-row">
                    <td class="list-table-cell list-table-cell-nowrap">{{ $statusLabels[$application->approval_status_code] ?? '未設定' }}</td>
                    @if ($isAdmin)
                        <td class="list-table-cell">{{ $application->requestUser->name ?? '' }}</td>
                    @endif
                    <td class="list-table-cell">{{ \Carbon\Carbon::parse($application->attendance->work_date)->locale('ja')->isoFormat('Y年M月D日') }}</td>
                    <td class="list-table-cell">{{ $application->reason ?? '' }}</td>
                    <td class="list-table-cell">{{ $application->created_at?->locale('ja')->isoFormat('Y年M月D日') }}</td>
                    <td class="list-table-cell">
                        @can('admin')
                            <a class="detail-button" href="{{ route('admin.attendance.approve', $application) }}">詳細</a>
                        @else
                            <a class="detail-button" href="{{ route('stamp_correction_request.detail', $application) }}">詳細</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr class="list-table-row">
                    <td class="list-table-cell" colspan="{{ $emptyColspan }}">申請データがありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
