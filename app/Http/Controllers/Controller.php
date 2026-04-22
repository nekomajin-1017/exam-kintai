<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class Controller extends BaseController
{
    // 全Controllerで使う認可・バリデーション機能を有効化する。
    use AuthorizesRequests, ValidatesRequests;

    protected function headerVariant(): string
    {
        // ヘッダー種別を user/admin の2値で返す。
        return Auth::user()?->is_admin ? 'admin' : 'user';
    }
}
