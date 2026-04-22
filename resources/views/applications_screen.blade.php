@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/lists.css') }}">
@endsection

@section('content')
    <h1 class="title">申請一覧</h1>

    @include('partials.layout.tab', [
        'tabRoute' => $tabRoute ?? null,
    ])

    @include('partials.application_list_table', [
        'applications' => $applications,
        'isAdmin' => $isAdmin ?? false,
        'detailRouteName' => $detailRouteName,
    ])
@endsection
