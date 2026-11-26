<?php

namespace App\Models\Concern;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static void addGlobalScope(\Illuminate\Database\Eloquent\Scope|\Closure|string $scope, \Closure|null $implementation = null)
 * @method static void creating(\Illuminate\Events\QueuedClosure|\Closure|string $callback)
 * @method \Illuminate\Database\Eloquent\Relations\BelongsTo belongsTo(string $related, string|null $foreignKey = null, string|null $ownerKey = null, string|null $relation = null)
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (! $model->tenant_id && ($tenantId = app(TenantContext::class)->id())) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
