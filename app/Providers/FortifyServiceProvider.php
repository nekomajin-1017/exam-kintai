<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Requests\LoginRequest;
use App\Http\Responses\LoginResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register()
    {
        // ログイン後リダイレクトを独自実装へ差し替える。
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    public function boot()
    {
        // ルートに応じてログインビューを切り替える。
        Fortify::loginView(function ($request) {
            return $request->routeIs('admin.login') ? view('auth.admin_login') : view('auth.login');
        });
        // 登録/認証案内ビューを指定する。
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::verifyEmailView(fn () => view('auth.verify'));

        Fortify::authenticateUsing(function ($request) {
            // 事前に入力の基本バリデーションを実行する。
            $loginRequest = new LoginRequest;
            Validator::make($request->all(), $loginRequest->rules(), $loginRequest->messages())->validate();

            // ユーザー存在とパスワード一致を確認する。
            $user = User::where('email', $request->email)->first();
            if (! $user || ! Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    Fortify::username() => ['ログイン情報が登録されていません'],
                ]);
            }
            // 管理者ログイン時は管理者フラグ必須。
            if ($request->routeIs('admin.login.store') && ! (bool) $user->is_admin) {
                throw ValidationException::withMessages([
                    Fortify::username() => ['管理者アカウントではありません'],
                ]);
            }
            return $user;
        });

        // 会員登録処理を独自アクションへ紐付ける。
        Fortify::createUsersUsing(CreateNewUser::class);

        // ログイン試行のレート制限。
        RateLimiter::for('login', function ($request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
