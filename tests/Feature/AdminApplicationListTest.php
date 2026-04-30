<?php

namespace Tests\Feature;

use App\Constants\ApprovalStatusCode;
use App\Models\AttendanceCorrection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminApplicationListTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    // [ID:15] 承認待ちの修正申請が全て表示されている
    public function all_pending_correction_requests_are_displayed_for_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $userA = User::factory()->create(['is_admin' => false, 'name' => '申請者A']);
        $userB = User::factory()->create(['is_admin' => false, 'name' => '申請者B']);

        $attendanceA = $this->createAttendance($userA, '2026-04-24');
        $attendanceB = $this->createAttendance($userB, '2026-04-25');

        AttendanceCorrection::create([
            'attendance_id' => $attendanceA->id,
            'request_user_id' => $userA->id,
            'requested_check_in_at' => '2026-04-24 08:30:00',
            'requested_check_out_at' => '2026-04-24 17:30:00',
            'reason' => '申請A',
            'approval_status_code' => ApprovalStatusCode::PENDING,
        ]);
        AttendanceCorrection::create([
            'attendance_id' => $attendanceB->id,
            'request_user_id' => $userB->id,
            'requested_check_in_at' => '2026-04-25 08:40:00',
            'requested_check_out_at' => '2026-04-25 17:40:00',
            'reason' => '申請B',
            'approval_status_code' => ApprovalStatusCode::PENDING,
        ]);
        AttendanceCorrection::create([
            'attendance_id' => $attendanceA->id,
            'request_user_id' => $userA->id,
            'requested_check_in_at' => '2026-04-24 08:00:00',
            'requested_check_out_at' => '2026-04-24 17:00:00',
            'reason' => '承認済みデータ',
            'approval_status_code' => ApprovalStatusCode::APPROVED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        /** @var User $admin */
        $this->actingAs($admin);
        $response = $this->get(route('stamp_correction_requests.list', ['tab' => 'pending']));

        $response->assertOk();
        $response->assertSeeText('申請者A');
        $response->assertSeeText('申請者B');
        $response->assertSeeText('申請A');
        $response->assertSeeText('申請B');
        $response->assertDontSeeText('承認済みデータ');
    }

    #[Test]
    // [ID:15] 承認済みの修正申請が全て表示されている
    public function all_approved_correction_requests_are_displayed_for_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $userA = User::factory()->create(['is_admin' => false, 'name' => '申請者A']);
        $userB = User::factory()->create(['is_admin' => false, 'name' => '申請者B']);

        $attendanceA = $this->createAttendance($userA, '2026-04-24');
        $attendanceB = $this->createAttendance($userB, '2026-04-25');

        AttendanceCorrection::create([
            'attendance_id' => $attendanceA->id,
            'request_user_id' => $userA->id,
            'requested_check_in_at' => '2026-04-24 08:30:00',
            'requested_check_out_at' => '2026-04-24 17:30:00',
            'reason' => '承認済みA',
            'approval_status_code' => ApprovalStatusCode::APPROVED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
        AttendanceCorrection::create([
            'attendance_id' => $attendanceB->id,
            'request_user_id' => $userB->id,
            'requested_check_in_at' => '2026-04-25 08:40:00',
            'requested_check_out_at' => '2026-04-25 17:40:00',
            'reason' => '承認済みB',
            'approval_status_code' => ApprovalStatusCode::APPROVED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
        AttendanceCorrection::create([
            'attendance_id' => $attendanceA->id,
            'request_user_id' => $userA->id,
            'requested_check_in_at' => '2026-04-24 08:00:00',
            'requested_check_out_at' => '2026-04-24 17:00:00',
            'reason' => '承認待ちデータ',
            'approval_status_code' => ApprovalStatusCode::PENDING,
        ]);

        /** @var User $admin */
        $this->actingAs($admin);
        $response = $this->get(route('stamp_correction_requests.list', ['tab' => 'approved']));

        $response->assertOk();
        $response->assertSeeText('申請者A');
        $response->assertSeeText('申請者B');
        $response->assertSeeText('承認済みA');
        $response->assertSeeText('承認済みB');
        $response->assertDontSeeText('承認待ちデータ');
    }

    #[Test]
    // [ID:15] 修正申請の詳細内容が正しく表示されている
    public function correction_request_detail_is_displayed_correctly_for_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false, 'name' => '申請ユーザー']);
        $attendance = $this->createAttendance($user, '2026-04-24');

        $correction = AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'request_user_id' => $user->id,
            'requested_check_in_at' => '2026-04-24 08:30:00',
            'requested_check_out_at' => '2026-04-24 17:30:00',
            'reason' => '電車遅延のため',
            'approval_status_code' => ApprovalStatusCode::PENDING,
        ]);

        $correction->breakCorrections()->create([
            'break_start_at' => '2026-04-24 12:10:00',
            'break_end_at' => '2026-04-24 12:40:00',
        ]);

        /** @var User $admin */
        $this->actingAs($admin);
        $response = $this->get(route('admin.attendance.approve', $correction));

        $response->assertOk();
        $response->assertSeeText('申請ユーザー');
        $response->assertSeeText('2026年04月24日');
        $response->assertSeeText('08:30');
        $response->assertSeeText('17:30');
        $response->assertSeeText('12:10');
        $response->assertSeeText('12:40');
        $response->assertSeeText('電車遅延のため');
        $response->assertSeeText('承認');
    }

    #[Test]
    // [ID:15] 修正申請の承認処理が正しく行われる
    public function correction_request_is_approved_and_attendance_is_updated(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);
        $attendance = $this->createAttendance($user, '2026-04-24');

        $correction = AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'request_user_id' => $user->id,
            'requested_check_in_at' => '2026-04-24 08:30:00',
            'requested_check_out_at' => '2026-04-24 17:30:00',
            'reason' => '時刻修正',
            'approval_status_code' => ApprovalStatusCode::PENDING,
        ]);

        /** @var User $admin */
        $this->actingAs($admin);
        $response = $this->put(route('admin.attendance.approve.update', $correction));
        $response->assertRedirect(route('admin.attendance.approve', $correction));

        $correction->refresh();
        $attendance->refresh();

        $this->assertSame(ApprovalStatusCode::APPROVED, $correction->approval_status_code);
        $this->assertSame((int) $admin->id, (int) $correction->approved_by);
        $this->assertNotNull($correction->approved_at);
        $this->assertSame('2026-04-24 08:30:00', $attendance->check_in_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-24 17:30:00', $attendance->check_out_at?->format('Y-m-d H:i:s'));
        $this->assertSame('時刻修正', $attendance->remarks);
    }
}
