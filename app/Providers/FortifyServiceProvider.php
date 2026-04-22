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
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    public function boot()
    {
        Fortify::loginView(function ($request) {
            return $request->routeIs('admin.login') ? view('auth.admin_login') : view('auth.login');
        });
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::verifyEmailView(fn () => view('auth.verify'));

        Fortify::authenticateUsing(function ($request) {
            $loginRequest = new LoginRequest;
            Validator::make($request->all(), $loginRequest->rules(), $loginRequest->messages())->validate();

            $user = User::where('email', $request->email)->first();
            if (! $user || ! Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    Fortify::username() => ['ログイン情報が登録されていません'],
                ]);
            }
            if ($request->routeIs('admin.login.store') && ! (bool) $user->is_admin) {
                throw ValidationException::withMessages([
                    Fortify::username() => ['管理者アカウントではありません'],
                ]);
            }
            return $user;
        });

        Fortify::createUsersUsing(CreateNewUser::class);

        RateLimiter::for('login', function ($request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
