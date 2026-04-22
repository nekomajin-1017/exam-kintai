@extends('layouts.app')


@section('css')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endsection

@section('content')
    @include('auth.login_fields', [
        'title' => '管理者ログイン',
        'submitLabel' => '管理者ログインする',
        'showRegisterLink' => false,
        'actionRoute' => 'admin.login.store',
    ])
@endsection
