<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserPresence
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $user->presence()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'is_online' => true,
                    'last_seen_at' => now(),
                ]
            );
        }

        return $next($request);
    }
}
