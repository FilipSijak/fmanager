<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnsureGameIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $instanceHash = $request->header('instanceHash');
        $instanceId = $request->header('instanceId');


        $result = DB::select(
            'SELECT * FROM instances WHERE instance_hash = :instanceHash AND id = :instanceId',
            ['instanceHash' => $instanceHash, 'instanceId' => $instanceId]
        );

        if (!$result) {
            return response()->json('Your account is inactive');
        }

        return $next($request);
    }
}
