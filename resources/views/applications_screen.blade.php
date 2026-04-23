@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/lists.css') }}">
@endsection

@section('content')
    @livewire('applications-screen', [
        'tab' => $tab ?? 'pending',
        'applications' => $applications,
        'isAdmin' => $isAdmin ?? false,
        'tabRoute' => $tabRoute ?? null,
        'detailRouteName' => $detailRouteName,
    ])
@endsection
