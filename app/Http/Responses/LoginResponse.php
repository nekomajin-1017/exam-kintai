<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {

        $target = $request->user()?->is_admin
            ? route('admin.dashboard')
            : route('attendance.index');

        return redirect()->intended($target);
    }
}
