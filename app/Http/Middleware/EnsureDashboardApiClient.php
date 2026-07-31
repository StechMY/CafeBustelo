<?php

namespace App\Http\Middleware;

use App\Models\DashboardApiClient;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDashboardApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->user();

        if (! $client instanceof DashboardApiClient || ! $client->is_active) {
            throw new AuthorizationException('This token is not authorized for the management dashboard.');
        }

        $response = $next($request);

        if ($client->last_used_at === null || $client->last_used_at->lte(now()->subMinutes(5))) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $response;
    }
}
