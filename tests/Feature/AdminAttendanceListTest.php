<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    // [ID:12] その日になされた全ユーザーの勤怠情報が正確に確認できる
    public function admin_can_view_all_users_attendance_for_the_day(): void
    {
        $this->withFrozenTime('2026-04-27 10:00:00', function (): void {
            $this->loginAdmin();
            $user1 = User::factory()->create(['is_admin' => false, 'name' => 'ユーザーA']);
            $user2 = User::factory()->create(['is_admin' => false, 'name' => 'ユーザーB']);

            Attendance::factory()->for($user1)->create([
                'work_date' => '2026-04-27',
                'check_in_at' => '2026-04-27 09:01:00',
                'check_out_at' => '2026-04-27 18:01:00',
            ]);
            Attendance::factory()->for($user2)->create([
                'work_date' => '2026-04-27',
                'check_in_at' => '2026-04-27 09:02:00',
                'check_out_at' => '2026-04-27 18:02:00',
            ]);
            Attendance::factory()->for($user1)->create([
                'work_date' => '2026-04-26',
                'check_in_at' => '2026-04-26 08:59:00',
                'check_out_at' => '2026-04-26 17:59:00',
            ]);

            $response = $this->get(route('admin.dashboard'));
            $response->assertOk();
            $response->assertSeeText('ユーザーA');
            $response->assertSeeText('ユーザーB');
            $response->assertSeeText('09:01');
            $response->assertSeeText('18:01');
            $response->assertSeeText('09:02');
            $response->assertSeeText('18:02');
            $response->assertDontSeeText('08:59');
            $response->assertDontSeeText('17:59');
        });
    }

    #[Test]
    // [ID:12] 遷移した際に現在の日付が表示される
    public function current_date_is_displayed_when_opening_admin_attendance_list(): void
    {
        $this->withFrozenTime('2026-04-27 10:00:00', function (): void {
            $this->loginAdmin();
            $response = $this->get(route('admin.dashboard'));
            $response->assertOk();
            $response->assertSeeText('2026年04月27日');
        });
    }

    #[Test]
    // [ID:12] 「前日」を押下した時に前日の日付の勤怠情報が表示される
    public function previous_day_information_is_displayed_when_clicking_previous_day(): void
    {
        $this->withFrozenTime('2026-04-27 10:00:00', function (): void {
            $this->loginAdmin();
            $user = User::factory()->create(['is_admin' => false, 'name' => '前日ユーザー']);
            Attendance::factory()->for($user)->create([
                'work_date' => '2026-04-26',
                'check_in_at' => '2026-04-26 09:10:00',
                'check_out_at' => '2026-04-26 18:10:00',
            ]);

            $todayResponse = $this->get(route('admin.dashboard'));
            $todayResponse->assertOk();
            $todayResponse->assertSee('href="'.route('admin.dashboard', ['date' => '2026-04-26']).'"', false);

            $previousResponse = $this->get(route('admin.dashboard', ['date' => '2026-04-26']));
            $previousResponse->assertOk();
            $previousResponse->assertSeeText('2026年04月26日');
            $previousResponse->assertSeeText('前日ユーザー');
            $previousResponse->assertSeeText('09:10');
            $previousResponse->assertSeeText('18:10');
        });
    }

    #[Test]
    // [ID:12] 「翌日」を押下した時に翌日の日付の勤怠情報が表示される
    public function next_day_information_is_displayed_when_clicking_next_day(): void
    {
        $this->withFrozenTime('2026-04-27 10:00:00', function (): void {
            $this->loginAdmin();
            $user = User::factory()->create(['is_admin' => false, 'name' => '翌日ユーザー']);
            Attendance::factory()->for($user)->create([
                'work_date' => '2026-04-28',
                'check_in_at' => '2026-04-28 09:20:00',
                'check_out_at' => '2026-04-28 18:20:00',
            ]);

            $todayResponse = $this->get(route('admin.dashboard'));
            $todayResponse->assertOk();
            $todayResponse->assertSee('href="'.route('admin.dashboard', ['date' => '2026-04-28']).'"', false);

            $nextResponse = $this->get(route('admin.dashboard', ['date' => '2026-04-28']));
            $nextResponse->assertOk();
            $nextResponse->assertSeeText('2026年04月28日');
            $nextResponse->assertSeeText('翌日ユーザー');
            $nextResponse->assertSeeText('09:20');
            $nextResponse->assertSeeText('18:20');
        });
    }
}
