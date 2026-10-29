<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoints chamados por máquina (cron, worker do YouTube) não têm sessão --
 * se autenticam com o token compartilhado, no header X-API-Token ou em ?token=.
 */
class VerifyApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('lectta.api_token');
        $given = (string) ($request->header('X-API-Token') ?? $request->query('token', ''));

        if ($expected === '' || !hash_equals($expected, $given)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        return $next($request);
    }
}
