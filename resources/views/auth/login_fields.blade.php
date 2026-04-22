<div class="form-card">
    <h1 class="form-title">{{ $title }}</h1>
    <form class="auth-form" action="{{ route($actionRoute ?? 'login') }}" method="post" novalidate>
        @csrf
        <x-form-field name="email" type="email" label="メールアドレス" />
        <x-form-field name="password" type="password" label="パスワード" :use-old="false" />
        <div class="auth-actions">
            <button class="button" type="submit">{{ $submitLabel }}</button>
        </div>
        @if (($showRegisterLink ?? false) === true)
            <p class="link-center"><a class="link-reset auth-switch-link" href="{{ route('register') }}">会員登録はこちら</a></p>
        @endif
    </form>
</div>
