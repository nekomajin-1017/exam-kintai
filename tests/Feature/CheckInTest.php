<?php

namespace Tests\Feature;

use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    // [ID:6] 画面上に「出勤」ボタンが表示され、処理後に画面上に表示されるステータスが「出勤中」になる
    public function check_in_updates_status_to_working(): void
    {
        $this->withFrozenTime('2026-04-24 09:30:00', function (): void {
            $user = $this->loginUser();
            $indexResponse = $this->get(route('attendance.index'));
            $indexResponse->assertOk();
            $indexResponse->assertSeeText('出勤');
            $indexResponse->assertSeeText('勤務外');

            $stampResponse = $this->post(route('attendance.check_in'));
            $stampResponse->assertRedirect(route('attendance.index'));
            $this->assertDatabaseHas('attendances', [
                'user_id' => $user->id,
                'attendance_status_code' => 'working',
            ]);

            $afterResponse = $this->get(route('attendance.index'));
            $afterResponse->assertOk();
            $afterResponse->assertSeeText('出勤中');
        });
    }

    #[Test]
    // [ID:6] 出勤は1日に1回のみで、退勤済みステータスでは「出勤」ボタンが表示されない
    public function check_in_button_is_hidden_after_check_in(): void
    {
        $this->withFrozenTime('2026-04-24 09:30:00', function (): void {
            $user = $this->loginUser();
            Attendance::factory()->for($user)->create([
                'work_date' => '2026-04-24',
                'check_in_at' => '2026-04-24 09:00:00',
                'check_out_at' => '2026-04-24 18:00:00',
                'attendance_status_code' => 'finished',
            ]);

            $response = $this->get(route('attendance.index'));
            $response->assertOk();
            $response->assertSeeText('退勤済');
            $response->assertDontSee('action="'.route('attendance.check_in').'"', false);

            // 直接POSTされても当日の退勤済み勤怠は再出勤できない。
            $this->post(route('attendance.check_in'));
            $this->assertDatabaseHas('attendances', [
                'user_id' => $user->id,
                'check_in_at' => '2026-04-24 09:00:00',
                'attendance_status_code' => 'finished',
            ]);
            $this->assertEquals(1, Attendance::query()->where('user_id', $user->id)->count());
        });
    }

    #[Test]
    // [ID:6] 出勤時刻が勤怠一覧画面で正確に表示される
    public function check_in_time_is_displayed_in_attendance_list(): void
    {
        $this->withFrozenTime('2026-04-24 09:30:00', function (): void {
            $this->loginUser();
            $this->post(route('attendance.check_in'));

            $response = $this->get(route('attendance.list', ['month' => '2026-04']));
            $response->assertOk();
            $response->assertSeeText('09:30');
        });
    }
}
