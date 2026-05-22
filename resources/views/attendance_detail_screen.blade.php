@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/details.css') }}">
@endsection

@section('content')
    @include('partials.attendance_detail_page', [
        'detailFields' => $detailFields ?? [],
        'formAction' => $formAction ?? null,
        'formMethod' => $formMethod ?? 'PUT',
        'submitLabel' => $submitLabel ?? null,
        'submitDisabled' => $submitDisabled ?? false,
        'statusMessage' => $statusMessage ?? null,
        'title' => $title ?? '勤怠詳細',
    ])
@endsection
