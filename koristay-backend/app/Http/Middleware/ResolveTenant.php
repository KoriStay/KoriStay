<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Tenant;
use Illuminate\Http\Request;
use App\Support\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(protected TenantContext $tenantContext){}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = null;

        $user = $request->user() ?? $request->user('sanctum');

        if ($user && $user->tenant_id) {
            $tenant = Tenant::where('id', $user->tenant_id)->where('is_active', true)->first();

            if (!$tenant) {
                abort(403, 'Your organization is not active or unavailable.');
            }
        }

        elseif ($identifier = $request->header('X-Tenant')) {
            $tenant = Tenant::where('is_active', true)
                ->where(function ($query) use ($identifier) {
                    $query->where('slug', $identifier)
                        ->orWhere('custom_domain', $identifier);
                })->first();

            if (!$tenant) {
                abort(403, 'Your organization is not active or unavailable.');
            }
        }

        if ($tenant) {
            $this->tenantContext->setTenant($tenant);
        }

        return $next($request);
    }
}
