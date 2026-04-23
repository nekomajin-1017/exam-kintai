<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    // 全Controllerで使う認可・バリデーション機能を有効化する。
    use AuthorizesRequests, ValidatesRequests;
}
