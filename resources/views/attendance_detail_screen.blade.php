@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/details.css') }}">
@endsection

@section('content')
    @livewire('attendance-detail-screen', [
        'detailFields' => $detailFields ?? [],
        'readonly' => $readonly ?? false,
        'plainReadonly' => $plainReadonly ?? false,
        'formAction' => $formAction ?? null,
        'formMethod' => $formMethod ?? 'PUT',
        'submitLabel' => $submitLabel ?? null,
        'submitDisabled' => $submitDisabled ?? false,
        'statusMessage' => $statusMessage ?? null,
        'statusMessageClass' => $statusMessageClass ?? 'progress-message',
        'title' => $title ?? '勤怠詳細',
    ])
@endsection
