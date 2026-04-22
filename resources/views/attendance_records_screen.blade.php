@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/lists.css') }}">
@endsection

@section('content')
    <h1 class="title">{{ $title ?? '勤怠一覧' }}</h1>
    <x-pagination
        :previous-url="$previousUrl"
        :next-url="$nextUrl"
        :current-label="$currentLabel"
        :previous-label="$previousLabel"
        :next-label="$nextLabel"
    />

    @include('partials.attendance_records_table', [
        'attendances' => $attendances,
        'firstColumnType' => $firstColumnType ?? 'date',
        'detailRouteName' => $detailRouteName,
        'allowMissingDetail' => $allowMissingDetail ?? false,
        ])

    @if (!empty($csvDownloadUrl))
        <div class="csv-download">
            <a class="csv-download-button" href="{{ $csvDownloadUrl }}">CSV出力</a>
        </div>
    @endif
@endsection
