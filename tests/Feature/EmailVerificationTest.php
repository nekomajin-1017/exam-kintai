<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    // [ID:16] 会員登録後、登録メールアドレス宛に認証メールが送信される
    public function verification_email_is_sent_after_registration(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'verify-target@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/home');
        $user = User::query()->where('email', 'verify-target@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    // [ID:16] 認証誘導画面の「認証はこちらから」ボタンがメール認証サイトURLを指している
    public function verify_link_button_points_to_mail_site(): void
    {
        $user = User::factory()->unverified()->create(['is_admin' => false]);
        /** @var User $user */
        $this->actingAs($user);

        $response = $this->get(route('verification.notice'));

        $response->assertOk();
        $response->assertSee('href="'.config('services.mail_ui_url').'"', false);
        $response->assertSeeText('認証はこちらから');
    }

    #[Test]
    // [ID:16] メール認証完了後、勤怠登録画面へ遷移する
    public function user_is_redirected_to_attendance_screen_after_verification(): void
    {
        $user = User::factory()->unverified()->create(['is_admin' => false]);
        /** @var User $user */
        $this->actingAs($user);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $response = $this->get($verificationUrl);

        $response->assertRedirect(route('attendance.index').'?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
