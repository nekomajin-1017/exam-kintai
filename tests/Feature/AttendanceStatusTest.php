<?php

namespace Tests\Feature;

use App\Constants\AttendanceStatusCode;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    // [ID:5] 勤務外の場合、画面上に表示されているステータスが「勤務外」と表示されている
    public function attendance_status_is_off_work(): void
    {
        $this->withFrozenTime('2026-04-27 09:30:00', function (): void {
            $user = $this->loginUser();
            Attendance::factory()->for($user)->state([
                'work_date' => '2026-04-27',
                'attendance_status_code' => AttendanceStatusCode::OFF,
            ])->create();

            $response = $this->get(route('attendance.index'));
            $response->assertOk();
            $response->assertSeeText('勤務外');
        });
    }

    #[Test]
    // [ID:5] 出勤中の場合、画面上に表示されているステータスが「出勤中」と表示されている
    public function attendance_status_is_working(): void
    {
        $this->withFrozenTime('2026-04-27 09:30:00', function (): void {
            $user = $this->loginUser();
            Attendance::factory()->for($user)->state([
                'work_date' => '2026-04-27',
                'attendance_status_code' => AttendanceStatusCode::WORKING,
            ])->create();

            $response = $this->get(route('attendance.index'));
            $response->assertOk();
            $response->assertSeeText('出勤中');
        });
    }

    #[Test]
    // [ID:5] 休憩中の場合、画面上に表示されているステータスが「休憩中」と表示されている
    public function attendance_status_is_on_break(): void
    {
        $this->withFrozenTime('2026-04-27 09:30:00', function (): void {
            $user = $this->loginUser();
            Attendance::factory()->for($user)->state([
                'work_date' => '2026-04-27',
                'attendance_status_code' => AttendanceStatusCode::ON_BREAK,
            ])->create();

            $response = $this->get(route('attendance.index'));
            $response->assertOk();
            $response->assertSeeText('休憩中');
        });
    }

    #[Test]
    // [ID:5] 退勤済みの場合、画面上に表示されているステータスが「退勤済」と表示されている
    public function attendance_status_is_finished(): void
    {
        $this->withFrozenTime('2026-04-27 09:30:00', function (): void {
            $user = $this->loginUser();
            Attendance::factory()->for($user)->state([
                'work_date' => '2026-04-27',
                'attendance_status_code' => AttendanceStatusCode::FINISHED,
            ])->create();

            $response = $this->get(route('attendance.index'));
            $response->assertOk();
            $response->assertSeeText('退勤済');
        });
    }
}
