<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToTenant
{
    public function __construct(protected TenantContext $tenantContext) {}

    /**
     * Reject users who are not staff of the resolved tenant, including users
     * without a tenant who picked one through the X-Tenant header.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $this->tenantContext->id();

        if ($tenantId === null || $request->user()?->tenant_id !== $tenantId) {
            abort(403, 'You are not a member of this tenant.');
        }

        return $next($request);
    }
}
