<?php

namespace App\Providers;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Policies\AttendanceCorrectionPolicy;
use App\Policies\AttendancePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        // 現時点ではサービス登録なし。
    }

    public function boot()
    {
        // 管理画面アクセス可否ゲート。
        Gate::define('access-admin', fn ($user) => (bool) $user->is_admin);
        // 勤怠本体のポリシーを関連付ける。
        Gate::policy(Attendance::class, AttendancePolicy::class);
        // 修正申請のポリシーを関連付ける。
        Gate::policy(AttendanceCorrection::class, AttendanceCorrectionPolicy::class);
    }
}
