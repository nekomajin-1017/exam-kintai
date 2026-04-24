<div>
    {{-- 画面タイトル（一般/管理者で可変）。 --}}
    <h1 class="title">{{ $title }}</h1>
    {{-- 月移動用の共通ページネーション。 --}}
    <x-pagination
        :previous-url="$previousUrl"
        :next-url="$nextUrl"
        :current-label="$currentLabel"
        :previous-label="$previousLabel"
        :next-label="$nextLabel"
    />

    {{-- 勤怠一覧テーブル。列形式や詳細遷移ルートは呼び出し元から渡される。 --}}
    @include('partials.attendance_records_table', [
        'attendances' => $attendances,
        'firstColumnType' => $firstColumnType,
        'detailRouteName' => $detailRouteName,
        'allowMissingDetail' => $allowMissingDetail,
        'missingDetailRouteName' => $missingDetailRouteName,
        'missingDetailRouteParams' => $missingDetailRouteParams,
    ])

    {{-- 管理者向けCSV出力。URLがある場合のみ表示する。 --}}
    @if (! empty($csvDownloadUrl))
        <div class="csv-download">
            <a class="csv-download-button" href="{{ $csvDownloadUrl }}">CSV出力</a>
        </div>
    @endif
</div>
