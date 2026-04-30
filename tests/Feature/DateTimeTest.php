<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DateTimeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    // [ID:4] 現在の日時情報が正しく取得され、画面に表示されていることを確認する
    public function current_datetime_is_displayed(): void
    {
        $this->withFrozenTime('2026-04-24 09:30:00', function (): void {
            $this->loginUser();
            $response = $this->get(route('attendance.index'));
            $response->assertOk();

            $expected = now()->locale('ja')->isoFormat('YYYY年MM月DD日(ddd) HH:mm');
            $response->assertSeeText($expected);
        });
    }
}
