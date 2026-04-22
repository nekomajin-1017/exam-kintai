<?php

namespace App\Helpers;

class TimeHelper
{
    /**
     * 秒数を「H:i」形式の時刻文字列に変換する。
     * 例：28800秒 → "08:00"
     *
     * @param int $seconds 秒数
     * @return string 「H:i」形式の文字列
     */
    public static function formatSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}