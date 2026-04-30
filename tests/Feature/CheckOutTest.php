<?php

namespace Tests\Feature;

use App\Constants\AttendanceStatusCode;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckOutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    // [ID:8] 画面上に「退勤」ボタンが表示され、処理後に画面上に表示されるステータスが「退勤済」になる
    public function check_out_button_is_displayed_and_status_changes_to_checked_out_after_processing(): void
    {
        $this->withFrozenTime('2026-04-24 18:00:00', function (): void {
            $user = $this->loginUser();
            $this->post(route('attendance.check_in'));

            // 画面上に「退勤」ボタンが表示されていることを確認
            $response = $this->get(route('attendance.index'));
            $response->assertOk();
            $response->assertSeeText('退勤');
            $response->assertSeeText('出勤中');

            // 「退勤」ボタンをクリックして処理を実行
            $response = $this->post(route('attendance.check_out'));

            // 処理後のリダイレクト先を確認
            $response->assertRedirect(route('attendance.index'));
            $this->assertDatabaseHas('attendances', [
                'user_id' => $user->id,
                'attendance_status_code' => AttendanceStatusCode::FINISHED,
            ]);

            // 画面上に表示されるステータスが「退勤済」になっていることを確認
            $response = $this->get(route('attendance.index'));
            $response->assertOk();
            $response->assertSeeText('退勤済');
        });
    }

    #[Test]
    // [ID:8] 勤怠一覧画面に退勤時刻が正確に記録されている
    public function check_out_time_is_recorded_correctly_in_attendance_list(): void
    {
        $this->withFrozenTime('2026-04-24 09:00:00', function (): void {
            $user = $this->loginUser();
            $this->post(route('attendance.check_in'));

            // 「退勤」ボタンをクリックして処理を実行
            $this->withFrozenTime('2026-04-24 18:00:00', function (): void {
                $this->post(route('attendance.check_out'));
            });

            // 勤怠一覧画面に退勤時刻が正確に記録されていることを確認
            $response = $this->get(route('attendance.list', ['month' => '2026-04']));
            $response->assertOk();
            $response->assertSeeText('18:00');
            $attendance = Attendance::query()->where('user_id', $user->id)->firstOrFail();
            /** @var Attendance $attendance */
            $this->assertDatabaseHas('attendances', [
                'id' => $attendance->id,
                'attendance_status_code' => AttendanceStatusCode::FINISHED,
                'check_out_at' => '2026-04-24 18:00:00',
            ]);
        });
    }
}
