@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/lists.css') }}">
@endsection

@section('content')
    @livewire('attendance-records-screen', [
        'title' => $title ?? '勤怠一覧',
        'attendances' => $attendances,
        'previousUrl' => $previousUrl,
        'nextUrl' => $nextUrl,
        'currentLabel' => $currentLabel,
        'previousLabel' => $previousLabel,
        'nextLabel' => $nextLabel,
        'firstColumnType' => $firstColumnType ?? 'date',
        'detailRouteName' => $detailRouteName,
        'allowMissingDetail' => $allowMissingDetail ?? false,
        'missingDetailRouteName' => $missingDetailRouteName ?? 'attendance.detail.date',
        'missingDetailRouteParams' => $missingDetailRouteParams ?? [],
        'csvDownloadUrl' => $csvDownloadUrl ?? null,
    ])
@endsection
