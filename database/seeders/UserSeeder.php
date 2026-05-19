<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private const COMMON_PASSWORD = 'Coachtech777';

    private const USER_NAMES = [
        '山田 太郎',
        '鈴木 花子',
        '田中 一郎',
        '高橋 レモン',
        '伊藤 二郎',
        '渡辺 ゆり',
        '山本 次郎',
        '中村 すみれ',
        '小林 三郎',
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