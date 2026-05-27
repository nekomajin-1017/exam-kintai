<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    // [ID:1] 登録画面において名前が未入力の場合、「お名前を入力してください」というバリデーションメッセージが表示される
    public function nameIsRequired(): void
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertEquals('お名前を入力してください', session('errors')->first('name'));
    }

    #[Test]
    // [ID:1] 登録画面においてメールアドレスが未入力の場合、「メールアドレスを入力してください」というバリデーションメッセージが表示される
    public function emailIsRequired(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => '',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertEquals('メールアドレスを入力してください', session('errors')->first('email'));
    }

    #[Test]
    // [ID:1] 登録画面においてパスワードが8文字未満の場合、「パスワードは8文字以上で入力してください」というバリデーションメッセージが表示される
    public function passwordMustBeAtLeast8Characters(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'pass',
            'password_confirmation' => 'pass',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertEquals('パスワードは8文字以上で入力してください', session('errors')->first('password'));
    }

    #[Test]
    // [ID:1] 登録画面においてパスワードとパスワード確認が一致しない場合、「パスワードが一致しません」というバリデーションメッセージが表示される
    public function passwordConfirmationMustMatch(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'different_password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertEquals('パスワードと一致しません', session('errors')->first('password'));
    }

    #[Test]
    // [ID:1] 登録画面においてパスワードが未入力の場合、「パスワードを入力してください」というバリデーションメッセージが表示される
    public function passwordIsRequired(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertEquals('パスワードを入力してください', session('errors')->first('password'));
    }

    #[Test]
    // [ID:1] 登録画面においてフォームに内容が入力されていた場合、データベースに登録したユーザー情報が保存される
    public function registersUserInDatabase(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    #[Test]
    // [ID:2] 一般ユーザーのログイン画面においてメールアドレスが未入力の場合、「メールアドレスを入力してください」というバリデーションメッセージが表示される
    public function loginRequiresEmail(): void
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertEquals('メールアドレスを入力してください', session('errors')->first('email'));
    }

    #[Test]
    // [ID:2] 一般ユーザーのログイン画面においてパスワードが未入力の場合、「パスワードを入力してください」というバリデーションメッセージが表示される
    public function loginRequiresPassword(): void
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertEquals('パスワードを入力してください', session('errors')->first('password'));
    }

    #[Test]
    // [ID:2] 一般ユーザーのログイン画面において入力内容が登録内容と一致しない場合、「ログイン情報が登録されていません」というバリデーションメッセージが表示される
    public function loginRejectsInvalidCredentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertEquals('ログイン情報が登録されていません', session('errors')->first('email'));
    }

    #[Test]
    // [ID:3] 管理者のログイン画面においてメールアドレスが未入力の場合、「メールアドレスを入力してください」というバリデーションメッセージが表示される
    public function adminLoginRequiresEmail(): void
    {
        $response = $this->post('/admin/login', [
            'email' => '',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertEquals('メールアドレスを入力してください', session('errors')->first('email'));
    }

    #[Test]
    // [ID:3] 管理者のログイン画面においてパスワードが未入力の場合、「パスワードを入力してください」というバリデーションメッセージが表示される
    public function adminLoginRequiresPassword(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertEquals('パスワードを入力してください', session('errors')->first('password'));
    }

    #[Test]
    // [ID:3] 管理者のログイン画面において入力内容が登録内容と一致しない場合、「ログイン情報が登録されていません」というバリデーションメッセージが表示される
    public function adminLoginRejectsInvalidCredentials(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertEquals('ログイン情報が登録されていません', session('errors')->first('email'));
    }
}
