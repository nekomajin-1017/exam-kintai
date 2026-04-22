@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/verify.css') }}">
@endsection

@section('content')
    <div class="verify-shell">
        <div class="verify-card">
            <p class="verify-text">
                <span class="verify-line">登録していただいたメールアドレスに認証メールを送付しました。</span>
                <span class="verify-line">メール認証を完了してください。</span>
            </p>

            <a class="verify-open-mail link-reset" href="{{ config('services.mail_ui_url') }}" target="_blank"
                rel="noopener noreferrer">認証はこちらから</a>

            @if (session('status') === 'verification-link-sent')
                <p class="status-message">認証メールを再送しました。受信ボックスを更新して確認してください。</p>
            @endif

            <form action="{{ route('verification.send') }}" method="post">
                @csrf
                <button class="verify-resend-link" type="submit">認証メールを再送信する</button>
                @error('email')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </form>
        </div>
    </div>
@endsection
