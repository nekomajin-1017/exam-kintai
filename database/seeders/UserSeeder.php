<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private const COMMON_PASSWORD = 'Coachtech777';
    private const USER_NAMES = [
        '田中 太郎',
        '佐藤 花子',
        '鈴木 一郎',
        '高橋 美咲',
        '伊藤 健太',
        '渡辺 彩',
        '山本 大輔',
        '中村 優奈',
        '小林 翔',
        '加藤 さくら',
    ];

    public function run(): void
    {
        $this->createUsers();
        $this->createAdmins();
    }

    private function createUsers(): void
    {
        collect(range(1, 10))->each(function (int $number): void {
            User::factory()->create([
                'name' => self::USER_NAMES[$number - 1],
                'email' => "user{$number}@example.com",
                'password' => Hash::make(self::COMMON_PASSWORD),
                'is_admin' => false,
            ]);
        });
    }

    private function createAdmins(): void
    {
        collect(range(1, 2))->each(function (int $number): void {
            User::factory()->create([
                'name' => "admin{$number}",
                'email' => "admin{$number}@example.com",
                'password' => Hash::make(self::COMMON_PASSWORD),
                'is_admin' => true,
            ]);
        });
    }
}
