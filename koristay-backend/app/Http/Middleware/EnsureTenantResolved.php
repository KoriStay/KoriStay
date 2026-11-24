<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantResolved
{
    public function __construct(protected TenantContext $tenantContext){}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->tenantContext->id()) {
            abort(403, 'Tenant context is required for this endpoint.');
        }

        return $next($request);
    }
}
