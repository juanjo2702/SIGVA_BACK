<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\Sanctum\PersonalAccessToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSharedSanctumToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if ($plainTextToken) {
            $accessToken = PersonalAccessToken::findToken($plainTextToken);

            if (!$accessToken && str_contains($plainTextToken, '|')) {
                [$tokenId, $tokenValue] = explode('|', $plainTextToken, 2);
                $candidate = PersonalAccessToken::query()->find($tokenId);

                if ($candidate && hash_equals($candidate->token, hash('sha256', $tokenValue))) {
                    $accessToken = $candidate;
                }
            }

            if (!$accessToken) {
                Log::warning('SIGVA shared auth: token not found', [
                    'token_prefix' => substr($plainTextToken, 0, 12),
                ]);
                return $this->unauthorized();
            }

            if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
                return $this->unauthorized();
            }

            $user = $accessToken->tokenable;

            if (!$user && $accessToken->tokenable_id) {
                $user = User::query()->find($accessToken->tokenable_id);
            }

            if (!$user || (($user->activo ?? true) === false)) {
                Log::warning('SIGVA shared auth: user could not be resolved from token', [
                    'token_id' => $accessToken->getKey(),
                    'tokenable_type' => $accessToken->tokenable_type,
                    'tokenable_id' => $accessToken->tokenable_id,
                ]);
                return $this->unauthorized();
            }

            $user->withAccessToken($accessToken);
            $accessToken->forceFill(['last_used_at' => now()])->save();

            Auth::shouldUse('sanctum');
            Auth::setUser($user);
            $request->attributes->set('shared_access_token', $accessToken);
            $request->setUserResolver(static fn () => $user);
        } elseif (!$request->user()) {
            Log::warning('SIGVA shared auth: missing bearer token');
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
