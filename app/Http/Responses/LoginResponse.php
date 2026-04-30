<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {

        if ($request->wantsJson()) {
            return response()->noContent();
        }

        $target = $request->user()?->is_admin
            ? route('admin.dashboard')
            : route('attendance.index');

        return redirect()->intended($target);
    }
}
