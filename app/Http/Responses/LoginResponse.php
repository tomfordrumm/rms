<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request instanceof Request && $request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $user = $request->user();

        if ($user !== null && ! $user->hasRestaurant()) {
            return redirect()->route('restaurant.edit');
        }

        return redirect()->intended(config('fortify.home'));
    }
}
