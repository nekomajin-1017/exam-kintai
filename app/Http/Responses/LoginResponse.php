<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        // 仕様上、記載必要。
        if ($request->wantsJson()) {
            return response()->noContent();
        }

        // 権限に応じて初期遷移先を切り替える。
        $target = $request->user()?->is_admin
            ? route('admin.dashboard')
            : route('attendance.index');

        // intended URL があれば優先し、無ければ既定先へ遷移する。
        return redirect()->intended($target);
    }
}
