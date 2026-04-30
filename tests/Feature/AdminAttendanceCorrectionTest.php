<?php

namespace Tests\Feature;

use App\Constants\AttendanceStatusCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminAttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    // [ID:13] 勤怠詳細画面に表示されるデータが選択した勤怠情報と一致する
    public function selected_attendance_data_is_displayed_on_admin_detail_screen(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false, 'name' => '山田 太郎']);
        $attendance = $this->createAttendance($user, '2026-04-24', [
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
            'remarks' => '通常勤務',
        ]);
        $attendance->breaks()->create([
            'break_start_at' => '2026-04-24 12:00:00',
            'break_end_at' => '2026-04-24 13:00:00',
        ]);

        /** @var User $admin */
        $this->actingAs($admin);
        $response = $this->get(route('admin.attendance.detail', $attendance));

        $response->assertOk();
        $response->assertSeeText('山田 太郎');
        $response->assertSeeText('2026年04月24日');
        $response->assertSee('name="start_time"', false);
        $response->assertSee('value="09:00"', false);
        $response->assertSee('name="end_time"', false);
        $response->assertSee('value="18:00"', false);
        $response->assertSee('name="break_start_at[]"', false);
        $response->assertSee('value="12:00"', false);
        $response->assertSee('name="break_end_at[]"', false);
        $response->assertSee('value="13:00"', false);
        $response->assertSeeText('通常勤務');
    }

    #[Test]
    // [ID:13] 出勤時間が退勤時間より後の場合にエラーメッセージが表示される
    public function validation_error_is_shown_when_start_time_is_after_end_time_for_admin_update(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);
        $attendance = $this->createAttendance($user, '2026-04-24', [
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
            'remarks' => '初期備考',
        ]);
        /** @var User $admin */
        $this->actingAs($admin);

        $response = $this->put(route('admin.attendance.update', $attendance), [
            'start_time' => '19:00',
            'end_time' => '18:00',
            'break_start_at' => [''],
            'break_end_at' => [''],
            'reason' => '管理者修正',
        ]);

        $response->assertSessionHasErrors('start_time');
        $this->assertSame('出勤時間もしくは退勤時間が不適切な値です', session('errors')->first('start_time'));
    }

    #[Test]
    // [ID:13] 休憩開始時間が退勤時間より後の場合にエラーメッセージが表示される
    public function validation_error_is_shown_when_break_start_is_after_end_time_for_admin_update(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);
        $attendance = $this->createAttendance($user, '2026-04-24', [
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
            'remarks' => '初期備考',
        ]);
        /** @var User $admin */
        $this->actingAs($admin);

        $response = $this->put(route('admin.attendance.update', $attendance), [
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_start_at' => ['19:00'],
            'break_end_at' => ['19:30'],
            'reason' => '管理者修正',
        ]);

        $response->assertSessionHasErrors('break_start_at.0');
        $this->assertSame('休憩時間が不適切な値です', session('errors')->first('break_start_at.0'));
    }

    #[Test]
    // [ID:13] 休憩終了時間が退勤時間より後の場合にエラーメッセージが表示される
    public function validation_error_is_shown_when_break_end_is_after_end_time_for_admin_update(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);
        $attendance = $this->createAttendance($user, '2026-04-24', [
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
            'remarks' => '初期備考',
        ]);
        /** @var User $admin */
        $this->actingAs($admin);

        $response = $this->put(route('admin.attendance.update', $attendance), [
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_start_at' => ['12:00'],
            'break_end_at' => ['19:00'],
            'reason' => '管理者修正',
        ]);

        $response->assertSessionHasErrors('break_end_at.0');
        $this->assertSame('休憩時間もしくは退勤時間が不適切な値です', session('errors')->first('break_end_at.0'));
    }

    #[Test]
    // [ID:13] 備考欄が未入力の場合にエラーメッセージが表示される
    public function validation_error_is_shown_when_reason_is_empty_for_admin_update(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);
        $attendance = $this->createAttendance($user, '2026-04-24', [
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
            'remarks' => '初期備考',
        ]);
        /** @var User $admin */
        $this->actingAs($admin);

        $response = $this->put(route('admin.attendance.update', $attendance), [
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_start_at' => ['12:00'],
            'break_end_at' => ['13:00'],
            'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertSame('備考を記入してください', session('errors')->first('reason'));
    }
}
