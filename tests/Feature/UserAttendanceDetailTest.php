<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    // [ID:10] 勤怠詳細画面の「名前」がログインユーザーの氏名になっている
    public function shows_logged_in_user_name(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'name' => '山田 太郎']);
        $attendance = $this->createAttendanceWithBreak($user, '2026-04-24');
        /** @var User $user */
        $this->actingAs($user);

        $response = $this->get(route('attendance.detail', $attendance));
        $response->assertOk();
        $response->assertSeeText('山田 太郎');
    }

    #[Test]
    // [ID:10] 勤怠詳細画面の「日付」が選択した日付になっている
    public function shows_selected_date(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $attendance = $this->createAttendanceWithBreak($user, '2026-04-24');
        /** @var User $user */
        $this->actingAs($user);

        $response = $this->get(route('attendance.detail', $attendance));
        $response->assertOk();
        $response->assertSeeText('2026年4月24日');
    }

    #[Test]
    // [ID:10] 出勤・退勤に表示される時間がログインユーザーの打刻と一致している
    public function shows_start_end_times(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $attendance = $this->createAttendanceWithBreak($user, '2026-04-24');
        /** @var User $user */
        $this->actingAs($user);

        $response = $this->get(route('attendance.detail', $attendance));
        $response->assertOk();
        $response->assertSee('value="09:00"', false);
        $response->assertSee('value="18:00"', false);
    }

    #[Test]
    // [ID:10] 休憩に表示される時間がログインユーザーの打刻と一致している
    public function shows_break_times(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $attendance = $this->createAttendanceWithBreak($user, '2026-04-24');
        /** @var User $user */
        $this->actingAs($user);

        $response = $this->get(route('attendance.detail', $attendance));
        $response->assertOk();
        $response->assertSee('value="12:00"', false);
        $response->assertSee('value="13:00"', false);
    }

    private function createAttendanceWithBreak(User $user, string $workDate): Attendance
    {
        $attendance = $this->createAttendance($user, $workDate, [
            'attendance_status_code' => 'finished',
        ]);

        $attendance->attendanceBreaks()->create([
            'break_start_at' => "{$workDate} 12:00:00",
            'break_end_at' => "{$workDate} 13:00:00",
        ]);

        return $attendance;
    }
}
