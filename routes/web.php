<?php

use App\Http\Controllers\AdminAttendController;
use App\Http\Controllers\AdminCorrectionController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CorrectionController;
use App\Http\Controllers\StampCorrectionListController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

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

    Route::get('/stamp_correction_request/list', [StampCorrectionListController::class, 'index'])
        ->name('stamp_correction_requests.list');
});

// User (email verified)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('attendance')->name('attendance.')->controller(AttendanceController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/check_in', 'checkIn')->name('check_in');
        Route::post('/check_out', 'checkOut')->name('check_out');
        Route::post('/break_in', 'breakIn')->name('break_in');
        Route::post('/break_out', 'breakOut')->name('break_out');
        Route::get('/list', 'list')->name('list');
        Route::get('/detail/date/{date}', 'detailByDate')
            ->where('date', '\d{4}-\d{2}-\d{2}')
            ->name('detail.date');
        Route::get('/detail/{attendance}', 'detail')->name('detail');
    });

    Route::controller(CorrectionController::class)->group(function () {
        Route::put('/attendance/request/{attendance}', 'store')->name('attendance.request');
        Route::get('/stamp_correction_request/{attendanceCorrection}', 'detail')
            ->whereNumber('attendanceCorrection')
            ->name('stamp_correction_request.detail');
    });
});

// Admin only
Route::middleware(['auth', 'can:access-admin'])->group(function () {
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::controller(AdminAttendController::class)->group(function () {
            Route::get('/attendance/list', 'index')->name('dashboard');
            Route::get('/attendance/{attendance}', 'detail')->name('attendance.detail');
            Route::put('/attendance/{attendance}', 'update')->name('attendance.update');
            Route::get('/staff/list', 'staff')->name('staff_list');
            Route::get('/attendance/staff/{user}', 'staffAttendances')->name('attendance.list');
            Route::get('/attendance/staff/{user}/csv', 'staffCsv')->name('attendance.list.csv');
        });
    });

    Route::controller(AdminCorrectionController::class)->group(function () {
        Route::get('/stamp_correction_request/approve/{attendanceCorrection}', 'detail')
            ->whereNumber('attendanceCorrection')
            ->name('admin.attendance.approve');
        Route::put('/stamp_correction_request/approve/{attendanceCorrection}', 'approve')
            ->whereNumber('attendanceCorrection')
            ->name('admin.attendance.approve.update');
    });
});
