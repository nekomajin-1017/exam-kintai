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
    public function register() {}

    public function boot()
    {

        Gate::define('access-admin', fn ($user) => (bool) $user->is_admin);

        Gate::policy(Attendance::class, AttendancePolicy::class);

        Gate::policy(AttendanceCorrection::class, AttendanceCorrectionPolicy::class);
    }
}
