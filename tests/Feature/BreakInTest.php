<?php

namespace Tests\Feature;

use App\Constants\AttendanceStatusCode;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BreakInTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    // [ID:7] 画面上に「休憩入」ボタンが表示され、処理後に画面上に表示されるステータスが「休憩中」になる
    public function test_break_in_button_and_status(): void
    {
        $this->withFrozenTime('2026-04-24 12:00:00', function (): void {
            $user = $this->loginUser();
            $this->post(route('attendance.check_in'));

            $indexResponse = $this->get(route('attendance.index'));
            $indexResponse->assertOk();
            $indexResponse->assertSeeText('休憩入');
            $indexResponse->assertSeeText('出勤中');

            $stampResponse = $this->post(route('attendance.break_in'));
            $stampResponse->assertRedirect(route('attendance.index'));
            $this->assertDatabaseHas('attendances', [
                'user_id' => $user->id,
                'attendance_status_code' => AttendanceStatusCode::ON_BREAK,
            ]);

            $afterResponse = $this->get(route('attendance.index'));
            $afterResponse->assertOk();
            $afterResponse->assertSeeText('休憩中');
        });
    }

    #[Test]
    // [ID:7] 休憩は1日に何回でもでき、画面上の「休憩入」ボタンが表示される
    public function test_break_in_button_is_displayed(): void
    {
        $this->withFrozenTime('2026-04-24 12:00:00', function (): void {
            $this->loginUser();
            $this->post(route('attendance.check_in'));
            $this->post(route('attendance.break_in'));
            $this->post(route('attendance.break_out'));

            $this->withFrozenTime('2026-04-24 15:00:00', function (): void {
                $this->post(route('attendance.break_in'));
                $this->post(route('attendance.break_out'));
            });

            $response = $this->get(route('attendance.index'));
            $response->assertOk();
            $response->assertSeeText('休憩入');
            $this->assertEquals(2, AttendanceBreak::query()->count());
        });
    }

    #[Test]
    // [ID:7] 休憩戻ボタンが表示され、処理後にステータスが「出勤中」に変更される
    public function test_break_out_button_and_status(): void
    {
        $this->withFrozenTime('2026-04-24 13:00:00', function (): void {
            $user = $this->loginUser();
            $this->post(route('attendance.check_in'));
            $this->post(route('attendance.break_in'));

            $indexResponse = $this->get(route('attendance.index'));
            $indexResponse->assertOk();
            $indexResponse->assertSeeText('休憩戻');
            $indexResponse->assertSeeText('休憩中');

            $stampResponse = $this->post(route('attendance.break_out'));
            $stampResponse->assertRedirect(route('attendance.index'));
            $this->assertDatabaseHas('attendances', [
                'user_id' => $user->id,
                'attendance_status_code' => AttendanceStatusCode::WORKING,
            ]);

            $afterResponse = $this->get(route('attendance.index'));
            $afterResponse->assertOk();
            $afterResponse->assertSeeText('出勤中');
            $afterResponse->assertSeeText('休憩入');
        });
    }

    #[Test]
    // [ID:7] 休憩は1日に何回でもでき、画面上の「休憩戻」ボタンが表示される
    public function test_break_out_button_is_displayed(): void
    {
        $this->withFrozenTime('2026-04-24 13:00:00', function (): void {
            $this->loginUser();
            $this->post(route('attendance.check_in'));
            $this->post(route('attendance.break_in'));
            $this->post(route('attendance.break_out'));

            $this->withFrozenTime('2026-04-24 14:30:00', function (): void {
                $this->post(route('attendance.break_in'));
            });

            $response = $this->get(route('attendance.index'));
            $response->assertOk();
            $response->assertSeeText('休憩戻');
        });
    }

    #[Test]
    // [ID:7] 休憩時刻が勤怠一覧画面で正確に表示されている

    public function test_break_time_is_displayed_in_attendance_list(): void
    {
        $this->withFrozenTime('2026-04-24 09:00:00', function (): void {
            $user = $this->loginUser();
            $this->post(route('attendance.check_in'));

            $this->withFrozenTime('2026-04-24 12:00:00', function (): void {
                $this->post(route('attendance.break_in'));
            });

            $this->withFrozenTime('2026-04-24 13:00:00', function (): void {
                $this->post(route('attendance.break_out'));
            });

            $response = $this->get(route('attendance.list'));
            $response->assertOk();
            $response->assertSeeText('01:00');
            $attendance = Attendance::query()->where('user_id', $user->id)->firstOrFail();
            /** @var Attendance $attendance */
            $this->assertDatabaseHas('attendance_breaks', [
                'attendance_id' => $attendance->id,
                'break_start_at' => '2026-04-24 12:00:00',
                'break_end_at' => '2026-04-24 13:00:00',
            ]);
            $this->assertEquals(1, AttendanceBreak::query()->count());
        });
    }
}
