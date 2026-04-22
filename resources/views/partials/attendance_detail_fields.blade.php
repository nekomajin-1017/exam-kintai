<table class="detail-table">
    <tr class="table-row">
        <th class="table-header">名前</th>
        <td class="table-data">{{ $fields['name'] }}</td>
    </tr>
    <tr class="table-row">
        <th class="table-header">日付</th>
        <td class="table-data">{{ $fields['workDateLabel'] }}</td>
    </tr>
    <tr class="table-row">
        <th class="table-header">出勤退勤</th>
        <td class="table-data">
            <div class="time-range">
                @if ($fields['isPlainReadonly'])
                    <span class="time-static-value">{{ $fields['startTime'] ?: 'ー' }}</span>
                @else
                    <div class="time-input-col">
                        <input class="time-input" type="text" name="start_time"
                            value="{{ $fields['startTime'] }}" {{ $fields['readonlyAttr'] }}>
                        @error('start_time')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
                <span>〜</span>
                @if ($fields['isPlainReadonly'])
                    <span class="time-static-value">{{ $fields['endTime'] ?: 'ー' }}</span>
                @else
                    <div class="time-input-col">
                        <input class="time-input" type="text" name="end_time"
                            value="{{ $fields['endTime'] }}" {{ $fields['readonlyAttr'] }}>
                        @error('end_time')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </div>
        </td>
    </tr>
    @foreach ($fields['breakRows'] as $index => $row)
        <tr class="table-row">
            <th class="table-header">休憩時間{{ $index + 1 }}</th>
            <td class="table-data">
                <div class="time-range">
                    @if ($fields['isPlainReadonly'])
                        <span class="time-static-value">{{ $row['start'] ?: 'ー' }}</span>
                    @else
                        <div class="time-input-col">
                            <input class="time-input" type="text" name="break_start_at[]" value="{{ $row['start'] }}" {{ $fields['readonlyAttr'] }}>
                            @error("break_start_at.{$index}")
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                    <span>〜</span>
                    @if ($fields['isPlainReadonly'])
                        <span class="time-static-value">{{ $row['end'] ?: 'ー' }}</span>
                    @else
                        <div class="time-input-col">
                            <input class="time-input" type="text" name="break_end_at[]" value="{{ $row['end'] }}" {{ $fields['readonlyAttr'] }}>
                            @error("break_end_at.{$index}")
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                </div>
            </td>
        </tr>
    @endforeach
    <tr class="table-row">
        <th class="table-header">備考</th>
        <td class="table-data">
            @if ($fields['isPlainReadonly'])
                <div class="reason-static">{{ $fields['reason'] ?: 'ー' }}</div>
            @else
                <div class="reason-input-col">
                    <textarea class="reason-input" name="reason" maxlength="255" {{ $fields['readonlyAttr'] }}>{{ $fields['reason'] }}</textarea>
                    @error('reason')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>
            @endif
        </td>
    </tr>
</table>
