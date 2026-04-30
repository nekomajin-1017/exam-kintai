<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUserListTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    // [ID:14] 管理者が全一般ユーザーの氏名・メールアドレスを確認できる
    public function admin_can_view_all_general_users_name_and_email(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $userA = User::factory()->create(['is_admin' => false, 'name' => '一般A', 'email' => 'a@example.com']);
        $userB = User::factory()->create(['is_admin' => false, 'name' => '一般B', 'email' => 'b@example.com']);
        $otherAdmin = User::factory()->create(['is_admin' => true, 'name' => '管理者X', 'email' => 'adminx@example.com']);

        /** @var User $admin */
        $this->actingAs($admin);
        $response = $this->get(route('admin.staff_list'));

        $response->assertOk();
        $response->assertSeeText('一般A');
        $response->assertSeeText('a@example.com');
        $response->assertSeeText('一般B');
        $response->assertSeeText('b@example.com');
        $response->assertDontSeeText('管理者X');
        $response->assertDontSeeText('adminx@example.com');
    }

    #[Test]
    // [ID:14] 選択したユーザーの勤怠情報が正しく表示される
    public function selected_users_attendance_is_displayed_correctly(): void
    {
        $this->withFrozenTime('2026-04-27 10:00:00', function (): void {
            $admin = User::factory()->create(['is_admin' => true]);
            $targetUser = User::factory()->create(['is_admin' => false, 'name' => '対象ユーザー']);
            $otherUser = User::factory()->create(['is_admin' => false, 'name' => '別ユーザー']);

            Attendance::factory()->for($targetUser)->create([
                'work_date' => '2026-04-10',
                'check_in_at' => '2026-04-10 09:11:00',
                'check_out_at' => '2026-04-10 18:11:00',
            ]);
            Attendance::factory()->for($otherUser)->create([
                'work_date' => '2026-04-10',
                'check_in_at' => '2026-04-10 07:00:00',
                'check_out_at' => '2026-04-10 16:00:00',
            ]);

            /** @var User $admin */
            $this->actingAs($admin);
            $response = $this->get(route('admin.attendance.list', ['user' => $targetUser->id, 'month' => '2026-04']));

            $response->assertOk();
            $response->assertSeeText('対象ユーザーさんの勤怠一覧');
            $response->assertSeeText('09:11');
            $response->assertSeeText('18:11');
            $response->assertDontSeeText('07:00');
            $response->assertDontSeeText('16:00');
        });
    }

    #[Test]
    // [ID:14] 「前月」を押下した時に表示月の前月情報が表示される
    public function previous_month_information_is_displayed_when_clicking_previous_month(): void
    {
        $this->withFrozenTime('2026-04-27 10:00:00', function (): void {
            $admin = User::factory()->create(['is_admin' => true]);
            $targetUser = User::factory()->create(['is_admin' => false, 'name' => '対象ユーザー']);
            Attendance::factory()->for($targetUser)->create([
                'work_date' => '2026-03-10',
                'check_in_at' => '2026-03-10 08:15:00',
                'check_out_at' => '2026-03-10 17:15:00',
            ]);
            Attendance::factory()->for($targetUser)->create([
                'work_date' => '2026-04-10',
                'check_in_at' => '2026-04-10 09:20:00',
                'check_out_at' => '2026-04-10 18:20:00',
            ]);

            /** @var User $admin */
            $this->actingAs($admin);
            $current = $this->get(route('admin.attendance.list', ['user' => $targetUser->id]));
            $current->assertOk();
            $current->assertSee('href="'.route('admin.attendance.list', ['user' => $targetUser->id, 'month' => '2026-03']).'"', false);

            $previous = $this->get(route('admin.attendance.list', ['user' => $targetUser->id, 'month' => '2026-03']));
            $previous->assertOk();
            $previous->assertSeeText('2026年3月');
            $previous->assertSeeText('08:15');
            $previous->assertDontSeeText('09:20');
        });
    }

    #[Test]
    // [ID:14] 「翌月」を押下した時に表示月の翌月情報が表示される
    public function next_month_information_is_displayed_when_clicking_next_month(): void
    {
        $this->withFrozenTime('2026-04-27 10:00:00', function (): void {
            $admin = User::factory()->create(['is_admin' => true]);
            $targetUser = User::factory()->create(['is_admin' => false, 'name' => '対象ユーザー']);
            Attendance::factory()->for($targetUser)->create([
                'work_date' => '2026-04-10',
                'check_in_at' => '2026-04-10 09:25:00',
                'check_out_at' => '2026-04-10 18:25:00',
            ]);
            Attendance::factory()->for($targetUser)->create([
                'work_date' => '2026-05-10',
                'check_in_at' => '2026-05-10 10:30:00',
                'check_out_at' => '2026-05-10 19:30:00',
            ]);

            /** @var User $admin */
            $this->actingAs($admin);
            $current = $this->get(route('admin.attendance.list', ['user' => $targetUser->id]));
            $current->assertOk();
            $current->assertSee('href="'.route('admin.attendance.list', ['user' => $targetUser->id, 'month' => '2026-05']).'"', false);

            $next = $this->get(route('admin.attendance.list', ['user' => $targetUser->id, 'month' => '2026-05']));
            $next->assertOk();
            $next->assertSeeText('2026年5月');
            $next->assertSeeText('10:30');
            $next->assertDontSeeText('09:25');
        });
    }

    #[Test]
    // [ID:14] 「詳細」を押下するとその日の勤怠詳細画面に遷移する
    public function detail_button_navigates_to_attendance_detail_screen(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $targetUser = User::factory()->create(['is_admin' => false, 'name' => '対象ユーザー']);
        $attendance = Attendance::factory()->for($targetUser)->create([
            'work_date' => '2026-04-10',
            'check_in_at' => '2026-04-10 09:00:00',
            'check_out_at' => '2026-04-10 18:00:00',
        ]);

        /** @var User $admin */
        $this->actingAs($admin);
        $listResponse = $this->get(route('admin.attendance.list', ['user' => $targetUser->id, 'month' => '2026-04']));
        $listResponse->assertOk();
        $listResponse->assertSee('href="'.route('admin.attendance.detail', $attendance).'"', false);

        $detailResponse = $this->get(route('admin.attendance.detail', $attendance));
        $detailResponse->assertOk();
        $detailResponse->assertSeeText('2026年04月10日');
        $detailResponse->assertSee('value="09:00"', false);
        $detailResponse->assertSee('value="18:00"', false);
    }
}
