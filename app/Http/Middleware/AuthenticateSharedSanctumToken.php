<?php

namespace App\Http\Middleware;

use App\Models\Sanctum\PersonalAccessToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSharedSanctumToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if ($plainTextToken) {
            $accessToken = PersonalAccessToken::findToken($plainTextToken);

            if (!$accessToken) {
                return $this->unauthorized();
            }

            if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
                return $this->unauthorized();
            }

            $user = $accessToken->tokenable;

            if (!$user || (property_exists($user, 'activo') && !$user->activo)) {
                return $this->unauthorized();
            }

            $user->withAccessToken($accessToken);
            $accessToken->forceFill(['last_used_at' => now()])->save();

            Auth::shouldUse('sanctum');
            Auth::setUser($user);
            $request->attributes->set('shared_access_token', $accessToken);
            $request->setUserResolver(static fn () => $user);
        } elseif (!$request->user()) {
            return $this->unauthorized();
        }

        return $next($request);
    }

    protected function unauthorized(): JsonResponse
    {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }
}
