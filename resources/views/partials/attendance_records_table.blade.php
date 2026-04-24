@php
    $firstColumnType = $firstColumnType ?? 'date';
    $firstColumnLabel = $firstColumnType === 'name' ? '名前' : '日付';
    $allowMissingDetail = $allowMissingDetail ?? false;
    $missingDetailRouteName = $missingDetailRouteName ?? 'attendance.detail.date';
    $missingDetailRouteParams = $missingDetailRouteParams ?? [];
@endphp

<div class="attendance-list-wrap">
    <x-list>
        <x-slot name="header">
            <th class="list-table-header-cell attendance-list-first-col">{{ $firstColumnLabel }}</th>
            <th class="list-table-header-cell">出勤</th>
            <th class="list-table-header-cell">退勤</th>
            <th class="list-table-header-cell">休憩</th>
            <th class="list-table-header-cell">合計</th>
            <th class="list-table-header-cell">詳細</th>
        </x-slot>

        <x-slot name="body">
            @foreach ($attendances as $attendance)
                @php
                    $breakSeconds = (int) ($attendance->calculated_break_seconds ?? 0);
                    $totalSeconds = (int) ($attendance->calculated_total_seconds ?? 0);
                    $firstCellValue = $firstColumnType === 'name'
                        ? ($attendance->user->name ?? 'ー')
                        : \Carbon\Carbon::parse($attendance->work_date)->locale('ja')->isoFormat('Y年MM月DD日(ddd)');
                @endphp
                <tr class="list-table-row">
                    <td class="list-table-cell attendance-list-first-col">{{ $firstCellValue }}</td>
                    <td class="list-table-cell">{{ $attendance->check_in_at ? \Carbon\Carbon::parse($attendance->check_in_at)->format('H:i') : 'ー' }}</td>
                    <td class="list-table-cell">{{ $attendance->check_out_at ? \Carbon\Carbon::parse($attendance->check_out_at)->format('H:i') : 'ー' }}</td>
                    <td class="list-table-cell">{{ $attendance->check_in_at ? App\Helpers\TimeHelper::formatSeconds($breakSeconds) : 'ー' }}</td>
                    <td class="list-table-cell">{{ ($attendance->check_in_at && $attendance->check_out_at) ? App\Helpers\TimeHelper::formatSeconds($totalSeconds) : 'ー' }}</td>
                    <td class="list-table-cell">
                        @if ($allowMissingDetail && !$attendance->exists)
                            <a class="detail-button" href="{{ route($missingDetailRouteName, array_merge($missingDetailRouteParams, ['date' => \Carbon\Carbon::parse($attendance->work_date)->toDateString()])) }}">詳細</a>
                        @else
                            <a class="detail-button" href="{{ route($detailRouteName, $attendance) }}">詳細</a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-slot>
    </x-list>
</div>
