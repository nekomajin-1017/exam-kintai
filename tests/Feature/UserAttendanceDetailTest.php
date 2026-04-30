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
    public function name_field_shows_logged_in_users_name(): void
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
    public function date_field_shows_selected_date(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $attendance = $this->createAttendanceWithBreak($user, '2026-04-24');
        /** @var User $user */
        $this->actingAs($user);

        $response = $this->get(route('attendance.detail', $attendance));
        $response->assertOk();
        $response->assertSeeText('2026年04月24日');
    }

    #[Test]
    // [ID:10] 出勤・退勤に表示される時間がログインユーザーの打刻と一致している
    public function start_and_end_times_match_users_attendance(): void
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
    public function break_times_match_users_attendance(): void
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

        $attendance->breaks()->create([
            'break_start_at' => "{$workDate} 12:00:00",
            'break_end_at' => "{$workDate} 13:00:00",
        ]);

        return $attendance;
    }
}
