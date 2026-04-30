<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    // [ID:9] 自分の勤怠情報がすべて表示される
    public function all_attendance_records_of_authenticated_user_are_displayed(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $otherUser = User::factory()->create(['is_admin' => false]);
        /** @var User $user */
        $this->actingAs($user);

        Attendance::factory()->for($user)->create([
            'work_date' => '2026-04-10',
            'check_in_at' => '2026-04-10 07:11:00',
            'check_out_at' => '2026-04-10 16:11:00',
        ]);
        Attendance::factory()->for($user)->create([
            'work_date' => '2026-04-20',
            'check_in_at' => '2026-04-20 10:22:00',
            'check_out_at' => '2026-04-20 19:22:00',
        ]);
        Attendance::factory()->for($otherUser)->create([
            'work_date' => '2026-04-10',
            'check_in_at' => '2026-04-10 23:59:00',
            'check_out_at' => '2026-04-10 23:59:00',
        ]);

        $response = $this->get(route('attendance.list', ['month' => '2026-04']));
        $response->assertOk();
        $response->assertSeeText('07:11');
        $response->assertSeeText('10:22');
        $response->assertDontSeeText('23:59');
    }

    #[Test]
    // [ID:9] 勤怠一覧画面の初期表示で現在の月が表示される
    public function current_month_is_shown_when_opening_attendance_list(): void
    {
        $this->withFrozenTime('2026-04-24 09:30:00', function (): void {
            $this->loginUser();
            $response = $this->get(route('attendance.list'));
            $response->assertOk();
            $response->assertSeeText('2026年4月');
        });
    }

    #[Test]
    // [ID:9] 「前月」を押下すると前月の情報が表示される
    public function previous_month_records_are_displayed_when_clicking_previous_month(): void
    {
        $this->withFrozenTime('2026-04-24 09:30:00', function (): void {
            $user = $this->loginUser();

            Attendance::factory()->for($user)->create([
                'work_date' => '2026-03-15',
                'check_in_at' => '2026-03-15 08:11:00',
                'check_out_at' => '2026-03-15 17:11:00',
            ]);
            Attendance::factory()->for($user)->create([
                'work_date' => '2026-04-15',
                'check_in_at' => '2026-04-15 09:22:00',
                'check_out_at' => '2026-04-15 18:22:00',
            ]);

            $currentResponse = $this->get(route('attendance.list'));
            $currentResponse->assertOk();
            $currentResponse->assertSee('href="'.route('attendance.list', ['month' => '2026-03']).'"', false);

            $previousResponse = $this->get(route('attendance.list', ['month' => '2026-03']));
            $previousResponse->assertOk();
            $previousResponse->assertSeeText('2026年3月');
            $previousResponse->assertSeeText('08:11');
            $previousResponse->assertDontSeeText('09:22');
        });
    }

    #[Test]
    // [ID:9] 「翌月」を押下すると翌月の情報が表示される
    public function next_month_records_are_displayed_when_clicking_next_month(): void
    {
        $this->withFrozenTime('2026-04-24 09:30:00', function (): void {
            $user = $this->loginUser();

            Attendance::factory()->for($user)->create([
                'work_date' => '2026-04-18',
                'check_in_at' => '2026-04-18 08:33:00',
                'check_out_at' => '2026-04-18 17:33:00',
            ]);
            Attendance::factory()->for($user)->create([
                'work_date' => '2026-05-18',
                'check_in_at' => '2026-05-18 09:44:00',
                'check_out_at' => '2026-05-18 18:44:00',
            ]);

            $currentResponse = $this->get(route('attendance.list'));
            $currentResponse->assertOk();
            $currentResponse->assertSee('href="'.route('attendance.list', ['month' => '2026-05']).'"', false);

            $nextResponse = $this->get(route('attendance.list', ['month' => '2026-05']));
            $nextResponse->assertOk();
            $nextResponse->assertSeeText('2026年5月');
            $nextResponse->assertSeeText('09:44');
            $nextResponse->assertDontSeeText('08:33');
        });
    }

    #[Test]
    // [ID:9] 「詳細」押下でその日の勤怠詳細画面に遷移できる
    public function detail_button_navigates_to_attendance_detail_for_the_day(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        /** @var User $user */
        $this->actingAs($user);

        $attendance = Attendance::factory()->for($user)->create([
            'work_date' => '2026-04-10',
            'check_in_at' => '2026-04-10 09:00:00',
            'check_out_at' => '2026-04-10 18:00:00',
        ]);

        $listResponse = $this->get(route('attendance.list', ['month' => '2026-04']));
        $listResponse->assertOk();
        $listResponse->assertSee('href="'.route('attendance.detail', $attendance).'"', false);

        $detailResponse = $this->get(route('attendance.detail', $attendance));
        $detailResponse->assertOk();
        $detailResponse->assertSeeText('2026年04月10日');
    }
}
