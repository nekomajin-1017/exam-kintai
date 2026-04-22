@extends('layouts.app')


@section('css')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endsection
@section('content')
    @include('auth.login_fields', [
        'title' => 'ログイン',
        'submitLabel' => 'ログインする',
        'showRegisterLink' => true,
    ])
@endsection
