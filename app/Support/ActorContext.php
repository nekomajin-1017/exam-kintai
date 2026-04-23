<?php

namespace App\Support;

enum ActorContext: string
{
    // 一般ユーザー文脈。
    case USER = 'user';
    // 管理者文脈。
    case ADMIN = 'admin';

    public static function fromUser(?object $user): self
    {
        // 認証ユーザーから画面文脈を導出する。
        return ($user?->is_admin ?? false) ? self::ADMIN : self::USER;
    }

    public function headerVariant(): string
    {
        // ヘッダー切り替え用に enum 値を返す。
        return $this->value;
    }

    public function isAdmin(): bool
    {
        // 管理者文脈判定。
        return $this === self::ADMIN;
    }
}
