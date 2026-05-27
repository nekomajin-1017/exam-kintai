@extends('layouts.app')


@section('css')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endsection

@section('content')
    <div class="form-card">
        <h1 class="form-title">会員登録</h1>
        <form class="auth-form" data-register-form action="{{ route('register') }}" method="post" novalidate>
            @csrf
            <x-form-field name="name" label="名前" />
            <x-form-field name="email" type="email" label="メールアドレス" />
            <x-form-field name="password" type="password" label="パスワード" :use-old="false" />
            <x-form-field name="password_confirmation" type="password" label="パスワード確認" :use-old="false" />
            <div class="auth-actions">
                <button class="button" type="submit" data-register-submit>登録する</button>
            </div>
            <p class="link-center"><a class="link-reset auth-switch-link" href="{{ route('login') }}">ログインはこちら</a></p>
        </form>
    </div>

    <script>
        (function () {
            const form = document.querySelector('[data-register-form]');
            if (!form) return;

            form.addEventListener('submit', function () {
                const submitButton = form.querySelector('[data-register-submit]');
                if (!submitButton) return;

                submitButton.disabled = true;
            });
        })();
    </script>
@endsection
