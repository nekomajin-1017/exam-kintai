<?php

use App\Http\Controllers\AttendanceScreenController;
use App\Http\Controllers\CorrectionRequestController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

$stampActionMethods = [
    'check_in' => 'checkIn',
    'check_out' => 'checkOut',
    'break_in' => 'breakIn',
    'break_out' => 'breakOut',
];

Route::redirect('/', '/attendance');

// Guest only
Route::middleware('guest')->group(function () {
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    });
});

// Auth required (shared)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/stamp_correction_request/list', [CorrectionRequestController::class, 'list'])
        ->name('stamp_correction_requests.list');
});

// User (email verified)
Route::middleware(['auth', 'verified'])->group(function () use ($stampActionMethods) {
    Route::prefix('attendance')->name('attendance.')->controller(AttendanceScreenController::class)->group(function () use ($stampActionMethods) {
        Route::get('/', 'index')->name('index');
        foreach ($stampActionMethods as $path => $method) {
            Route::post("/{$path}", $method)->name($path);
        }
        Route::get('/list', 'userList')->name('list');
        Route::get('/detail/date/{date}', 'showUserDetailByDate')
            ->where('date', '\d{4}-\d{2}-\d{2}')
            ->name('detail.date');
        Route::get('/detail/{attendance}', 'userDetail')->name('detail');
    });

    Route::controller(CorrectionRequestController::class)->group(function () {
        Route::put('/attendance/request/{attendance}', 'store')->name('attendance.request');
        Route::get('/stamp_correction_request/{attendanceCorrection}', 'userDetail')
            ->whereNumber('attendanceCorrection')
            ->name('stamp_correction_request.detail');
    });
});

// Admin only
Route::middleware(['auth', 'can:access-admin'])->group(function () {
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::controller(AttendanceScreenController::class)->group(function () {
            Route::get('/attendance/list', 'adminDashboard')->name('dashboard');
            Route::get('/attendance/{attendance}', 'adminDetail')->name('attendance.detail');
            Route::put('/attendance/{attendance}', 'adminUpdate')->name('attendance.update');
            Route::get('/staff/list', 'adminStaff')->name('staff_list');
            Route::get('/attendance/staff/{user}', 'adminStaffList')->name('attendance.list');
            Route::get('/attendance/staff/{user}/csv', 'adminStaffCsv')->name('attendance.list.csv');
        });
    });

    Route::controller(CorrectionRequestController::class)->group(function () {
        Route::get('/stamp_correction_request/approve/{attendanceCorrection}', 'adminDetail')
            ->whereNumber('attendanceCorrection')
            ->name('admin.attendance.approve');
        Route::put('/stamp_correction_request/approve/{attendanceCorrection}', 'approve')
            ->whereNumber('attendanceCorrection')
            ->name('admin.attendance.approve.update');
    });
});
