<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRestaurant
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->hasRestaurant() || $this->isExcludedRoute($request)) {
            return $next($request);
        }

        return redirect()->route('restaurant.edit');
    }

    private function isExcludedRoute(Request $request): bool
    {
        return $request->routeIs(
            'restaurant.edit',
            'restaurant.store',
            'restaurant.update',
            'profile.*'
        );
    }
}
