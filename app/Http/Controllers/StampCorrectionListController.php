<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StampCorrectionListController extends Controller
{
    public function __construct(
        // 管理者向け申請一覧表示。
        private AdminCorrectionController $adminCorrectionController,
        // 一般ユーザー向け申請一覧表示。
        private CorrectionController $correctionController,
    ) {
        // DI注入のみ。
    }

    public function index(Request $request)
    {
        // 管理者は管理者向け一覧へ分岐する。
        if ($request->user()?->is_admin) {
            return $this->adminCorrectionController->list($request);
        }

        // 一般ユーザーで未認証メールなら認証案内へ戻す。
        if (! $request->user()?->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        // 一般ユーザー向け一覧を表示する。
        return $this->correctionController->list($request);
    }
}

