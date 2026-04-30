<?php

namespace Tests\Feature;

use App\Constants\AttendanceStatusCode;
use App\Models\AttendanceCorrection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserAttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    // [ID:11] 出勤時間が退勤時間より後の場合に「出勤時間が不適切な値です」が表示される
    public function validation_error_is_shown_when_start_time_is_after_end_time(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $attendance = $this->createAttendance($user, '2026-04-24', [
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
            'remarks' => '初期備考',
        ]);
        /** @var User $user */
        $this->actingAs($user);

        $response = $this->put(route('attendance.request', $attendance), [
            'start_time' => '19:00',
            'end_time' => '18:00',
            'break_start_at' => [''],
            'break_end_at' => [''],
            'reason' => '修正理由',
        ]);

        $response->assertSessionHasErrors('start_time');
        $this->assertSame('出勤時間が不適切な値です', session('errors')->first('start_time'));
    }

    #[Test]
    // [ID:11] 休憩開始時間が退勤時間より後の場合にバリデーションメッセージが表示される
    public function validation_error_is_shown_when_break_start_is_after_end_time(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $attendance = $this->createAttendance($user, '2026-04-24', [
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
            'remarks' => '初期備考',
        ]);
        /** @var User $user */
        $this->actingAs($user);

        $response = $this->put(route('attendance.request', $attendance), [
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_start_at' => ['19:00'],
            'break_end_at' => ['19:30'],
            'reason' => '修正理由',
        ]);

        $response->assertSessionHasErrors('break_start_at.0');
        $this->assertSame('休憩時間が不適切な値です', session('errors')->first('break_start_at.0'));
    }

    #[Test]
    // [ID:11] 休憩終了時間が退勤時間より後の場合にバリデーションメッセージが表示される
    public function validation_error_is_shown_when_break_end_is_after_end_time(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $attendance = $this->createAttendance($user, '2026-04-24', [
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
            'remarks' => '初期備考',
        ]);
        /** @var User $user */
        $this->actingAs($user);

        $response = $this->put(route('attendance.request', $attendance), [
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_start_at' => ['12:00'],
            'break_end_at' => ['19:00'],
            'reason' => '修正理由',
        ]);

        $response->assertSessionHasErrors('break_end_at.0');
        $this->assertSame('休憩時間もしくは退勤時間が不適切な値です', session('errors')->first('break_end_at.0'));
    }

    #[Test]
    // [ID:11] 備考未入力時にバリデーションメッセージが表示される
    public function validation_error_is_shown_when_reason_is_empty(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $attendance = $this->createAttendance($user, '2026-04-24', [
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
            'remarks' => '初期備考',
        ]);
        /** @var User $user */
        $this->actingAs($user);

        $response = $this->put(route('attendance.request', $attendance), [
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_start_at' => ['12:00'],
            'break_end_at' => ['13:00'],
            'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertSame('備考を記入してください', session('errors')->first('reason'));
    }

    #[Test]
    // [ID:11] 修正申請が作成され、管理者の承認画面と申請一覧に表示される
    public function correction_request_is_created_and_visible_on_admin_screens(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);
        $attendance = $this->createAttendance($user, '2026-04-24', [
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
            'remarks' => '初期備考',
        ]);
        /** @var User $user */
        $this->actingAs($user);
        $this->put(route('attendance.request', $attendance), [
            'start_time' => '08:30',
            'end_time' => '17:30',
            'break_start_at' => ['12:00'],
            'break_end_at' => ['13:00'],
            'reason' => '電車遅延',
        ]);

        $correction = AttendanceCorrection::query()->latest('id')->firstOrFail();
        $this->assertSame((int) $user->id, (int) $correction->request_user_id);
        /** @var User $admin */
        $this->actingAs($admin);
        $listResponse = $this->get(route('stamp_correction_requests.list', ['tab' => 'pending']));
        $listResponse->assertOk();
        $listResponse->assertSeeText('電車遅延');
        $listResponse->assertSeeText($user->name);

        $detailResponse = $this->get(route('admin.attendance.approve', $correction));
        $detailResponse->assertOk();
        $detailResponse->assertSeeText('承認');
    }

    #[Test]
    // [ID:11] 承認待ちタブにログインユーザーの申請がすべて表示される
    public function all_requests_of_login_user_are_listed_in_pending_tab(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $attendance1 = $this->createAttendance($user, '2026-04-10', [
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
            'remarks' => '初期備考',
        ]);
        $attendance2 = $this->createAttendance($user, '2026-04-11', [
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
            'remarks' => '初期備考',
        ]);
        /** @var User $user */
        $this->actingAs($user);
        $this->put(route('attendance.request', $attendance1), [
            'start_time' => '08:30',
            'end_time' => '17:30',
            'break_start_at' => ['12:00'],
            'break_end_at' => ['13:00'],
            'reason' => '申請A',
        ]);
        $this->put(route('attendance.request', $attendance2), [
            'start_time' => '08:40',
            'end_time' => '17:40',
            'break_start_at' => ['12:10'],
            'break_end_at' => ['13:10'],
            'reason' => '申請B',
        ]);

        $response = $this->get(route('stamp_correction_requests.list', ['tab' => 'pending']));
        $response->assertOk();
        $response->assertSeeText('申請A');
        $response->assertSeeText('申請B');
    }

    #[Test]
    // [ID:11] 承認済みタブに管理者承認済み申請が表示される
    public function approved_requests_are_listed_in_approved_tab(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);
        $attendance = $this->createAttendance($user, '2026-04-24', [
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
            'remarks' => '初期備考',
        ]);
        /** @var User $user */
        $this->actingAs($user);
        $this->put(route('attendance.request', $attendance), [
            'start_time' => '08:30',
            'end_time' => '17:30',
            'break_start_at' => ['12:00'],
            'break_end_at' => ['13:00'],
            'reason' => '承認対象',
        ]);

        $correction = AttendanceCorrection::query()->latest('id')->firstOrFail();
        /** @var User $admin */
        $this->actingAs($admin);
        $approveResponse = $this->put(route('admin.attendance.approve.update', $correction));
        $approveResponse->assertRedirect(route('admin.attendance.approve', $correction));

        $this->actingAs($user);
        $response = $this->get(route('stamp_correction_requests.list', ['tab' => 'approved']));
        $response->assertOk();
        $response->assertSeeText('承認対象');
        $response->assertSeeText('承認済み');
    }

    #[Test]
    // [ID:11] 申請一覧の詳細ボタンから勤怠詳細画面へ遷移できる
    public function detail_link_in_application_list_navigates_to_correction_detail_screen(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $attendance = $this->createAttendance($user, '2026-04-24', [
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
            'remarks' => '初期備考',
        ]);
        /** @var User $user */
        $this->actingAs($user);
        $this->put(route('attendance.request', $attendance), [
            'start_time' => '08:30',
            'end_time' => '17:30',
            'break_start_at' => ['12:00'],
            'break_end_at' => ['13:00'],
            'reason' => '詳細確認',
        ]);

        $correction = AttendanceCorrection::query()->latest('id')->firstOrFail();
        $listResponse = $this->get(route('stamp_correction_requests.list', ['tab' => 'pending']));
        $listResponse->assertOk();
        $listResponse->assertSee('href="'.route('stamp_correction_request.detail', $correction).'"', false);

        $detailResponse = $this->get(route('stamp_correction_request.detail', $correction));
        $detailResponse->assertOk();
        $detailResponse->assertSeeText('勤怠詳細');
    }
}
